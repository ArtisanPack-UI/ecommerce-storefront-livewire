<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\Ecommerce\Support\GuestCartCookie;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Puts a guest-cart token in the current request's cookies.
 */
function withCartCookie( string $token ): void
{
    app( 'request' )->cookies->set( GuestCartCookie::name(), $token );
}

/**
 * The queued guest-cart cookie, if any.
 */
function queuedCartCookie(): ?Symfony\Component\HttpFoundation\Cookie
{
    return Cookie::queued( GuestCartCookie::name() );
}

/**
 * A fresh resolver, as a new request would get.
 */
function freshCarts(): StorefrontCart
{
    app()->forgetScopedInstances();

    return app( StorefrontCart::class );
}

it( 'has no cart for a new guest until one is needed', function (): void {
    expect( freshCarts()->current() )->toBeNull()
        ->and( queuedCartCookie() )->toBeNull()
        ->and( Cart::query()->count() )->toBe( 0 );
} );

it( 'creates a guest cart on demand in the shopper\'s currency and queues its cookie', function (): void {
    $cart = freshCarts()->current( true );

    expect( $cart )->toBeInstanceOf( Cart::class )
        ->and( $cart->currency )->toBe( 'USD' )
        ->and( $cart->customer_id )->toBeNull()
        ->and( queuedCartCookie()?->getValue() )->toBe( $cart->token )
        ->and( queuedCartCookie()?->isHttpOnly() )->toBeTrue()
        // A second call in the same request finds it before the cookie reaches the browser.
        ->and( app( StorefrontCart::class )->current()?->is( $cart ) )->toBeTrue()
        ->and( Cart::query()->count() )->toBe( 1 );
} );

it( 'resolves a returning guest\'s cart from the cookie without re-queuing it', function (): void {
    $cart = app( StorefrontCartService::class )->create( 'USD' );
    withCartCookie( $cart->token );

    expect( freshCarts()->current()?->is( $cart ) )->toBeTrue()
        ->and( queuedCartCookie() )->toBeNull();
} );

it( 'ignores a malformed or unknown cookie token', function ( string $token ): void {
    withCartCookie( $token );

    expect( freshCarts()->current() )->toBeNull();
} )->with( [ 'malformed' => 'not-a-token', 'unknown' => str_repeat( 'a', 40 ) ] );

it( 'gives a signed-in shopper their account cart and clears a leftover guest cookie', function (): void {
    $user     = makeUser();
    $customer = app( CustomerService::class )->customerForUser( $user, true );
    $account  = app( StorefrontCartService::class )->create( 'USD', $customer->email, $customer );
    $guest    = app( StorefrontCartService::class )->create( 'USD' );

    withCartCookie( $guest->token );
    Auth::setUser( $user );

    expect( freshCarts()->current()?->is( $account ) )->toBeTrue()
        ->and( queuedCartCookie()?->getExpiresTime() )->toBeLessThan( time() );
} );

it( 'creates an account cart for a signed-in shopper without setting a guest cookie', function (): void {
    Auth::setUser( makeUser() );

    $cart = freshCarts()->current( true );

    expect( $cart->customer_id )->not->toBeNull()
        ->and( queuedCartCookie() )->toBeNull();
} );

it( 'counts the units in the cart', function (): void {
    $carts = freshCarts();
    $cart  = $carts->current( true );

    app( StorefrontCartService::class )->addItem( $cart, makeProduct()->id, null, 2 );
    app( StorefrontCartService::class )->addItem( $cart, makeProduct()->id, null, 3 );

    expect( $carts->count() )->toBe( 5 );
} );

it( 'stops returning a cart once it has expired', function (): void {
    $carts = freshCarts();
    $cart  = $carts->current( true );

    $cart->forceFill( [ 'expires_at' => now()->subMinute() ] )->save();
    $carts->remember( $cart );

    expect( $carts->current() )->toBeNull();
} );

it( 're-queues the cookie when the guest cart\'s token changes', function (): void {
    $carts = freshCarts();
    $cart  = $carts->current( true );

    withCartCookie( $cart->token );
    Cookie::flushQueuedCookies();

    $cart->forceFill( [ 'token' => str_repeat( 'b', 40 ) ] )->save();
    $carts->remember( $cart );

    expect( queuedCartCookie()?->getValue() )->toBe( str_repeat( 'b', 40 ) );
} );

it( 'counts rate limits against the cart, the customer, or the IP', function (): void {
    $carts = freshCarts();

    expect( $carts->subject()->cartToken )->toBeNull()
        ->and( $carts->subject()->user )->toBeNull();

    $cart = $carts->current( true );

    expect( $carts->subject()->cartToken )->toBe( $cart->token );

    $user = makeUser();
    Auth::setUser( $user );

    expect( freshCarts()->subject()->user )->toBe( $user );
} );
