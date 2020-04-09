<?php

namespace Drupal\commerce_payu;

use Drupal\commerce_order\Entity\Order;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PayuNotificationHelper.
 *
 * @package Drupal\commerce_payu
 */
class PayuNotificationHelper {

  /**
   * Validates request signature using drupal commerce signature.
   *
   * @param \Symfony\Component\HttpFoundation\Request $onNotifyRequest
   *   The Request object.
   * @param array $onNotifyConfig
   *   The onNotify configuration array.
   *
   * @return bool
   *   Returns true if succeeds, false of not.
   */
  public function areValidSignatures(Request $onNotifyRequest, array $onNotifyConfig) {
    $headerRaw = $onNotifyRequest->headers->get('Openpayu-Signature');

    $header = \OpenPayU_Util::parseSignature($headerRaw);
    $requestSignature = $header['signature'];
    $requestHashMethod = $header['algorithm'];
    $requestContent = $onNotifyRequest->getContent();
    $drupalSignature = $onNotifyConfig['signature_key'];

    if (\OpenPayU_Util::verifySignature($requestContent, $requestSignature, $drupalSignature, $requestHashMethod)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Set transition by its name to an order.
   *
   * @param \Drupal\commerce_order\Entity\Order $order
   *   The commerce order object.
   * @param string $transitionName
   *   The commerce transition name.
   *
   * @return bool
   *   Returns true if succeeds, false of not.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setTransition(Order $order, $transitionName) {
    if ($transition = $order->getState()
      ->getWorkflow()
      ->getTransition($transitionName)) {
      $order->getState()->applyTransition($transition);
      $order->save();

      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns transition name by order type.
   *
   * @param string $payuStatus
   *   The PayU order status.
   * @param string $orderType
   *   The order type.
   *
   * @return string|null
   *   Returns transition name, or null.
   */
  public function getTransitionName($payuStatus, $orderType) {
    $transitionName = NULL;

    if ($payuStatus === 'COMPLETED') {
      if (substr($orderType, -11) === '_validation') {
        $transitionName = 'validate';
      }
      else {
        $transitionName = 'place';
      }
    }
    elseif ($payuStatus === 'CANCELED') {
      $transitionName = 'cancel';
    }
    elseif ($payuStatus === 'PENDING') {
      $transitionName = 'place';
    }

    return $transitionName;
  }

}
