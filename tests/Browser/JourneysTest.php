<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Coupon;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Models\PromotionAction;
use Tests\Browser\Support\Accessibility;
use Tests\Browser\Support\Locators;

/*
 * The storefront journeys from plan §15.4 (#44), end to end in a browser
 * against the seeded demo store. Checkout pays with the test-card gateway
 * (Tests\Browser\Support\TestCardDriver), so no payment provider is needed.
 */

/**
 * Fills the checkout's shipping address with a US address.
 *
 * @param  object  $page  The page (a Pest browser webpage).
 *
 * @return object
 */
function fillShippingAddress( object $page ): object
{
    return $page
        ->select( Locators::field( 'shipping.country_code' ), 'US' )
        ->type( Locators::field( 'shipping.first_name' ), 'Ada' )
        ->type( Locators::field( 'shipping.last_name' ), 'Lovelace' )
        ->type( Locators::field( 'shipping.address1' ), '1 Main Street' )
        ->type( Locators::field( 'shipping.city' ), 'Springfield' )
        ->select( Locators::field( 'shipping.region_code' ), 'IL' )
        ->type( Locators::field( 'shipping.postal_code' ), '62701' );
}

/**
 * From the address step to the review step: the address, the first
 * shipping option, and a test-card payment.
 *
 * @param  object  $page  The page (a Pest browser webpage).
 *
 * @return object
 */
function checkoutToReview( object $page ): object
{
    return fillShippingAddress( $page )
        ->click( '[data-checkout-address-submit]' )
        ->assertPresent( '[data-checkout-shipping-form]' )
        ->click( 'internal:label=/Standard shipping/' )
        ->click( '[data-checkout-shipping-submit]' )
        ->click( '[data-test-card-pay]' )
        ->assertPresent( '[data-checkout-place-order]' );
}

it( 'adds to the cart from the catalog', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Catalog mug' ] );

    visit( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->click( '[data-quick-add="' . $product->id . '"]' )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel', 'Catalog mug' )
        ->assertSeeIn( '[data-header-action="cart"]', '1' )
        ->assertNoJavaScriptErrors();
} );

it( 'adds a quantity to the cart from the product page', function (): void {
    makeProduct( 2400, [ 'name' => 'Product page mug', 'slug' => 'product-page-mug' ] );

    visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'product-page-mug' ] ) )
        ->type( '[data-quantity] input', '3' )
        ->click( '[data-add-to-cart]' )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel', 'Product page mug' )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel [data-cart-subtotal]', '$72.00' )
        ->assertNoJavaScriptErrors();
} );

it( 'chooses a variant and adds it to the cart', function (): void {
    makeVariableProduct( [
        'red/m'  => [ 'price' => 2500, 'stock' => 4 ],
        'blue/l' => [ 'price' => 2900, 'stock' => 4 ],
    ], [ 'slug' => 'journey-shirt' ], 'JOURNEY' );

    visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'journey-shirt' ] ) )
        ->assertButtonDisabled( '[data-add-to-cart]' )
        ->click( '[data-purchase-form] label[title="Blue"]' )
        ->assertScript( 'document.querySelector( \'[data-purchase-form] label[title="Blue"] input\' ).checked' )
        ->click( '[data-purchase-form] label >> internal:text="L"s' )
        // "$29.00" is already on the page in the price range, so wait for
        // the variant to resolve: a disabled button would make the click
        // wait for it to be enabled, with no timeout.
        ->assertScript( '! document.querySelector( "[data-add-to-cart]" ).disabled' )
        ->assertSeeIn( '[data-product-summary]', '$29.00' )
        ->click( '[data-add-to-cart]' )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel', 'Blue / L' )
        ->assertNoJavaScriptErrors();
} );

it( 'updates the cart and applies a coupon', function (): void {
    $promotion = Promotion::factory()->coupon()->create( [ 'name' => 'Journey sale' ] );
    PromotionAction::factory()->create( [ 'promotion_id' => $promotion->id, 'type' => 'percent-off-cart', 'config' => [ 'percent' => 10 ] ] );
    Coupon::factory()->create( [ 'promotion_id' => $promotion->id, 'code' => 'JOURNEY10' ] );

    makeProduct( 2000, [ 'name' => 'Coupon mug', 'slug' => 'coupon-mug' ] );

    $page = visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'coupon-mug' ] ) )
        ->click( '[data-add-to-cart]' )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel', 'Coupon mug' )
        ->navigate( route( 'artisanpack.ecommerce.storefront.cart' ) )
        ->click( '[data-ecommerce-cart] [aria-label="Increase quantity of Coupon mug"]' )
        ->assertSeeIn( '[data-ecommerce-cart] [data-cart-line-total]', '$40.00' )
        ->type( Locators::field( 'ec-cart-coupon' ), 'journey10' )
        ->click( '[data-cart-coupon] button[type="submit"]' )
        ->assertSeeIn( '[data-ecommerce-cart] [data-cart-coupon-code]', 'JOURNEY10' )
        ->assertSeeIn( '[data-cart-summary]', 'Journey sale' )
        ->assertSeeIn( '[data-cart-summary]', '$36.00' )
        ->assertNoJavaScriptErrors();

    expect( $page->text( '[data-ecommerce-cart] [data-cart-announcement]' ) )->toContain( 'Coupon applied.' );

    Accessibility::assertAccessible( $page );
} );

