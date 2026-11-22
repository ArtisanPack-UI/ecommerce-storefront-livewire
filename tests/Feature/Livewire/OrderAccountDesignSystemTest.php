<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderNote;
use ArtisanPackUI\Ecommerce\Models\Shipment;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Dashboard;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\OrderShow;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Order\Confirmation;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Order\Lookup;
use Livewire\Livewire;

/**
 * The unpaid order screen, order confirmation, guest order lookup, order
 * detail and account dashboard on the design system (#142–#146).
 */

/** Tailwind and DaisyUI classes these screens used before the rebuild. */
const OLD_ORDER_SCREEN_CLASSES = [
    'flex', 'flex-col', 'flex-wrap', 'grid', 'gap-2', 'gap-3', 'gap-4', 'gap-6', 'gap-8', 'text-sm', 'text-lg', 'text-xl', 'text-2xl', 'text-3xl',
    'font-semibold', 'font-bold', 'rounded-box', 'border', 'border-primary', 'border-base-content/10', 'text-base-content/70', 'p-4', 'p-5', 'p-6',
    'link', 'link-primary', 'bg-success/10', 'text-success', 'divide-y', 'md:grid-cols-3', 'sm:grid-cols-2', 'max-w-xl', 'lg:grid-cols-[minmax(0,1fr)_22rem]',
];

it( 'shows an unpaid order as one card: the clock tile, the title, the problem, the meta list and the driver (#142)', function (): void {
    checkoutShipping( [ 'Standard' => 500 ] );
    $gateway = checkoutGateway();
    checkoutCart( [ makeProduct( 2000, [ 'name' => 'Mug' ] ) ] );
    payAtCheckoutThen( $gateway, PaymentSession::STATUS_REQUIRES_ACTION );

    $component = payAtCheckout( checkoutAtPaymentStep( $gateway ), $gateway );
    $order     = Order::query()->sole();
    $html      = $component->html();

    expect( $html )->toContain( 'class="ec-placement" data-checkout-placement="' . $order->id . '"' )
        ->toContain( 'apui-icon-tile' )
        ->toContain( 'data-color="warning"' )
        ->toMatch( '#<h2\s+id="ec-checkout-placement-heading"#' )
        ->toContain( 'Your order is saved, and we&#039;ll hold it while you finish paying.' )
        ->toContain( 'data-checkout-payment-problem' )
        ->toContain( 'data-checkout-placement-items>1</dd>' )
        ->toContain( 'data-checkout-placement-shipping' )
        ->toContain( 'Standard' )
        ->not->toContain( 'class="ec-checkout__title"' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_ORDER_SCREEN_CLASSES );

    Livewire::test( Index::class, [ 'showTitle' => true ] )
        ->assertSeeHtml( '<h1' )
        ->assertSee( 'Finish paying for order ' . $order->order_number )
        ->assertDontSeeHtml( 'class="ec-checkout__title"' );
} );

it( 'thanks the shopper with a success tile, a meta strip and the guest hints (#143)', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.auth.register_route', 'register' );
    Illuminate\Support\Facades\Route::get( '/register', static fn (): string => 'register' )->name( 'register' );
    $order = placedOrder( [ 'order_number' => 'ORD-2001' ] );

    $html = Livewire::test( Confirmation::class, [ 'order' => $order->id, 'token' => OrderViewToken::for( $order ), 'showTitle' => true ] )->html();

    expect( $html )->toContain( 'data-color="success"' )
        ->toMatch( '#<h1 class="ec-confirmation__title">Thank you for your order</h1>#' )
        ->toContain( 'data-order-confirmation-strip' )
        ->toContain( 'class="ec-confirmation__number" data-order-number><bdi>ORD-2001</bdi>' )
        ->toContain( 'data-order-hint="register"' )
        ->toContain( 'data-order-hint="lookup"' )
        ->toContain( 'data-order-continue' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_ORDER_SCREEN_CLASSES );
} );

it( 'heads the confirmation page with "Thank you for your order" as its only h1', function (): void {
    $order = placedOrder();

    $html = $this->get( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id, 'token' => OrderViewToken::for( $order ) ] ) )
        ->assertOk()
        ->getContent();

    expect( substr_count( $html, '<h1' ) )->toBe( 1 )
        ->and( $html )->toContain( 'Thank you for your order</h1>' );
} );

it( 'shows the lookup form in a card, with the title, and says what to check when nothing matched (#144)', function (): void {
    $component = Livewire::test( Lookup::class, [ 'showTitle' => true ] );

    expect( $component->html() )->toContain( '<h1 class="ec-lookup__title">Find your order</h1>' )
        ->toContain( 'class="ec-lookup__form"' )
        ->toContain( 'data-size="lg"' )
        ->not->toContain( 'data-order-lookup-failure' )
        ->and( classTokens( $component->html() ) )->not->toContain( ...OLD_ORDER_SCREEN_CLASSES );

    $component->set( 'email', 'ada@example.test' )
        ->set( 'orderNumber', 'NOPE-1' )
        ->call( 'find' )
        ->assertSet( 'notFound', true )
        ->assertSeeHtml( 'data-order-lookup-failure' )
        ->assertSee( 'Check the order number and use the same email as at checkout.' )
        ->assertSeeHtml( 'aria-invalid="true"' );
} );

