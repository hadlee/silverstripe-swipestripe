<?php
/**
 * 
 * @author frankmullenger
 * 
 * Summary of tests:
 * -----------------
 * checkout published product
 * unpublish product after it is in the cart cannot checkout
 * delete product after it is in the cart cannot checkout
 * add variation then disable the variation, cannot checkout
 * add variation to cart then delete variation cannot checkout
 * submit checkout without products in cart
 * checkout with product that has attributes, without a variation set
 * submit checkout without necessary details
 * submit checkout without specifying payment gateway
 * 
 * 
 * TODO
 * ----
 * when last item deleted from the cart, remove order modifiers also
 * add shipping options to checkout
 * submit checkout with shipping option that does not match shipping country (validate)
 * process payment
 * send receipt
 * checkout addresses correct
 * 
 * Product Category
 * delete product, does not appear on website
 * delete product, staging versions all up to date and still exist
 * 
 * TEST:
 * Order
 * Order addresses
 * Order modifiers
 * Shipping
 * Product Categories
 * Account page
 * Product 
 * Checkout
 * Payment
 * 
 */
class CheckoutTest extends FunctionalTest {
  
	static $fixture_file = 'stripeycart/tests/CartTest.yml';
	static $disable_themes = false;
	static $use_draft_site = true;
	
  function setUp() {
		parent::setUp();

		//Check that payment module is installed
		$this->assertTrue(class_exists('Payment'), 'Payment module is installed.');
		$this->assertTrue(class_exists('ChequePayment'), 'Cheque Payment module is installed.');
		
		//Force payment method to be basic cheque payment
		Payment::set_supported_methods(array(
      'ChequePayment' => 'Cheque Or Pay On Site'
    ));
	}
	
	/**
	 * Log current member out by clearing session
	 */
	function logOut() {
	  $this->session()->clear('loggedInAs');
	}
	
	/**
	 * Helper to get data from a form.
	 * 
	 * @param String $formID
	 * @return Array
	 */
	function getFormData($formID) {
	  $page = $this->mainSession->lastPage();
	  $data = array();
	  
	  if ($page) {
			$form = $page->getFormById($formID);
			if (!$form) user_error("Function getFormData() failed to find the form {$formID}", E_USER_ERROR);

  	  foreach ($form->_widgets as $widget) {
  
  	    $fieldName = $widget->getName();
  	    $fieldValue = $widget->getValue();
  	    
  	    $data[$fieldName] = $fieldValue;
  	  }
	  }
	  else user_error("Function getFormData() called when there is no form loaded.  Visit the page with the form first", E_USER_ERROR);
	  
	  return $data;
	}

	/**
	 * Create product and check basic attributes
	 */
  function testCheckoutWithPublishedProduct() {

		$productA = $this->objFromFixture('Product', 'productA');

	  $this->loginAs('admin');
	  $productA->doPublish();
	  $this->logOut();
	  
	  $this->assertTrue($productA->isPublished());
	  
	  $this->loginAs('buyer');
	  $buyer = $this->objFromFixture('Member', 'buyer');

	  $this->get(Director::makeRelative($productA->Link())); 
	  $this->submitForm('Form_AddToCartForm', null, array(
	    'Quantity' => 1
	  ));

	  $order = CartControllerExtension::get_current_order();
	  $items = $order->Items();
	  
	  $this->assertEquals(1, $items->Count());
	  $this->assertEquals($productA->ID, $items->First()->Object()->ID);
	  
	  $orders = $buyer->Orders();
	  $this->assertEquals(1, $orders->Count());
	  
	  $checkoutPage = DataObject::get_one('CheckoutPage');
	  $this->get(Director::makeRelative($checkoutPage->Link()));

	  $this->submitForm('CheckoutForm_OrderForm', null, array(
	    'Notes' => 'New order for test buyer.'
	  ));
	  
	  $orders = $buyer->Orders();
	  $this->assertEquals(2, $orders->Count());
	}
	
