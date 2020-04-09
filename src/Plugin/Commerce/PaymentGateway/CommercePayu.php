<?php

namespace Drupal\commerce_payu\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payu\PayuNotificationHelper;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use OpenPayU_Configuration;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CommercePayu.
 *
 * @package Drupal\commerce_payu\Plugin\Commerce\PaymentGateway
 * @CommercePaymentGateway(
 *   id="payu_redirect_checkout",
 *   label=@Translation("PayU gateway"),
 *   display_label=@Translation("PayU"),
 *   forms={
 *     "offsite-payment" = "Drupal\commerce_payu\PluginForm\PayuPaymentForm",
 *   }
 * )
 */
class CommercePayu extends OffsitePaymentGatewayBase implements OffsitePaymentGatewayInterface {

  /**
   * The notification helper.
   *
   * @var \Drupal\commerce_payu\PayuNotificationHelper
   */
  private $notificationHelper;

  /**
   * CommercePayu constructor.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time entity.
   * @param \Drupal\commerce_payu\PayuNotificationHelper $notification_helper
   *   The notification helper.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, PayuNotificationHelper $notification_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);
    $this->notificationHelper = $notification_helper;
    $config = $this->getConfiguration();
    $env = 'sandbox';
    if ($config['mode'] == 'live') {
      $env = 'secure';
    }

    OpenPayU_Configuration::setEnvironment($env);
    OpenPayU_Configuration::setMerchantPosId($config['pos_id']);
    OpenPayU_Configuration::setSignatureKey($config['signature_key']);
    OpenPayU_Configuration::setOauthClientId($config['client_id']);
    OpenPayU_Configuration::setOauthClientSecret($config['client_secret']);
  }

  /**
   * CommercePayu create method - handles service creation.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container injecting services.
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugun definition.
   *
   * @return \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase|\Drupal\Core\Plugin\ContainerFactoryPluginInterface|static
   *   Returns OffsitePaymentGatewayBase, Container or static.
   *
   * @throws \OpenPayU_Exception_Configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('commerce_payu.notification_helper')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration() {
    $config = [
      'environment' => 'sandbox',
      'pos_id' => '',
      'signature_key' => '',
      'client_id' => '',
      'client_secret' => '',
    ];
    return $config + parent::defaultConfiguration();
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['pos_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('POS ID'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['pos_id'],
    ];

    $form['signature_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Signature key'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['signature_key'],
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['client_id'],
    ];

    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client secret'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['client_secret'],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['pos_id'] = $values['pos_id'];
    $this->configuration['signature_key'] = $values['signature_key'];
    $this->configuration['client_id'] = $values['client_id'];
    $this->configuration['client_secret'] = $values['client_secret'];
  }

  /**
   * The wakeup magic method override.
   *
   * @throws \OpenPayU_Exception_Configuration
   */
  public function __wakeup() {
    $config = $this->getConfiguration();
    $env = 'sandbox';
    if ($config['mode'] == 'live') {
      $env = 'secure';
    }

    OpenPayU_Configuration::setEnvironment($env);
    OpenPayU_Configuration::setMerchantPosId($config['pos_id']);
    OpenPayU_Configuration::setSignatureKey($config['signature_key']);
    OpenPayU_Configuration::setOauthClientId($config['client_id']);
    OpenPayU_Configuration::setOauthClientSecret($config['client_secret']);
  }

  /**
   * Handles notifications sent by PayU.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response|void|null
   *   Returns Response object, null or nothing.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \OpenPayU_Exception
   */
  public function onNotify(Request $request) {
    $config = $this->getConfiguration();

    $payuOrder = json_decode($request->getContent())->order;
    $payuOrderExtId = $payuOrder->extOrderId;

    $storage = $this->entityTypeManager->getStorage('commerce_order');
    $drupalOrders = $storage->loadByProperties(['order_id' => $payuOrderExtId]);
    /** @var \Drupal\commerce_order\Entity\Order $drupalOrder */
    $drupalOrder = $drupalOrders[] = array_pop($drupalOrders);

    if ($drupalOrder == NULL) {
      return new Response('Waiting for creation of drupal order', 500);
    }

    if (!$this->notificationHelper->areValidSignatures($request, $config)) {
      \OpenPayU_Order::cancel($payuOrder->orderId);
      $this->notificationHelper->setTransition(
        $drupalOrder,
        $this->notificationHelper->getTransitionName(
          'CANCELED', $drupalOrder->getState()->getWorkflow()->getId()
        )
      );
      throw new PaymentGatewayException('ERROR - Unvalid PayU Request');
    }

    $payuStatus = $payuOrder->status;
    $transitionName = $this->notificationHelper->getTransitionName($payuStatus, $drupalOrder->getState()->getWorkflow()->getId());

    if (!$this->notificationHelper->setTransition($drupalOrder, $transitionName)) {
      return new Response('ERROR - Transition name not in workflow', 500);
    }

    $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');
    $payments = $paymentStorage->loadByProperties(['order_id' => $payuOrderExtId]);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $payment */
    $payment = $payments[] = array_pop($payments);

    if ($payment == NULL) {
      $paymentStorage->create([
        'state' => $drupalOrder->getState()->getString(),
        'amount' => $drupalOrder->getTotalPrice(),
        'payment_gateway' => $this->parentEntity->id(),
        'order_id' => $payuOrderExtId,
        'remote_id' => $payuOrder->orderId,
        'remote_state' => $payuStatus,
        'completed' => $drupalOrder->getState()->getString() === 'COMPLETED' ? TRUE : FALSE,
      ])->save();
    }
    else {
      $payment
        ->set('state', $drupalOrder->getState()->getString())
        ->set('remote_id', $payuOrder->orderId)
        ->set('remote_state', $payuStatus)
        ->set('completed', $drupalOrder->getState()->getString() === 'COMPLETED' ? TRUE : FALSE)
        ->save();
    }

    return new Response('Notification OK');
  }

}
