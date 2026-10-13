<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Order\Confirmation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach( function (): void {
    Route::get( 'register', static fn (): string => 'register' )->middleware( 'web' )->name( 'register' );
    app( 'router' )->getRoutes()->refreshNameLookups();
} );

it( 'shows a guest their order with a signed token', function (): void {
    $order = placedOrder( [ 'customer_note' => 'Ring the bell' ] );

    Livewire::test( Confirmation::class, [ 'order' => $order->id, 'token' => OrderViewToken::for( $order ) ] )
        ->assertSee( 'Thank you for your order' )
        ->assertSee( $order->order_number )
        ->assertSee( 'Processing' )
        ->assertSee( 'Mug' )
        ->assertSee( '1 Main Street' )
        ->assertSee( 'Ring the bell' )
        ->assertSee( '$45.00' )
        ->assertSee( 'Standard' )
        ->assertSeeHtml( 'data-order-guest-hints' )
        ->assertSee( 'Create an account to track orders' )
        ->assertSee( 'Look up this order later' )
        ->assertSeeHtml( 'href="' . route( 'register' ) . '"' )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.storefront.lookup' ) . '"' )
        ->assertDontSeeHtml( 'data-order-account-link' );
} );

it( 'shows the owner their order without a token, with a link to their account', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [], $customer );

    Livewire::test( Confirmation::class, [ 'order' => $order->id ] )
        ->assertSet( 'owner', true )
        ->assertSee( $order->order_number )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.account.orders.show', [ 'order' => $order->id ] ) . '"' )
        ->assertDontSeeHtml( 'data-order-guest-hints' );
} );

it( 'answers 404 without a valid token or ownership', function ( Closure $arrange ): void {
    $order = placedOrder();

    [ $reference, $token ] = $arrange( $order );

    Livewire::test( Confirmation::class, [ 'order' => $reference, 'token' => $token ] )->assertNotFound();
} )->with( [
    'no token'          => [ fn ( Order $order ): array => [ $order->id, null ] ],
    'tampered token'    => [ fn ( Order $order ): array => [ $order->id, substr( OrderViewToken::for( $order ), 0, -1 ) . 'x' ] ],
    'expired token'     => [ fn ( Order $order ): array => [ $order->id, OrderViewToken::for( $order, Carbon::now()->subDay() ) ] ],
    'another order'     => [ fn ( Order $order ): array => [ $order->id, OrderViewToken::for( placedOrder() ) ] ],
    'not a number'      => [ fn ( Order $order ): array => [ 'abc', OrderViewToken::for( $order ) ] ],
    'someone else\'s'   => [ function ( Order $order ): array {
        shopper();

        return [ placedOrder( [], ArtisanPackUI\Ecommerce\Models\Customer::factory()->create() )->id, null ];
    } ],
] );

it( 'lists the downloads digital lines unlocked', function (): void {
    $order = placedOrder();
    $file  = DigitalFile::factory()->create( [ 'label' => 'Field guide (PDF)', 'product_id' => $order->items->first()->product_id ] );

    DigitalDownload::factory()->create( [
        'order_item_id'       => $order->items->first()->id,
        'digital_file_id'     => $file->id,
        'downloads_remaining' => 3,
    ] );

    Livewire::test( Confirmation::class, [ 'order' => $order->id, 'token' => OrderViewToken::for( $order ) ] )
        ->assertSeeHtml( 'data-order-downloads' )
        ->assertSee( 'Field guide (PDF)' )
        ->assertSee( '3 downloads left' )
        ->assertSee( 'We\'ve emailed your download links to guest@example.test.' );
} );

it( 'changes nothing when the page is refreshed', function (): void {
    $order = placedOrder();
    $token = OrderViewToken::for( $order );
    $fired = 0;

    addAction( 'ap.ecommerce.order.placed', function () use ( &$fired ): void {
        $fired++;
    } );

    $before = $order->refresh()->toArray();

    Livewire::test( Confirmation::class, [ 'order' => $order->id, 'token' => $token ] )->call( '$refresh' );
    Livewire::test( Confirmation::class, [ 'order' => $order->id, 'token' => $token ] );

    expect( $order->refresh()->toArray() )->toBe( $before )
        ->and( $fired )->toBe( 0 )
        ->and( Order::query()->count() )->toBe( 1 );
} );

it( 'serves the page privately and not indexed', function (): void {
    $order = placedOrder();

    $response = $this->get( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id, 'token' => OrderViewToken::for( $order ) ] ) )
        ->assertOk()
        ->assertSee( 'data-order-confirmation', false )
        ->assertHeader( 'X-Robots-Tag', 'noindex, nofollow' )
        ->assertHeader( 'Referrer-Policy', 'same-origin' );

    expect( $response->headers->get( 'Cache-Control' ) )->toContain( 'no-store' );

    $this->get( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) )->assertNotFound();
} );