	/**
	 * Try to checkout an unpublished product
	 */
	function testCheckoutWithUnpublishedProduct() {
	  
	  $productA = $this->objFromFixture('Product', 'productA');

	  $this->loginAs('admin');
	  $productA->doPublish();
	  $this->logOut();
	  
	  $this->assertTrue($productA->isPublished());

	  //Add product to cart, buyer has one Order existing from fixture
	  $buyer = $this->objFromFixture('Member', 'buyer');
	  $this->assertEquals(1, $buyer->Orders()->Count());
	  
	  $this->loginAs('buyer');

	  $this->get(Director::makeRelative($productA->Link())); 
	  $this->submitForm('Form_AddToCartForm', null, array(
	    'Quantity' => 1
	  ));

	  $order = CartControllerExtension::get_current_order();
	  $items = $order->Items();
	  
	  $this->assertEquals(1, $items->Count());
	  $this->assertEquals($productA->ID, $items->First()->Object()->ID);
	  $this->logOut();
	  
	  //Unpublish the product thats in the cart
	  $this->loginAs('admin');
	  $productA->doUnpublish();
	  $this->logOut();
	  
	  $this->assertEquals(false, $productA->isPublished());
	  $this->assertEquals(false, $order->isValid());
	  
	  //Log in as buyer again and try to checkout
	  $this->loginAs('buyer');
	  $checkoutPage = DataObject::get_one('CheckoutPage');
	  $this->get(Director::makeRelative($checkoutPage->Link()));

	  $this->submitForm('CheckoutForm_OrderForm', null, array(
	    'Notes' => 'This order should fail.'
	  ));
	  
	  $orders = $buyer->Orders();
	  $this->assertEquals(1, $orders->Count());
	}
	
	/**
	 * Try to checkout a deleted product
	 */
	function testCheckoutWithDeletedProduct() {
	  
	  $productA = $this->objFromFixture('Product', 'productA');

	  $this->loginAs('admin');
	  $productA->doPublish();
	  $this->logOut();
	  
	  $this->assertTrue($productA->isPublished());

	  //Add product to cart, buyer has one Order existing from fixture
	  $buyer = $this->objFromFixture('Member', 'buyer');
	  $this->assertEquals(1, $buyer->Orders()->Count());
	  
	  $this->loginAs('buyer');

	  $this->get(Director::makeRelative($productA->Link())); 
	  $this->submitForm('Form_AddToCartForm', null, array(
	    'Quantity' => 1
	  ));

	  $order = CartControllerExtension::get_current_order();
	  $items = $order->Items();
	  
	  $this->assertEquals(1, $items->Count());
	  $this->assertEquals($productA->ID, $items->First()->Object()->ID);
	  $this->logOut();
	  
	  //Delete the product thats in the cart
	  $this->loginAs('admin');
	  $productA->delete();
	  $this->logOut();
	  
	  $this->assertEquals(false, $productA->isInDB());
	  $this->assertEquals(false, $order->isValid());
	  
	  //Log in as buyer again and try to checkout
	  $this->loginAs('buyer');
	  $checkoutPage = DataObject::get_one('CheckoutPage');
	  $this->get(Director::makeRelative($checkoutPage->Link()));

	  $this->submitForm('CheckoutForm_OrderForm', null, array(
	    'Notes' => 'This order should fail.'
	  ));
	  
	  $orders = $buyer->Orders();
	  $this->assertEquals(1, $orders->Count());
	}
	
	/**
	 * Try to checkout a disabled variation
	 */
	function testCheckoutWithDisabledVariation() {
	  
	  $shortsA = $this->objFromFixture('Product', 'shortsA');

	  $this->loginAs('admin');
	  $shortsA->doPublish();
	  $this->logOut();
	  
	  $this->assertTrue($shortsA->isPublished());

	  //Add product to cart, buyer has one Order existing from fixture
	  $buyer = $this->objFromFixture('Member', 'buyer');
	  $this->assertEquals(1, $buyer->Orders()->Count());
	  
	  $this->loginAs('buyer');

	  $this->get(Director::makeRelative($shortsA->Link())); 
	  
	  $shortsAVariation = $this->objFromFixture('Variation', 'shortsSmallRedCotton');
	  $this->assertEquals('Enabled', $shortsAVariation->Status);
	  
	  $this->submitForm('Form_AddToCartForm', null, array(
	    'Quantity' => 1,
	    'Options[1]' => $shortsAVariation->getAttributeOption(1)->ID,  //Small
	    'Options[2]' => $shortsAVariation->getAttributeOption(2)->ID, //Red
	    'Options[3]' => $shortsAVariation->getAttributeOption(3)->ID, //Cotton
	  ));

	  $order = CartControllerExtension::get_current_order();
	  $items = $order->Items();
	  
	  $this->assertEquals(1, $items->Count());
	  $this->assertEquals($shortsA->ID, $items->First()->Object()->ID);
	  $this->logOut();
	  
	  $this->logInAs('admin');
	  $shortsAVariation->Status = 'Disabled';
	  $shortsAVariation->write();
	  $this->logOut();

	  $this->assertEquals('Disabled', $shortsAVariation->Status);
	  $this->assertEquals(false, $order->isValid());
	  
	  //Log in as buyer again and try to checkout
	  $this->loginAs('buyer');
	  $checkoutPage = DataObject::get_one('CheckoutPage');
	  $this->get(Director::makeRelative($checkoutPage->Link()));

	  $this->submitForm('CheckoutForm_OrderForm', null, array(
	    'Notes' => 'This order should fail.'
	  ));
	  
	  $orders = $buyer->Orders();
	  $this->assertEquals(1, $orders->Count());
	}
	
