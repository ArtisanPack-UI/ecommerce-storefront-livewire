<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Drawer;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutPlacement;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

/*
 * Every storefront link is a full page load (owner decision D4):
 * `wire:navigate` changes the page with no focus reset or page
 * announcement, and host sign-in pages may not be Livewire pages. The 3.0
 * library's buttons never add it; its menu items and breadcrumbs do
 * unless told `:navigate="false"`.
 */

/**
 * Whether the HTML has a `wire:navigate` attribute (not the toaster's
 * `livewire:navigated` listener).
 */
function hasWireNavigate( string $html ): bool
{
    return 1 === preg_match( '/(?<![\w-])wire:navigate\b/', $html );
}

beforeEach( function (): void {
    Route::get( 'login', static fn (): string => 'login' )->name( 'login' );
    Route::get( 'register', static fn (): string => 'register' )->name( 'register' );
    app( 'router' )->getRoutes()->refreshNameLookups();
} );

it( 'opts every library menu item and breadcrumb trail in the views out of wire:navigate', function (): void {
    $missing = [];

    foreach ( Finder::create()->files()->in( __DIR__ . '/../../../resources/views' )->name( '*.blade.php' ) as $file ) {
        preg_match_all( '/<x-artisanpack-(?:menu-item|breadcrumbs|link|list-item)\b(?:[^>]|(?<=[-=])>)*>/s', $file->getContents(), $tags );

        foreach ( $tags[0] as $tag ) {
            if ( 1 === preg_match( '/\s:?(?:link|href|items)=/', $tag ) && 1 !== preg_match( '/\s:navigate="false"/', $tag ) ) {
                $missing[] = $file->getRelativePathname() . ': ' . trim( strtok( $tag, "\n" ) );
            }
        }
    }

    expect( $missing )->toBe( [] );
} );

it( 'renders no wire:navigate links on the cart, checkout and confirmation pages', function (): void {
    expect( hasWireNavigate( $this->get( route( 'artisanpack.ecommerce.storefront.cart' ) )->assertOk()->getContent() ) )->toBeFalse();

    checkoutCart();

    $cart     = $this->get( route( 'artisanpack.ecommerce.storefront.cart' ) )->assertOk()->assertSee( 'data-cart-checkout', false )->getContent();
    $checkout = $this->get( route( 'artisanpack.ecommerce.storefront.checkout' ) )->assertOk()->assertSee( 'data-checkout-contact-form', false )->getContent();
    $drawer   = Livewire::test( Drawer::class )->dispatch( 'ecommerce-cart-open' )->assertSeeHtml( 'data-cart-drawer-checkout' )->html();
    $placed   = $this->get( (string) CheckoutPlacement::confirmationUrl( placedOrder() ) )->assertOk()->assertSee( 'data-order-continue', false )->getContent();

    expect( hasWireNavigate( $cart ) )->toBeFalse()
        ->and( hasWireNavigate( $checkout ) )->toBeFalse()
        ->and( hasWireNavigate( $drawer ) )->toBeFalse()
        ->and( hasWireNavigate( $placed ) )->toBeFalse();
} );

it( 'renders no wire:navigate links on the account screens', function ( string $route ): void {
    shopper();

    expect( hasWireNavigate( $this->get( route( 'artisanpack.ecommerce.account.' . $route ) )->assertOk()->getContent() ) )->toBeFalse();
} )->with( [ 'dashboard', 'orders.index', 'downloads', 'profile', 'addresses' ] );
