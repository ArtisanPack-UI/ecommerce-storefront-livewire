<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Services\CustomerOrderHistory;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Orders;
use Livewire\Livewire;

it( 'lists the shopper\'s orders with number, date, status, total, and item count', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [ 'total_amount' => 4500 ], $customer );
    $other          = placedOrder( [ 'order_number' => 'SOMEONEELSE' ] );

    Livewire::test( Orders::class )
        ->assertSeeHtml( 'data-account-orders-table' )
        ->assertSee( $order->order_number )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.account.orders.show', [ 'order' => $order->id ] ) . '"' )
        ->assertSee( 'Processing' )
        ->assertSee( '$45.00' )
        ->assertSee( '2 items' )
        ->assertDontSee( $other->order_number );
} );

it( 'filters by status group, in the query string', function (): void {
    [ , $customer ] = shopper();
    $open           = placedOrder( [ 'order_number' => 'OPEN1' ], $customer );
    $done           = placedOrder( [ 'order_number' => 'DONE1', 'system_status' => 'complete' ], $customer );
    $cancelled      = placedOrder( [ 'order_number' => 'GONE1', 'system_status' => 'cancelled' ], $customer );

    Livewire::withQueryParams( [ 'status' => 'completed' ] )
        ->test( Orders::class )
        ->assertSet( 'status', 'completed' )
        ->assertSee( 'DONE1' )
        ->assertDontSee( 'OPEN1' )
        ->set( 'status', 'cancelled' )
        ->assertSee( 'GONE1' )
        ->assertSee( 'Cancelled' )
        ->assertDontSee( 'DONE1' )
        ->set( 'status', '' )
        ->assertSee( 'OPEN1' )
        ->assertSee( 'DONE1' );
} );

it( 'ignores an unknown status filter', function (): void {
    [ , $customer ] = shopper();
    placedOrder( [ 'order_number' => 'OPEN1' ], $customer );

    Livewire::withQueryParams( [ 'status' => 'pending\' OR 1=1' ] )
        ->test( Orders::class )
        ->assertSet( 'status', '' )
        ->assertSee( 'OPEN1' )
        ->set( 'status', 'nope' )
        ->assertSet( 'status', '' );
} );

it( 'paginates the history', function (): void {
    [ , $customer ] = shopper();

    foreach ( range( 1, Orders::PER_PAGE + 2 ) as $day ) {
        placedOrder( [ 'order_number' => 'ORD' . str_pad( (string) $day, 3, '0', STR_PAD_LEFT ), 'placed_at' => now()->subDays( 100 - $day ) ], $customer );
    }

    Livewire::test( Orders::class )
        ->assertSee( 'ORD012' )
        ->assertDontSee( 'ORD002' )
        ->assertSeeHtml( 'data-account-orders-pagination' )
        ->call( 'gotoPage', 2 )
        ->assertSee( 'ORD002' )
        ->assertDontSee( 'ORD012' );
} );

it( 'shows an empty state with no orders, and a different one when the filter matches none', function (): void {
    [ , $customer ] = shopper();

    Livewire::test( Orders::class )->assertSeeHtml( 'data-account-orders-empty="none"' )->assertSee( 'No orders yet' );

    placedOrder( [], $customer );

    Livewire::test( Orders::class )
        ->set( 'status', 'cancelled' )
        ->assertSeeHtml( 'data-account-orders-empty="filtered"' )
        ->assertSee( 'No orders match this filter' );
} );

it( 'shows an empty list to a user the store has no customer record for', function (): void {
    $this->actingAs( makeUser() );
    placedOrder();

    Livewire::test( Orders::class )->assertSeeHtml( 'data-account-orders-empty="none"' );
} );

it( 'says when the engine can\'t load the orders', function (): void {
    shopper();

    app()->instance( CustomerOrderHistory::class, new class extends CustomerOrderHistory {
        public function query( ArtisanPackUI\Ecommerce\Models\Customer $customer, ?string $status = null ): Illuminate\Database\Eloquent\Builder
        {
            throw new RuntimeException( 'Database is down.' );
        }
    } );

    Livewire::test( Orders::class )->assertOk()->assertSeeHtml( 'data-account-orders-failed' );
} );
