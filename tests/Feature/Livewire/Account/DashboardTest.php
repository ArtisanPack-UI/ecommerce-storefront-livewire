<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Registries\AccountMenuRegistry;
use ArtisanPackUI\Ecommerce\Services\CustomerOrderHistory;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Dashboard;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

it( 'greets the shopper and lists their three latest orders', function (): void {
    [ , $customer ] = shopper( [ 'first_name' => 'Ada' ] );

    $orders = collect( range( 1, 4 ) )->map( fn ( int $day ) => placedOrder( [ 'placed_at' => now()->subDays( 5 - $day ) ], $customer ) );
    $other  = placedOrder();

    Livewire::test( Dashboard::class )
        ->assertSee( 'Welcome back, Ada.' )
        ->assertSeeHtml( 'data-account-recent-order="' . $orders[3]->id . '"' )
        ->assertSeeHtml( 'data-account-recent-order="' . $orders[2]->id . '"' )
        ->assertSeeHtml( 'data-account-recent-order="' . $orders[1]->id . '"' )
        ->assertDontSeeHtml( 'data-account-recent-order="' . $orders[0]->id . '"' )
        ->assertDontSeeHtml( 'data-account-recent-order="' . $other->id . '"' )
        ->assertSee( '2 items' )
        ->assertSee( 'Processing' )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.account.orders.show', [ 'order' => $orders[3]->id ] ) . '"' )
        ->assertSeeHtml( 'data-account-all-orders' );
} );

it( 'shows the default addresses and how many downloads the shopper has', function (): void {
    [ , $customer ] = shopper();

    CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'address1' => '9 Ship Lane', 'is_default_shipping' => true, 'is_default_billing' => false ] );
    CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'address1' => '4 Bill Road', 'is_default_shipping' => false, 'is_default_billing' => true ] );
    CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'address1' => '7 Other Way', 'is_default_shipping' => false, 'is_default_billing' => false ] );

    $order = placedOrder( [], $customer );
    $file  = DigitalFile::factory()->create( [ 'product_id' => $order->items->first()->product_id ] );
    DigitalDownload::factory()->count( 2 )->create( [ 'order_item_id' => $order->items->first()->id, 'digital_file_id' => $file->id ] );

    Livewire::test( Dashboard::class )
        ->assertSee( '9 Ship Lane' )
        ->assertSee( '4 Bill Road' )
        ->assertDontSee( '7 Other Way' )
        ->assertSee( '2 downloads available' );
} );

it( 'works for a signed-in user the store has no customer record for', function (): void {
    $this->actingAs( makeUser( [ 'name' => 'Grace' ] ) );

    Livewire::test( Dashboard::class )
        ->assertSee( 'Welcome back, Grace.' )
        ->assertSeeHtml( 'data-account-no-orders' )
        ->assertSee( 'None saved yet.' )
        ->assertSee( '0 downloads available' )
        ->assertDontSeeHtml( 'data-account-dashboard-failed' );
} );

