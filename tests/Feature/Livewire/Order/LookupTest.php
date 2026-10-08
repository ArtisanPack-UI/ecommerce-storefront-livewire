<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\Ecommerce\Services\GuestOrderLookupService;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Order\Lookup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach( function (): void {
    foreach ( [ 'ecommerce:lookup:ip:' . sha1( '127.0.0.1' ), 'ecommerce:lookup-request:ip:' . sha1( '127.0.0.1' ) ] as $key ) {
        RateLimiter::clear( $key );
    }
} );

/**
 * The token in a redirect to the signed order page.
 */
function lookupToken( Testable $component ): string
{
    $url = (string) ( $component->effects['redirect'] ?? '' );

    return 1 === preg_match( '#/shop/order/([0-9]+-[0-9]+-[a-f0-9]{64})$#', $url, $matches ) ? $matches[1] : '';
}

it( 'renders the lookup form', function (): void {
    Livewire::test( Lookup::class )
        ->assertSeeHtml( 'data-order-lookup-form' )
        ->assertSee( 'Email' )
        ->assertSee( 'Order number' );
} );

it( 'links a guest to sign in', function (): void {
    Route::get( 'login', static fn (): string => 'login' )->middleware( 'web' )->name( 'login' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    Livewire::test( Lookup::class )->assertSeeHtml( 'data-order-lookup-login' );

    $this->actingAs( makeUser() );

    Livewire::test( Lookup::class )->assertDontSeeHtml( 'data-order-lookup-login' );
} );

it( 'opens a short-lived signed page for the order when the email and number match', function (): void {
    Carbon::setTestNow( '2026-10-08 12:00:00' );

    $order = placedOrder( [ 'email' => 'ada@example.test', 'order_number' => 'ORD-1001' ] );

    $component = Livewire::test( Lookup::class )
        ->set( 'email', ' ADA@example.test ' )
        ->set( 'orderNumber', 'ORD-1001' )
        ->call( 'find' )
        ->assertHasNoErrors()
        ->assertSet( 'failure', null );

    $token = lookupToken( $component );

    expect( $token )->not->toBe( '' )
        ->and( OrderViewToken::verify( $token )?->id )->toBe( $order->id );

    Carbon::setTestNow( '2026-10-08 13:01:00' );

    expect( OrderViewToken::verify( $token ) )->toBeNull();

    Carbon::setTestNow();
} );

it( 'requires a valid email and the order number', function (): void {
    Livewire::test( Lookup::class )
        ->call( 'find' )
        ->assertHasErrors( [ 'email' => 'Enter the email address you used for the order.', 'orderNumber' => 'Enter the order number.' ] );

    Livewire::test( Lookup::class )
        ->set( 'email', 'not-an-email' )
        ->set( 'orderNumber', str_repeat( '1', 51 ) )
        ->call( 'find' )
        ->assertHasErrors( [ 'email' => 'Enter a valid email address.', 'orderNumber' => 'Keep this under 50 characters.' ] )
        ->assertNoRedirect();
} );

it( 'gives one message for a wrong email, an unknown number, and an anonymized order', function (): void {
    placedOrder( [ 'email' => 'ada@example.test', 'order_number' => 'ORD-1001' ] );
    placedOrder( [ 'email' => CustomerService::ANONYMIZED_EMAIL, 'order_number' => 'ORD-1002' ] );

    $messages = collect( [ [ 'grace@example.test', 'ORD-1001' ], [ 'ada@example.test', 'ORD-9999' ], [ CustomerService::ANONYMIZED_EMAIL, 'ORD-1002' ] ] )
        ->map( fn ( array $attempt ): ?string => Livewire::test( Lookup::class )
            ->set( 'email', $attempt[0] )
            ->set( 'orderNumber', $attempt[1] )
            ->call( 'find' )
            ->assertNoRedirect()
            ->get( 'failure' ) )
        ->unique();

    expect( $messages )->toHaveCount( 1 )
        ->and( $messages->first() )->toBe( 'We couldn\'t find an order with that email address and order number.' );
} );

it( 'locks out repeated wrong guesses and says how long to wait', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.guest_lookup.max_failures_per_order', 2 );
    config()->set( 'artisanpack.ecommerce.checkout.guest_lookup.lockout_minutes', 15 );

    placedOrder( [ 'email' => 'ada@example.test', 'order_number' => 'ORD-1001' ] );

    foreach ( range( 1, 2 ) as $attempt ) {
        Livewire::test( Lookup::class )->set( 'email', 'grace@example.test' )->set( 'orderNumber', 'ORD-1001' )->call( 'find' );
    }

    Livewire::test( Lookup::class )
        ->set( 'email', 'ada@example.test' )
        ->set( 'orderNumber', 'ORD-1001' )
        ->call( 'find' )
        ->assertNoRedirect()
        ->assertSet( 'failure', 'Too many attempts. Try again in 15 minutes.' );

    RateLimiter::clear( 'ecommerce:lookup:order:' . sha1( 'ORD-1001' ) );
} );

