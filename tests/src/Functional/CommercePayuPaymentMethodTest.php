<?php

namespace Drupal\Tests\commerce_payu\Functional;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_store\Entity\Store;
use Drupal\Tests\BrowserTestBase;

/**
 * PayU Payment Method test.
 *
 * @group commerce_payu
 * @package Drupal\Tests\commerce_payu\Functional
 */
class CommercePayuPaymentMethodTest extends BrowserTestBase {
  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'commerce',
    'commerce_price',
    'commerce_store',
    'commerce_product',
    'commerce_order',
    'commerce_checkout',
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
   * The order object.
   *
   * @var \Drupal\commerce_order\Entity\Order
   */
  private $order;

  /**
   * The setUp function.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp() {
    parent::setUp();

    $this->user = $this->drupalCreateUser(
      $this->getUserPermissions()
    );

    $this->drupalLogin($this->user);

    // $this->drupalPlaceBlock('commerce_cart');
    // $this->drupalPlaceBlock('commerce_checkout_progress');

    // Order setup.
    /** @var \Drupal\commerce_product\Entity\Store $store */
    $store = Store::create([
      'name' => $this->randomString(),
      'mail' => 'test@test.test',
      'billing_countries' => [
        'PL',
      ],
    ]);

    /** @var \Drupal\commerce_product\Entity\ProductVariation $variation */
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => 9.99,
        'currency_code' => 'PLN',
      ],
    ]);

    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = Product::create([
      'type' => 'default',
      'title' => $this->randomString(),
      'variations' => [$variation],
      'stores' => [$store],
    ]);

    $this->drupalGet($product->toUrl());
    $this->submitForm([], 'Add to cart');

    $this->assertSession()->pageTextContains('1 item');
    $cartLink = $this->getSession()->getPage()->findLink('your cart');
    $cartLink->click();

    $this->submitForm([], 'Checkout');
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
      'administer commerce_checkout_flow',
    ];
  }

  /**
   * Checking if user can choose payment method.
   */
  public function testChoosePaymentMethod() {
    // Payment.
    $this->assertSession()->pageTextContains('PayU');
    $this->getSession()->getPage()->findField('PayU')->click();
  }

  /**
   * Checking if inputs are fillable.
   */
  public function testFormIsFillable() {
    // Contact informations.
    $edit = [
      'contact_information[email]' => 'test@test.test',
      'contact_information[email_confirm]' => 'test@test.test',
      'billing_information[profile][address][0][address][given_name]' => $this->randomString(),
      'billing_information[profile][address][0][address][family_name]' => $this->randomString(),
      'billing_information[profile][address][0][address][organization]' => $this->randomString(),
      'billing_information[profile][address][0][address][address_line1]' => $this->randomString(),
      'billing_information[profile][address][0][address][postal_code]' => '94043',
      'billing_information[profile][address][0][address][locality]' => 'Mountain View',
      'billing_information[profile][address][0][address][administrative_area]' => 'CA',
    ];

    $this->submitForm($edit, 'Continue to review');
  }

  /**
   * Checking if form can be submitted.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testFormSubmit() {
    // Review.
    $this->assertSession()->pageTextContains('Contact information');
    $this->assertSession()->pageTextContains('Billing information');
    $this->assertSession()->pageTextContains('Order Summary');

    $this->submitForm([], 'Complete checkout');
  }

}
