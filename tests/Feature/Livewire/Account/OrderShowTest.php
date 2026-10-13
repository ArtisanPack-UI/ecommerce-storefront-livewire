<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\OrderNote;
use ArtisanPackUI\Ecommerce\Models\Refund;
use ArtisanPackUI\Ecommerce\Models\Shipment;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\OrderShow;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Livewire\Livewire;

it( 'shows the order\'s lines, totals, addresses, and payment method', function (): void {
    checkoutGateway( 'fake', 'Fake card' );

    [ , $customer ] = shopper();
    $order          = placedOrder( [ 'payment_gateway_key' => 'fake' ], $customer );

    Livewire::test( OrderShow::class, [ 'order' => $order->id ] )
        ->assertSee( $order->order_number )
        ->assertSee( 'Processing' )
        ->assertSee( 'Mug' )
        ->assertSee( 'SKU: SKU-' . $order->items->first()->product_id )
        ->assertSee( '1 Main Street' )
        ->assertSee( 'Fake card' )
        ->assertSee( '$45.00' )
        ->assertSeeHtml( 'data-account-order-buy-again' );
} );

it( 'shows shipments with safe tracking links, refunds, and the store\'s notes for the shopper', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [ 'total_refunded_amount' => 1000 ], $customer );

    Shipment::factory()->create( [ 'order_id' => $order->id, 'carrier' => 'UPS', 'tracking_number' => '1Z999', 'tracking_url' => 'https://ups.test/track/1Z999', 'status' => Shipment::STATUS_IN_TRANSIT, 'shipped_at' => now() ] );
    Shipment::factory()->create( [ 'order_id' => $order->id, 'carrier' => 'Sketchy', 'tracking_number' => 'XX1', 'tracking_url' => 'javascript:alert(1)', 'status' => Shipment::STATUS_PENDING ] );
    Refund::factory()->create( [ 'order_id' => $order->id, 'amount' => 1000, 'currency' => 'USD', 'status' => Refund::STATUS_SUCCEEDED ] );
    OrderNote::factory()->create( [ 'order_id' => $order->id, 'body' => 'Your parcel left our warehouse.', 'is_customer_visible' => true ] );
    OrderNote::factory()->create( [ 'order_id' => $order->id, 'body' => 'Internal: customer was rude.', 'is_customer_visible' => false ] );

    Livewire::test( OrderShow::class, [ 'order' => $order->id ] )
        ->assertSee( 'UPS' )
        ->assertSee( 'In transit' )
        ->assertSee( 'Tracking number: 1Z999' )
        ->assertSeeHtml( 'href="https://ups.test/track/1Z999"' )
        ->assertDontSeeHtml( 'javascript:alert(1)' )
        ->assertSee( 'Tracking number: XX1' )
        ->assertSeeHtml( 'data-order-refunds' )
        ->assertSee( '$10.00' )
        ->assertSee( 'Your parcel left our warehouse.' )
        ->assertDontSee( 'Internal: customer was rude.' );
} );

it( 'answers 404 for another customer\'s order or an unknown one', function ( Closure $reference ): void {
    shopper();

    Livewire::test( OrderShow::class, [ 'order' => $reference() ] )->assertNotFound();
} )->with( [
    'another customer\'s' => [ fn (): int => placedOrder( [], Customer::factory()->create() )->id ],
    'a guest order'       => [ fn (): int => placedOrder()->id ],
    'unknown'             => [ fn (): string => '999999' ],
] );

it( 'answers 404 over HTTP for another customer\'s order', function (): void {
    $order = placedOrder( [], Customer::factory()->create() );

    shopper();

    $this->get( route( 'artisanpack.ecommerce.account.orders.show', [ 'order' => $order->id ] ) )->assertNotFound();
} );

it( 'shows a guest\'s order read-only with a signed token', function (): void {
    $order = placedOrder();

    Livewire::test( OrderShow::class, [ 'order' => $order->id, 'token' => OrderViewToken::for( $order ) ] )
        ->assertSet( 'readOnly', true )
        ->assertSee( $order->order_number )
        ->assertSeeHtml( 'data-account-order-read-only' )
        ->assertDontSeeHtml( 'data-account-order-buy-again' )
        ->call( 'buyAgain' );

    expect( app( StorefrontCart::class )->current() )->toBeNull();
} );

it( 'adds the lines that can still be bought to the cart and reports the rest', function (): void {
    [ , $customer ] = shopper();

    $available = makeProduct( 2000, [ 'name' => 'Coffee' ] );
    $gone      = makeProduct( 1500, [ 'name' => 'Old blend' ] );
    $order     = placedOrder( [], $customer, [ $available, $gone ] );

    $gone->delete();

    $component = Livewire::test( OrderShow::class, [ 'order' => $order->id ] )
        ->call( 'buyAgain' )
        ->assertDispatched( 'ecommerce-cart-updated' )
        ->assertDispatched( 'ecommerce-cart-open' );

    $cart = app( StorefrontCart::class )->current();

    expect( $cart->items()->pluck( 'quantity', 'product_id' )->all() )->toBe( [ $available->id => 2 ] )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Old blend' );
} );

it( 'says nothing was added when no line can be bought', function (): void {
    [ , $customer ] = shopper();

    $gone  = makeProduct( 1500, [ 'name' => 'Old blend' ] );
    $order = placedOrder( [], $customer, [ $gone ] );

    $gone->delete();

    $component = Livewire::test( OrderShow::class, [ 'order' => $order->id ] )
        ->call( 'buyAgain' )
        ->assertNotDispatched( 'ecommerce-cart-open' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Nothing was added to your cart' );
} );