it( 'is rate limited per IP', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.lookup.attempt.per_ip', 1 );

    placedOrder( [ 'email' => 'ada@example.test', 'order_number' => 'ORD-1001' ] );

    Livewire::test( Lookup::class )->set( 'email', 'grace@example.test' )->set( 'orderNumber', 'ORD-1001' )->call( 'find' );

    Livewire::test( Lookup::class )
        ->set( 'email', 'ada@example.test' )
        ->set( 'orderNumber', 'ORD-1001' )
        ->call( 'find' )
        ->assertNoRedirect()
        ->assertSet( 'failure', fn ( ?string $failure ): bool => str_starts_with( (string) $failure, 'Too many attempts. Try again in' ) );

    RateLimiter::clear( 'ecommerce:lookup:order:' . sha1( 'ORD-1001' ) );
} );

it( 'reports an unexpected engine failure', function (): void {
    app()->instance( GuestOrderLookupService::class, Mockery::mock( GuestOrderLookupService::class, function ( $mock ): void {
        $mock->shouldReceive( 'find' )->andThrow( new RuntimeException( 'Database is down.' ) );
    } ) );

    Livewire::test( Lookup::class )
        ->set( 'email', 'ada@example.test' )
        ->set( 'orderNumber', 'ORD-1001' )
        ->call( 'find' )
        ->assertNoRedirect()
        ->assertSet( 'failure', 'We couldn\'t look up orders right now. Try again in a moment.' );
} );

it( 'shows the order read-only on its signed page, not indexed', function (): void {
    $order = placedOrder( [ 'email' => 'ada@example.test', 'order_number' => 'ORD-1001' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.order-view', [ 'token' => OrderViewToken::for( $order ) ] ) )
        ->assertOk()
        ->assertSee( 'ORD-1001' )
        ->assertSee( 'Mug' )
        ->assertDontSee( 'Buy again' )
        ->assertHeader( 'X-Robots-Tag', 'noindex, nofollow' )
        ->assertHeader( 'Referrer-Policy', 'same-origin' );
} );

it( 'answers 404 for a tampered or expired order link', function (): void {
    $order = placedOrder();
    $token = OrderViewToken::for( $order );

    $this->get( route( 'artisanpack.ecommerce.storefront.order-view', [ 'token' => substr( $token, 0, -1 ) . ( str_ends_with( $token, 'a' ) ? 'b' : 'a' ) ] ) )->assertNotFound();
    $this->get( route( 'artisanpack.ecommerce.storefront.order-view', [ 'token' => OrderViewToken::for( $order, now()->subMinute() ) ] ) )->assertNotFound();
} );

it( 'points the engine\'s guest order links at the signed page', function (): void {
    $order = placedOrder();

    expect( config( 'artisanpack.ecommerce.checkout.order_view_url' ) )->toBe( url( 'shop/order/{token}' ) )
        ->and( OrderViewToken::url( $order ) )->toStartWith( url( 'shop/order/' ) );

    $this->get( OrderViewToken::url( $order ) )->assertOk();
} );

it( 'ships a "find your order" link for the host\'s sign-in screen', function (): void {
    expect( view( 'ecommerce-storefront::partials.order-lookup-link' )->render() )
        ->toContain( 'data-order-lookup-link' )
        ->toContain( 'href="' . route( 'artisanpack.ecommerce.storefront.lookup' ) . '"' );
} );
