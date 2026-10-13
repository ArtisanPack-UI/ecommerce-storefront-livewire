<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Drawer;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutPlacement;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

/*
 * Every storefront link is a full page load (owner decision D4): the
 * library's `:link` buttons default to `wire:navigate`, which changes the
 * page with no focus reset or page announcement, and host sign-in pages
 * may not be Livewire pages.
 */

beforeEach( function (): void {
    Route::get( 'login', static fn (): string => 'login' )->name( 'login' );
    Route::get( 'register', static fn (): string => 'register' )->name( 'register' );
    app( 'router' )->getRoutes()->refreshNameLookups();
} );

it( 'opts every library link button in the views out of wire:navigate', function (): void {
    $missing = [];

    foreach ( Finder::create()->files()->in( __DIR__ . '/../../../resources/views' )->name( '*.blade.php' ) as $file ) {
        preg_match_all( '/<x-artisanpack-(?:button|menu-item|link|list-item)\b.*?\/?>\s*$/ms', $file->getContents(), $tags );

        foreach ( $tags[0] as $tag ) {
            if ( 1 === preg_match( '/\s:?link=/', $tag ) && 1 !== preg_match( '/\s(?:no-wire-navigate|external)\b/', $tag ) ) {
                $missing[] = $file->getRelativePathname() . ': ' . trim( strtok( $tag, "\n" ) );
            }
        }
    }

    expect( $missing )->toBe( [] );
} );

it( 'renders no wire:navigate links on the cart, checkout and confirmation pages', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.cart' ) )->assertOk()->assertDontSee( 'wire:navigate', false );

    checkoutCart();

    $this->get( route( 'artisanpack.ecommerce.storefront.cart' ) )->assertOk()->assertSee( 'data-cart-checkout', false )->assertDontSee( 'wire:navigate', false );
    $this->get( route( 'artisanpack.ecommerce.storefront.checkout' ) )->assertOk()->assertSee( 'data-checkout-contact-form', false )->assertDontSee( 'wire:navigate', false );

    Livewire::test( Drawer::class )
        ->dispatch( 'ecommerce-cart-open' )
        ->assertSeeHtml( 'data-cart-drawer-checkout' )
        ->assertDontSeeHtml( 'wire:navigate' );

    $this->get( (string) CheckoutPlacement::confirmationUrl( placedOrder() ) )->assertOk()->assertSee( 'data-order-continue', false )->assertDontSee( 'wire:navigate', false );
} );

it( 'renders no wire:navigate links on the account screens', function ( string $route ): void {
    shopper();

    $this->get( route( 'artisanpack.ecommerce.account.' . $route ) )->assertOk()->assertDontSee( 'wire:navigate', false );
} )->with( [ 'dashboard', 'orders.index', 'downloads', 'profile', 'addresses' ] );