it( 'links to the host\'s profile and password screens when they exist', function (): void {
    Route::get( 'settings/profile', static fn (): string => 'profile' )->middleware( 'web' )->name( 'settings.profile' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    shopper();

    Livewire::test( Dashboard::class )
        ->assertSeeHtml( 'data-account-host-link="profile"' )
        ->assertSeeHtml( 'href="' . route( 'settings.profile' ) . '"' )
        ->assertDontSeeHtml( 'data-account-host-link="password"' );
} );

it( 'still shows the dashboard when the engine can\'t load the orders', function (): void {
    shopper( [ 'first_name' => 'Ada' ] );

    app()->instance( CustomerOrderHistory::class, new class extends CustomerOrderHistory {
        public function query( ArtisanPackUI\Ecommerce\Models\Customer $customer, ?string $status = null ): Illuminate\Database\Eloquent\Builder
        {
            throw new RuntimeException( 'Database is down.' );
        }
    } );

    Livewire::test( Dashboard::class )
        ->assertOk()
        ->assertSee( 'Welcome back, Ada.' )
        ->assertSeeHtml( 'data-account-dashboard-failed' );
} );

it( 'lists the core account pages and satellite entries by position, marking the current page', function (): void {
    app( AccountMenuRegistry::class )->register( 'subscriptions', [ 'label' => 'Subscriptions', 'route' => '/account/subscriptions', 'icon' => 'o-arrow-path', 'position' => 25 ] );
    app( AccountMenuRegistry::class )->register( 'hidden', [ 'label' => 'Hidden thing', 'route' => '/account/hidden', 'visible' => false ] );
    app( AccountMenuRegistry::class )->register( 'wishlist', [ 'label' => 'Wishlist', 'route' => '/account/wishlist' ] );

    shopper();

    $html = $this->get( route( 'artisanpack.ecommerce.account.orders.index' ) )
        ->assertOk()
        ->assertSee( 'Subscriptions' )
        ->assertSee( 'Wishlist' )
        ->assertDontSee( 'Hidden thing' )
        ->getContent();

    $keys = [ 'dashboard', 'orders.index', 'satellite-subscriptions', 'addresses', 'downloads', 'profile', 'claim', 'satellite-wishlist' ];
    $at   = array_map( static fn ( string $key ): int => (int) strpos( $html, 'data-account-nav-entry="' . $key . '"' ), $keys );

    expect( $at )->each->toBeGreaterThan( 0 )
        ->and( $at )->toBe( collect( $at )->sort()->values()->all() );

    preg_match( '/<a[^>]*data-account-nav-entry="orders\.index"[^>]*>/', $html, $orders );
    preg_match( '/<a[^>]*data-account-nav-entry="dashboard"[^>]*>/', $html, $dashboard );

    expect( $orders[0] ?? '' )->toContain( 'aria-current="page"' )
        ->and( $dashboard[0] ?? '' )->not->toContain( 'aria-current' );
} );

it( 'keeps "Orders" active on an order\'s page', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [], $customer );

    $html = $this->get( route( 'artisanpack.ecommerce.account.orders.show', [ 'order' => $order->id ] ) )->assertOk()->getContent();

    preg_match( '/<a[^>]*data-account-nav-entry="orders\.index"[^>]*>/', $html, $orders );

    expect( $orders[0] ?? '' )->toContain( 'aria-current="page"' );
} );

it( 'prompts the shopper to claim guest orders placed with their email', function (): void {
    [ , $customer ] = shopper();

    placedOrder( [ 'email' => $customer->email, 'is_claimed' => false ] );
    placedOrder( [ 'email' => $customer->email, 'is_claimed' => false ] );
    placedOrder( [ 'email' => 'someone@example.test', 'is_claimed' => false ] );
    placedOrder( [], $customer );

    Livewire::test( Dashboard::class )
        ->assertSeeHtml( 'data-account-claim-prompt' )
        ->assertSee( 'We found 2 orders placed as a guest with your email' )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.account.claim' ) . '"' );
} );

it( 'doesn\'t prompt when there are no unclaimed guest orders for the shopper', function (): void {
    [ , $customer ] = shopper();

    placedOrder( [ 'email' => 'someone@example.test', 'is_claimed' => false ] );
    placedOrder( [], $customer );

    Livewire::test( Dashboard::class )->assertDontSeeHtml( 'data-account-claim-prompt' );
} );

it( 'doesn\'t prompt an account whose email isn\'t verified', function (): void {
    $user = makeUser();

    placedOrder( [ 'email' => $user->email, 'is_claimed' => false ] );

    $unverified = new class extends Tests\Fixtures\User implements Illuminate\Contracts\Auth\MustVerifyEmail {
        use Illuminate\Auth\MustVerifyEmail;
    };
    $unverified->setRawAttributes( $user->getAttributes(), true );
    $unverified->exists = true;

    $this->actingAs( $unverified );

    Livewire::test( Dashboard::class )->assertDontSeeHtml( 'data-account-claim-prompt' );
} );