	/**
	 * Try to checkout a deleted variation
	 */
	function testCheckoutWithDeletedVariation() {
	  
	  $shortsA = $this->objFromFixture('Product', 'shortsA');

	  $this->loginAs('admin');
	  $shortsA->doPublish();
	  $this->logOut();
	  
	  $this->assertTrue($shortsA->isPublished());

	  //Add product to cart, buyer has one Order existing from fixture
	  $buyer = $this->objFromFixture('Member', 'buyer');
	  $this->assertEquals(1, $buyer->Orders()->Count());
	  
	  $this->loginAs('buyer');

	  $this->get(Director::makeRelative($shortsA->Link())); 
	  
	  $shortsAVariation = $this->objFromFixture('Variation', 'shortsSmallRedCotton');
	  $this->assertEquals('Enabled', $shortsAVariation->Status);
	  
	  $this->submitForm('Form_AddToCartForm', null, array(
	    'Quantity' => 1,
	    'Options[1]' => $shortsAVariation->getAttributeOption(1)->ID,  //Small
	    'Options[2]' => $shortsAVariation->getAttributeOption(2)->ID, //Red
	    'Options[3]' => $shortsAVariation->getAttributeOption(3)->ID, //Cotton
	  ));

	  $order = CartControllerExtension::get_current_order();
	  $items = $order->Items();
	  
	  $this->assertEquals(1, $items->Count());
	  $this->assertEquals($shortsA->ID, $items->First()->Object()->ID);
	  $this->logOut();
	  
	  $this->logInAs('admin');
	  $shortsAVariation->delete();
	  $this->logOut();

	  $this->assertEquals(false, $shortsAVariation->isInDB());
	  $this->assertEquals(false, $order->isValid());
	  
	  //Log in as buyer again and try to checkout
	  $this->loginAs('buyer');
	  $checkoutPage = DataObject::get_one('CheckoutPage');
	  $this->get(Director::makeRelative($checkoutPage->Link()));

	  $this->submitForm('CheckoutForm_OrderForm', null, array(
	    'Notes' => 'This order should fail.'
	  ));
	  
	  $orders = $buyer->Orders();
	  $this->assertEquals(1, $orders->Count());
	}
	
	/**
	 * Try to checkout without products added to the order
	 */
	function testCheckoutWithoutProducts() {
	  
	  //Add product to cart, buyer has one Order existing from fixture
	  $buyer = $this->objFromFixture('Member', 'buyer');
	  $this->assertEquals(1, $buyer->Orders()->Count());
	  
	  //Log in as buyer again and try to checkout
	  $this->loginAs('buyer');
	  
	  $order = CartControllerExtension::get_current_order();
	  $items = $order->Items();
	  $this->assertEquals(0, $items->Count());
	  
	  $this->assertEquals(false, $order->isValid());
	  
	  $checkoutPage = DataObject::get_one('CheckoutPage');
	  $this->get(Director::makeRelative($checkoutPage->Link()));

	  $this->submitForm('CheckoutForm_OrderForm', null, array(
	    'Notes' => 'This order should fail.'
	  ));
	  
	  $orders = $buyer->Orders();
	  $this->assertEquals(1, $orders->Count());
	}
	
