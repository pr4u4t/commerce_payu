<?php

namespace Drupal\Tests\commerce_payu\Functional;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\Tests\BrowserTestBase;

/**
 * CommercePayuTest to ensure module configuration is correct.
 *
 * @group commerce_payu
 * @package Drupal\Tests\commerce_payu\Functional
 */
class CommercePayuTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'commerce',
    'commerce_payment',
    'commerce_payu',
  ];

  /**
   * The user object.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * The setUp function.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp() {
    parent::setUp();

    $this->user = $this->drupalCreateUser(
      $this->getUserPermissions()
    );

    $this->drupalLogin($this->user);
  }

  /**
   * Gets the user permissions array.
   *
   * @return array
   *   The user permissions array.
   */
  protected function getUserPermissions() {
    return [
      'view the administration theme',
      'access administration pages',
      'access commerce administration pages',
      'administer commerce_payment_gateway',
      'administer commerce_payment',
    ];
  }

  /**
   * Tests if the configuration form of gateway plugin exists.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testConfigFormExists() {
    $this->drupalGet('/admin/commerce/config/payment-gateways/add');
    $this->assertSession()->addressEquals('admin/commerce/config/payment-gateways/add');
    $this->assertSession()->pageTextContains('Payu Gateway');
  }

  /**
   * Tests if configuration form can be submitted.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testConfigWorks() {
    $this->drupalGet('/admin/commerce/config/payment-gateways/add');
    $this->assertSession()->addressEquals('admin/commerce/config/payment-gateways/add');
    $edit = [
      'label' => 'PayU',
      'plugin' => 'payu_redirect_checkout',
      'configuration[payu_redirect_checkout][display_label]' => 'PayU',
      'configuration[payu_redirect_checkout][pos_id]' => '7322432',
      'configuration[payu_redirect_checkout][signature_key]' => '12521fdacvxz32',
      'configuration[payu_redirect_checkout][client_id]' => '7322432',
      'configuration[payu_redirect_checkout][client_secret]' => 'dgfargargae342gdaswr2',
      'configuration[payu_redirect_checkout][mode]' => 'test',
      'status' => '1',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Saved the PayU payment gateway.');
    $this->assertSession()->addressEquals('admin/commerce/config/payment-gateways');

    $payment_gateway = PaymentGateway::load('payu');
    $this->assertEquals('payu', $payment_gateway->id());
    $this->assertEquals('Payu', $payment_gateway->label());
    $this->assertEquals('payu_redirect_checkout', $payment_gateway->getPluginId());

    $payment_gateway_plugin = $payment_gateway->getPlugin();
    $this->assertEquals('test', $payment_gateway_plugin->getMode());
  }

}
