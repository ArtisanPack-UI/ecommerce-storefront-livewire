<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;

beforeEach( function (): void {
    View::addNamespace( 'host', __DIR__ . '/../../Fixtures/views/host' );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.header.actions' );
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.layout.viteEntries' );
} );

it( 'renders pages inside the standalone layout by default', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertOk()
        ->assertSee( '<title>Shop &middot;', false )
        ->assertSee( 'Skip to content' )
        ->assertSee( 'id="ecommerce-storefront-content"', false )
        ->assertSee( 'role="search"', false )
        ->assertSee( 'data-header-action="cart"', false )
        ->assertSee( 'Find an order' )
        ->assertSee( 'window.toast', false )
        ->assertSee( 'data-ecommerce-cart-merge-prompt', false );
} );

it( 'links a guest to the host\'s sign-in route and a signed-in shopper to their account', function (): void {
    Route::get( 'login', static fn (): string => 'login' )->name( 'login' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertSee( 'Sign in' )
        ->assertSee( route( 'login' ), false );

    $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertSee( 'Account' )
        ->assertSee( route( 'artisanpack.ecommerce.account.dashboard' ), false )
        ->assertDontSee( 'Sign in' );
} );

it( 'leaves the sign-in link out when the host has no such route', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertOk()
        ->assertDontSee( 'data-header-action="account"', false );
} );

it( 'adopts the host layout named in storefront.layout', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.storefront.layout', 'host::layout' );

    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertOk()
        ->assertSee( '<title>Shop | Host</title>', false )
        ->assertSee( 'data-host-header', false )
        ->assertSee( 'data-ecommerce-catalog', false )
        ->assertSee( 'data-ecommerce-storefront-global', false )
        ->assertDontSee( 'id="ecommerce-storefront-content"', false );
} );

it( 'falls back to the package layout when storefront.layout is empty', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.storefront.layout', '' );

    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertOk()
        ->assertSee( 'id="ecommerce-storefront-content"', false );
} );

it( 'lets satellites add, replace, and remove header actions', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.header.actions', static function ( array $actions ): array {
        unset( $actions['cart'] );
        $actions['wishlist'] = new HtmlString( '<a href="/wishlist" data-header-action="wishlist">Wishlist</a>' );
        $actions['missing']  = 'host::no-such-view';

        return $actions;
    } );

    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertOk()
        ->assertSee( 'data-header-action="wishlist"', false )
        ->assertDontSee( 'data-header-action="cart"', false );
} );

it( 'loads the filtered Vite entries when a dev server runs, and none when the filter empties them', function (): void {
    $hot = tempnam( sys_get_temp_dir(), 'vite-hot' );
    file_put_contents( $hot, 'http://localhost:5173' );
    Vite::useHotFile( $hot );

    addFilter( 'ap.ecommerceStorefrontLivewire.layout.viteEntries', static fn (): array => [ 'resources/css/shop.css' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertSee( 'http://localhost:5173/resources/css/shop.css', false )
        ->assertDontSee( 'resources/js/app.js', false );

    removeAllFilters( 'ap.ecommerceStorefrontLivewire.layout.viteEntries' );
    addFilter( 'ap.ecommerceStorefrontLivewire.layout.viteEntries', static fn (): array => [] );

    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertDontSee( 'http://localhost:5173', false );

    @unlink( $hot );
} );

it( 'renders the global partial once even when it is included twice', function (): void {
    $html = view( 'host::double-global' )->render();

    expect( substr_count( $html, 'data-ecommerce-storefront-global' ) )->toBe( 1 );
} );

it( 'shows the cart count in the header', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertSee( 'Cart, 0 items' );
} );

it( 'ignores a search term that isn\'t a string', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) . '?q[]=lamp' )->assertOk()->assertSee( '&quot;q&quot;:&quot;&quot;', false );
    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) . '?q=lamp' )->assertSee( '&quot;q&quot;:&quot;lamp&quot;', false );
} );