it( 'shows a shipment as steps, with the tracking panel, and the store\'s messages as a timeline (#145)', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [ 'order_number' => 'ORD-3001' ], $customer );

    Shipment::factory()->create( [ 'order_id' => $order->id, 'carrier' => 'USPS', 'tracking_number' => '9400', 'tracking_url' => 'https://usps.test/9400', 'status' => Shipment::STATUS_IN_TRANSIT, 'shipped_at' => now() ] );
    OrderNote::factory()->create( [ 'order_id' => $order->id, 'body' => 'Packed with care.', 'is_customer_visible' => true ] );

    $html = Livewire::test( OrderShow::class, [ 'order' => $order->id, 'showTitle' => true ] )->html();

    expect( $html )->toContain( 'Order ORD-3001</h1>' )
        ->toContain( 'data-account-order-back' )
        ->toContain( 'data-order-shipment-steps' )
        ->toContain( 'data-order-shipment-step="complete"' )
        ->toContain( 'data-order-shipment-step="current"' )
        ->toContain( 'data-order-shipment-step="upcoming"' )
        ->toContain( 'class="ec-order-shipment__panel"' )
        ->toContain( 'data-scope="timeline"' )
        ->toContain( 'Packed with care.' )
        ->toContain( 'data-account-order-buy-again' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_ORDER_SCREEN_CLASSES );
} );

it( 'marks a delivery problem on its step, and finishes every step once delivered', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [], $customer );
    $shipment       = Shipment::factory()->create( [ 'order_id' => $order->id, 'status' => Shipment::STATUS_EXCEPTION, 'shipped_at' => now() ] );

    Livewire::test( OrderShow::class, [ 'order' => $order->id ] )
        ->assertSeeHtml( 'data-order-shipment-step="error"' )
        ->assertSee( 'Delivery problem' );

    $shipment->forceFill( [ 'status' => Shipment::STATUS_DELIVERED, 'delivered_at' => now() ] )->save();

    $html = Livewire::test( OrderShow::class, [ 'order' => $order->id ] )->html();

    expect( substr_count( $html, 'data-order-shipment-step="complete"' ) )->toBe( 4 );
} );

it( 'shows a guest\'s order under an "Order lookup" breadcrumb, with no account links', function (): void {
    $order = placedOrder( [ 'email' => 'ada@example.test', 'order_number' => 'ORD-4001' ] );

    $html = $this->get( route( 'artisanpack.ecommerce.storefront.order-view', [ 'token' => OrderViewToken::for( $order ) ] ) )
        ->assertOk()
        ->getContent();

    expect( $html )->toContain( 'data-order-breadcrumbs' )
        ->toContain( 'Order lookup' )
        ->toContain( 'href="' . route( 'artisanpack.ecommerce.storefront.lookup' ) . '"' )
        ->toContain( 'Order ORD-4001</h1>' )
        ->not->toContain( 'data-account-order-back' )
        ->and( substr_count( $html, '<h1' ) )->toBe( 1 );
} );

it( 'heads the account order page with the order number as its only h1', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [ 'order_number' => 'ORD-5001' ], $customer );

    $html = $this->get( route( 'artisanpack.ecommerce.account.orders.show', [ 'order' => $order->id ] ) )->assertOk()->getContent();

    expect( substr_count( $html, '<h1' ) )->toBe( 1 )
        ->and( $html )->toContain( 'Order ORD-5001</h1>' )
        ->not->toContain( 'Order details</h1>' );
} );

it( 'greets the shopper as the dashboard\'s h1, with recent orders, address cards and onward links (#146)', function (): void {
    [ , $customer ] = shopper( [ 'first_name' => 'Dana' ] );
    placedOrder( [ 'order_number' => 'ORD-6001' ], $customer );
    CustomerAddress::factory()->defaultShipping()->defaultBilling()->create( [ 'customer_id' => $customer->id, 'label' => 'Home' ] + checkoutAddress() );

    $html = Livewire::test( Dashboard::class, [ 'showTitle' => true ] )->html();

    expect( $html )->toContain( '<h1 class="ec-dashboard__greeting" data-account-greeting>Welcome back, Dana.</h1>' )
        ->toContain( 'Order ORD-6001' )
        ->toContain( 'apui-icon-tile' )
        ->toContain( 'data-account-billing-same' )
        ->toContain( 'data-account-link="addresses"' )
        ->toContain( 'Files from your orders will appear here.' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_ORDER_SCREEN_CLASSES );
} );

it( 'shows a new account the empty recent orders state', function (): void {
    shopper( [ 'first_name' => 'Dana' ] );

    Livewire::test( Dashboard::class )
        ->assertSeeHtml( 'class="ec-dashboard__lead" data-account-greeting' )
        ->assertSeeHtml( 'data-account-no-orders' )
        ->assertSee( 'No orders yet' )
        ->assertDontSeeHtml( 'data-account-billing-same' );
} );

it( 'heads the dashboard page with the greeting as its only h1', function (): void {
    shopper( [ 'first_name' => 'Dana' ] );

    $html = $this->get( route( 'artisanpack.ecommerce.account.dashboard' ) )->assertOk()->getContent();

    expect( substr_count( $html, '<h1' ) )->toBe( 1 )
        ->and( $html )->toContain( 'Welcome back, Dana.</h1>' );
} );