it( 'checks out as a guest in the multi-step layout and shows the confirmation', function (): void {
    makeProduct( 3200, [ 'name' => 'Guest lamp', 'slug' => 'guest-lamp' ] );

    $page = visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'guest-lamp' ] ) )
        ->click( '[data-add-to-cart]' )
        ->click( '[data-cart-drawer-checkout]' )
        ->assertPresent( '[data-checkout-progress]' )
        ->type( Locators::label( 'Email' ), 'guest-journey@example.test' )
        ->click( '[data-checkout-contact-submit]' )
        ->assertPresent( '[data-checkout-address-form]' );

    checkoutToReview( $page )
        ->click( '[data-checkout-place-order]' )
        ->assertPresent( '[data-order-confirmation]' )
        ->assertSee( 'Guest lamp' )
        ->assertPresent( '[data-order-guest-hints]' )
        ->assertNoJavaScriptErrors();

    $order = Order::query()->where( 'email', 'guest-journey@example.test' )->sole();

    expect( $order->customer_id )->toBeNull()
        ->and( $order->payment_status )->toBe( 'paid' )
        ->and( $page->text( '[data-order-number]' ) )->toBe( $order->order_number );
} );

it( 'checks out signed in on the single-page layout', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.layout', 'single_page' );

    [ $user, $customer ] = shopper();

    makeProduct( 4500, [ 'name' => 'Member blanket', 'slug' => 'member-blanket' ] );

    $page = visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'member-blanket' ] ) )
        ->click( '[data-add-to-cart]' )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel', 'Member blanket' )
        ->navigate( route( 'artisanpack.ecommerce.storefront.checkout' ) )
        ->assertNotPresent( '[data-checkout-progress]' )
        ->assertPresent( '[data-checkout-step="review"]' );

    if ( ! str_contains( $page->content(), 'data-checkout-address-form' ) ) {
        $page->click( '[data-checkout-contact-submit]' );
    }

    checkoutToReview( $page )
        ->click( '[data-checkout-place-order]' )
        ->assertPresent( '[data-order-confirmation]' )
        ->assertSee( 'Member blanket' )
        ->assertPresent( '[data-order-account-link]' )
        ->assertNotPresent( '[data-order-guest-hints]' )
        ->assertNoJavaScriptErrors();

    expect( Order::query()->where( 'customer_id', $customer->id )->sole()->email )->toBe( $user->email );
} );

it( 'finds a guest order with the email and order number', function (): void {
    placedOrder( [ 'order_number' => 'LOOKUP42', 'email' => 'finder@example.test' ], null, [ makeProduct( 2000, [ 'name' => 'Lookup kettle' ] ) ] );

    visit( route( 'artisanpack.ecommerce.storefront.lookup' ) )
        ->type( Locators::field( 'email' ), 'finder@example.test' )
        ->type( Locators::field( 'orderNumber' ), 'LOOKUP42' )
        ->click( '[data-order-lookup-form] button[type="submit"]' )
        ->assertPathBeginsWith( '/shop/order/' )
        ->assertSee( 'LOOKUP42' )
        ->assertSee( 'Lookup kettle' )
        ->assertNoJavaScriptErrors();
} );

it( 'tells a shopper when no order matches, without saying which part was wrong', function (): void {
    placedOrder( [ 'order_number' => 'LOOKUP43', 'email' => 'finder@example.test' ] );

    visit( route( 'artisanpack.ecommerce.storefront.lookup' ) )
        ->type( Locators::field( 'email' ), 'someone-else@example.test' )
        ->type( Locators::field( 'orderNumber' ), 'LOOKUP43' )
        ->click( '[data-order-lookup-form] button[type="submit"]' )
        ->assertPresent( '[data-order-lookup-failure]' )
        ->assertPathIs( '/shop/order-lookup' );
} );

it( 'opens an order from the account order history', function (): void {
    [ , $customer ] = shopper();

    $order = placedOrder( [ 'order_number' => 'ACCOUNT7' ], $customer, [ makeProduct( 2000, [ 'name' => 'Account teapot' ] ) ] );

    visit( route( 'artisanpack.ecommerce.account.orders.index' ) )
        ->click( '[data-account-order-link="' . $order->id . '"]' )
        ->assertRoute( 'artisanpack.ecommerce.account.orders.show', [ 'order' => $order->id ] )
        ->assertSee( 'ACCOUNT7' )
        ->assertSee( 'Account teapot' )
        ->assertSee( '$45.00' )
        ->assertNoJavaScriptErrors();
} );