	/**
	 * Try to checkout with a product that requires a variation, without a variation in the cart
	 */
	function testCheckoutWithoutRequiredVariation() { 
	  $shortsA = $this->objFromFixture('Product', 'shortsA');

	  $this->loginAs('admin');
	  $shortsA->doPublish();
	  $this->logOut();
	  
	  $this->assertTrue($shortsA->isPublished());
	  $this->assertTrue($shortsA->requiresVariation());

	  //Add product to cart, buyer has one Order existing from fixture
	  $buyer = $this->objFromFixture('Member', 'buyer');
	  $this->assertEquals(1, $buyer->Orders()->Count());
	  
	  $this->loginAs('buyer');

	  $this->get(Director::makeRelative($shortsA->Link())); 
	  
	  $shortsAVariation = $this->objFromFixture('Variation', 'shortsSmallRedCotton');
	  $this->assertEquals('Enabled', $shortsAVariation->Status);
	  
	  $this->submitForm('Form_AddToCartForm', null, array(
	    'Quantity' => 1,
	    'Options[1]' => $shortsAVariation->getAttributeOption(1)->ID,  //Small
	    'Options[2]' => $shortsAVariation->getAttributeOption(2)->ID, //Red
	    'Options[3]' => $shortsAVariation->getAttributeOption(3)->ID, //Cotton
	  ));

	  $order = CartControllerExtension::get_current_order();
	  $items = $order->Items();
	  $variation = $order->Items()->First()->Variation();
	  
	  $this->assertEquals(1, $items->Count());
	  $this->assertEquals($shortsA->ID, $items->First()->Object()->ID);
	  $this->logOut();
	  
	  $this->logInAs('admin');
	  $variation->delete();
	  $this->logOut();
	  
	  $this->assertEquals(false, $variation->isInDB());
	  $this->assertEquals(false, $order->isValid());
	  
	  //Log in as buyer again and try to checkout
	  $this->loginAs('buyer');
	  $checkoutPage = DataObject::get_one('CheckoutPage');
	  $this->get(Director::makeRelative($checkoutPage->Link()));

	  $this->submitForm('CheckoutForm_OrderForm', null, array(
	    'Notes' => 'This order should fail.'
	  ));
	  
	  $orders = $buyer->Orders();
	  $this->assertEquals(1, $orders->Count());
	}
	
	/**
	 * Try to submit the checkout form without some required fields
	 * Assumes that billing FirstName is always required
	 */
	function testCheckoutWithoutRequiredFields() {
	  $shortsA = $this->objFromFixture('Product', 'shortsA');
	  $shortsAVariation = $this->objFromFixture('Variation', 'shortsSmallRedCotton');
	  
	  $this->loginAs('admin');
	  $shortsA->doPublish();
	  $this->logOut();
	  
	  $this->assertTrue($shortsA->isPublished());
	  
	  //Add product to cart, buyer has one Order existing from fixture
	  $buyer = $this->objFromFixture('Member', 'buyer');
	  $this->assertEquals(1, $buyer->Orders()->Count());
	  
	  $this->loginAs('buyer');

	  $this->get(Director::makeRelative($shortsA->Link())); 
	  $this->submitForm('Form_AddToCartForm', null, array(
	    'Quantity' => 1,
	    'Options[1]' => $shortsAVariation->getAttributeOption(1)->ID,  //Small
	    'Options[2]' => $shortsAVariation->getAttributeOption(2)->ID, //Red
	    'Options[3]' => $shortsAVariation->getAttributeOption(3)->ID, //Cotton
	  ));

	  $order = CartControllerExtension::get_current_order();
	  $items = $order->Items();
	  $variation = $order->Items()->First()->Variation();
	  
	  $this->assertEquals(1, $items->Count());
	  $this->assertEquals($shortsA->ID, $items->First()->Object()->ID);
	  $this->logOut();

	  $checkoutPage = DataObject::get_one('CheckoutPage');
	  $this->get(Director::makeRelative($checkoutPage->Link()));

	  $this->submitForm('CheckoutForm_OrderForm', null, array(
	    'Billing[FirstName]' => ''
	  ));
	  
	  $orders = $buyer->Orders();
	  $this->assertEquals(1, $orders->Count());
	}
	
	/**
	 * Try checking out an order without specifying a payment gateway
	 */
	function testCheckoutWithoutPaymentGateway() {

    $productA = $this->objFromFixture('Product', 'productA');

	  $this->loginAs('admin');
	  $productA->doPublish();
	  $this->logOut();
	  
	  $buyer = $this->objFromFixture('Member', 'buyer');
	  $this->assertEquals(1, $buyer->Orders()->Count());
	  
	  $this->loginAs('buyer');

	  $productALink = $productA->Link();
	  $this->get(Director::makeRelative($productALink)); 
	  $this->submitForm('Form_AddToCartForm', null, array(
	    'Quantity' => 1
	  ));

	  $order = CartControllerExtension::get_current_order();
	  $items = $order->Items();
	  $this->assertEquals(1, $items->Count());
	  
	  $checkoutPage = DataObject::get_one('CheckoutPage');
	  $this->get(Director::makeRelative($checkoutPage->Link()));
	  
	  //Submit the form without restrictions on what can be POST'd
	  $data = $this->getFormData('CheckoutForm_OrderForm');
    $data['PaymentMethod'] = '';

	  $this->post(
	    Director::absoluteURL('/checkout/OrderForm'),
	    $data
	  );

	  $this->assertEquals(1, $buyer->Orders()->Count());
	}
	
}