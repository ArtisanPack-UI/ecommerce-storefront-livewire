<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\HeaderButton;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

it( 'renders a link to the cart that opens the drawer', function (): void {
    $cart = app( StorefrontCart::class )->current( true );
    app( StorefrontCartService::class )->addItem( $cart, makeProduct( 1000, [ 'name' => 'Mug' ] )->id, null, 2 );

    Livewire::test( HeaderButton::class )
        ->assertOk()
        ->assertSet( 'count', 2 )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.storefront.cart' ) . '"' )
        ->assertSeeHtml( 'aria-label="Cart, 2 items"' )
        ->assertSeeHtml( 'aria-haspopup="dialog"' )
        ->assertSeeHtml( '$dispatch( \'ecommerce-cart-open\' )' )
        ->assertSeeHtml( 'data-cart-count' );
} );

it( 'recounts when the cart changes, ignoring the count the event carries', function (): void {
    $component = Livewire::test( HeaderButton::class )->assertSet( 'count', 0 );

    $cart = app( StorefrontCart::class )->current( true );
    app( StorefrontCartService::class )->addItem( $cart, makeProduct()->id, null, 1 );

    $component->dispatch( 'ecommerce-cart-updated', count: 42 )
        ->assertSet( 'count', 1 )
        ->assertSeeHtml( 'aria-label="Cart, 1 item"' )
        ->assertSeeHtml( 'data-cart-count' );

    app( StorefrontCartService::class )->removeItem( $cart, $cart->items()->sole() );

    $component->dispatch( 'ecommerce-cart-updated', count: 1 )
        ->assertSet( 'count', 0 )
        ->assertSeeHtml( 'aria-label="Cart, 0 items"' )
        ->assertDontSeeHtml( 'data-cart-count' );
} );

it( 'shows no badge for an empty cart', function (): void {
    Livewire::test( HeaderButton::class )
        ->assertSet( 'count', 0 )
        ->assertSeeHtml( 'aria-label="Cart, 0 items"' )
        ->assertDontSeeHtml( 'data-cart-count' );
} );

it( 'renders nothing when the cart page route is off', function (): void {
    $routes = Route::getRoutes();
    $kept   = new Illuminate\Routing\RouteCollection();

    foreach ( $routes->getRoutes() as $route ) {
        if ( 'artisanpack.ecommerce.storefront.cart' !== $route->getName() ) {
            $kept->add( $route );
        }
    }

    Route::setRoutes( $kept );

    Livewire::test( HeaderButton::class )
        ->assertOk()
        ->assertDontSeeHtml( 'data-header-action="cart"' );
} );

it( 'is a ghost Button with the bag icon and a solid primary count badge hidden from assistive technology', function (): void {
    $cart = app( StorefrontCart::class )->current( true );
    app( StorefrontCartService::class )->addItem( $cart, makeProduct( 1000 )->id, null, 2 );

    $html = Livewire::test( HeaderButton::class )->html();

    preg_match( '/<span[^>]*data-cart-count[^>]*>/', $html, $badge );

    expect( $html )->toContain( 'class="apui-button" data-variant="ghost" data-color="neutral" data-size="sm"' )
        ->toContain( 'aria-controls="ecommerce-cart-drawer-content"' )
        ->toContain( 'data-scope="icon"' )
        ->not->toContain( 'h-5 w-5' )
        ->not->toContain( 'sm:inline' )
        ->and( $badge[0] )->toContain( 'data-variant="solid"' )->toContain( 'data-color="primary"' )->toContain( 'aria-hidden="true"' );
} );
