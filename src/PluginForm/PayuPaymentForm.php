<?php

namespace Drupal\commerce_payu\PluginForm;

use Drupal;
use Drupal\Core\Messenger\MessengerInterface;
use OpenPayU_Configuration;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use OpenPayU_Exception_Request;
use OpenPayU_Order;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PayuPaymentForm.
 *
 * @package Drupal\commerce_payu\PluginForm
 */
class PayuPaymentForm extends BasePaymentOffsiteForm implements ContainerInjectionInterface {

  /**
   * The logger factory.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $loggerFactory;

  /**
   * The drupal messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private $messenger;

  /**
   * Constructs a Drupal\rest\Plugin\rest\resource\EntityResource object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger instance.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, MessengerInterface $messenger) {
    $this->loggerFactory = $logger;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('messenger')
    );
  }

  /**
   * Form constructor.
   *
   * Plugin forms are embedded in other forms. In order to know where the plugin
   * form is located in the parent form, #parents and #array_parents must be
   * known, but these are not available during the initial build phase. In order
   * to have these properties available when building the plugin form's
   * elements, let this method return a form element that has a #process
   * callback and build the rest of the form in the callback. By the time the
   * callback is executed, the element's #parents and #array_parents properties
   * will have been set by the form API. For more documentation on #parents and
   * #array_parents, see \Drupal\Core\Render\Element\FormElement.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   *
   * @return array
   *   Returns the form structure.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *   Throws MissingDataException.
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   *   Throws NeedsRedirectException.
   * @throws \OpenPayU_Exception
   *   Throws OpenPayU_Exception.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $drupalOrder = $payment->getOrder();
    $billing_address = $drupalOrder->getBillingProfile()
      ->get('address')
      ->first();
    $orderDate = date('H:i:s Y/m/d', $drupalOrder->getCreatedTime());

    // Form url values.
    $order['continueUrl'] = $form['#return_url'];
    $order['cancelUrl'] = $form['#cancel_url'];
    $order['notifyUrl'] = $payment_gateway_plugin->getNotifyUrl()->toString();
    $order['customerIp'] = Drupal::request()->getClientIp();

    // Payment data.
    $order['merchantPosId'] = OpenPayU_Configuration::getMerchantPosId();
    $order['description'] = 'Płatność ' . $billing_address->getGivenName() . ' ' . $billing_address->getFamilyName() . ' z dnia ' . $orderDate;
    $order['currencyCode'] = $payment->getAmount()->getCurrencyCode();
    $order['totalAmount'] = (int) ($payment->getAmount()->getNumber() * 100);
    $order['extOrderId'] = $drupalOrder->id();

    foreach ($drupalOrder->getItems() as $key => $item) {
      $order['products'][$key]['name'] = $item->label();
      $order['products'][$key]['unitPrice'] = (int) ($item->getUnitPrice()
        ->getNumber() * 100);
      $order['products'][$key]['quantity'] = (int) $item->getQuantity();
    }

    // Order and billing address.
    $order['buyer']['firstName'] = $billing_address->getGivenName();
    $order['buyer']['lastName'] = $billing_address->getFamilyName();
    $order['buyer']['language'] = strtolower($billing_address->getCountryCode());
    $order['buyer']['email'] = $drupalOrder->getCustomer()->getEmail();
    // $order['buyer']['phone'] = '123123123';
    $order['buyer']['delivery']['street'] = $billing_address->getAddressLine1() . ' ' . $billing_address->getAddressLine2();
    $order['buyer']['delivery']['postalCode'] = $billing_address->getPostalCode();
    $order['buyer']['delivery']['city'] = $billing_address->getLocality();
    $order['buyer']['delivery']['countryCode'] = $billing_address->getCountryCode();

    try {
      $payuOrder = OpenPayU_Order::create($order);
      $redirect_url = $payuOrder->getResponse()->redirectUri;
    }
    catch (OpenPayU_Exception_Request $exception) {
      $this->loggerFactory->get('commerce_payu')->error('Error with payu order payment: ' . $exception->getMessage());
      $this->messenger->addWarning('Contact with site administrators due to error.');
      return [];
    }

    // Merge multidimension array.
    $processOrder = [];
    foreach ($order as $key => $value) {
      self::mergeKeys($key, $processOrder, $value);
    }

    return $this->buildRedirectForm($form, $form_state, $redirect_url, $processOrder, self::REDIRECT_POST);
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitConfigurationForm() method.
  }

  /**
   * Method mergeKeys.
   *
   * @param string|int $key
   *   Your basic key.
   * @param array $arr
   *   Array with results.
   * @param mixed $data
   *   Level of nested array or saved data item.
   *
   *   Return void.
   */
  public static function mergeKeys($key, array &$arr, $data) {
    if (is_array($data)) {
      foreach ($data as $mykey => $myvalue) {
        self::mergeKeys($key . '.' . $mykey, $arr, $myvalue);
      }
    }
    else {
      $arr[$key] = $data;
    }
  }

}
