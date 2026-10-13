<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use Tests\Browser\Support\Accessibility;
use Tests\Browser\Support\Locators;

/*
 * The storefront on a phone (390×844, and 320px for reflow): axe on the
 * screens whose layout changes below lg (the filters drawer, the collapsed
 * checkout summary), keyboard use of the filters drawer, and the header.
 */

/**
 * Opens `$url` in a 390×844 viewport.
 */
function onPhone( string $url, int $width = 390, int $height = 844 ): object
{
    return visit( $url )->resize( $width, $height );
}

/**
 * JavaScript: records every element that takes focus, for the tab-order
 * checks.
 */
function recordFocus(): string
{
    return 'window.ecFocused = []; window.addEventListener( "focusin", ( event ) => window.ecFocused.push( event.target.matches( ".drawer-toggle" ) ? "drawer-toggle" : ( event.target.getAttribute( "aria-label" ) ?? event.target.textContent.trim().slice( 0, 30 ) ) ) ); true';
}

/**
 * JavaScript: whether the filters drawer has finished sliding and fading
 * in, so axe sees its final colours.
 */
function filtersDrawerShown(): string
{
    return '( ( panel ) => [ "none", "0%", "0px" ].includes( getComputedStyle( panel ).translate ) && getComputedStyle( panel.closest( ".drawer-side" ) ).opacity === "1" )( document.querySelector( "[data-filters-drawer]" ) )';
}

/**
 * The first storefront-visible demo product without variants.
 */
function phoneProduct(): Product
{
    return Product::query()->storefrontVisible()->where( 'type', 'simple' )->whereDoesntHave( 'variants' )->orderBy( 'id' )->firstOrFail();
}

it( 'has no accessibility violations on a phone', function ( Closure $open ): void {
    Accessibility::assertAccessible( $open()->assertNoJavaScriptErrors() );
} )->with( [
    'catalog'              => [ fn (): object => onPhone( route( 'artisanpack.ecommerce.storefront.catalog' ) ) ],
    'catalog with filters' => [ fn (): object => onPhone( route( 'artisanpack.ecommerce.storefront.catalog' ) )->click( '[data-filters-open]' )->assertScript( 'document.activeElement?.id', 'ecommerce-catalog-filters-title' )->assertScript( filtersDrawerShown() ) ],
    'product'              => [ fn (): object => onPhone( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => phoneProduct()->slug ] ) ) ],
] );

it( 'has no accessibility violations at each checkout step on a phone, with the summary open', function (): void {
    checkoutCart( [ phoneProduct() ] );
    checkoutShipping( [ 'Standard shipping' => 500 ] );

    $page = onPhone( route( 'artisanpack.ecommerce.storefront.checkout' ) )
        ->click( '[data-checkout-summary-toggle]' )
        ->assertVisible( '[data-checkout-summary-body]' );

    Accessibility::assertAccessible( $page );

    $page->type( Locators::label( 'Email' ), 'phone@example.test' )
        ->click( '[data-checkout-contact-submit]' )
        ->assertPresent( '[data-checkout-address-form]' );

    Accessibility::assertAccessible( $page );

    $page->select( Locators::field( 'shipping.country_code' ), 'US' )
        ->type( Locators::field( 'shipping.first_name' ), 'Ada' )
        ->type( Locators::field( 'shipping.last_name' ), 'Lovelace' )
        ->type( Locators::field( 'shipping.address1' ), '1 Main Street' )
        ->type( Locators::field( 'shipping.city' ), 'Springfield' )
        ->select( Locators::field( 'shipping.region_code' ), 'IL' )
        ->type( Locators::field( 'shipping.postal_code' ), '62701' )
        ->click( '[data-checkout-address-submit]' )
        ->assertPresent( '[data-checkout-shipping-form]' );

    Accessibility::assertAccessible( $page );

    $page->click( 'internal:label=/Standard shipping/' )
        ->click( '[data-checkout-shipping-submit]' )
        ->assertPresent( '[data-checkout-review-form]' );

    Accessibility::assertAccessible( $page );

    $page->click( '[data-checkout-review-submit]' )
        ->assertPresent( '[data-test-card-pay]' );

    Accessibility::assertAccessible( $page );
} );

it( 'opens and closes the filters by keyboard, returning focus to the button', function (): void {
    onPhone( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->keys( '[data-filters-open]', 'Enter' )
        ->assertScript( 'document.activeElement?.id', 'ecommerce-catalog-filters-title' )
        ->keys( '#ecommerce-catalog-filters-title', 'Escape' )
        ->assertScript( 'document.activeElement?.hasAttribute( "data-filters-open" )' )
        ->keys( '[data-filters-open]', 'Enter' )
        ->assertScript( 'document.activeElement?.id', 'ecommerce-catalog-filters-title' )
        ->click( '[data-filters-close]' )
        ->assertScript( 'document.activeElement?.hasAttribute( "data-filters-open" )' );
} );

it( 'never tabs onto a drawer toggle', function (): void {
    $page = onPhone( route( 'artisanpack.ecommerce.storefront.catalog' ) )->assertPresent( '[data-filters-open]' );

    $page->script( recordFocus() );
    $page->keys( 'html > body', array_fill( 0, 40, 'Tab' ) );

    $closed = $page->script( 'window.ecFocused' );

    $page->keys( '[data-filters-open]', 'Enter' )
        ->assertScript( 'document.activeElement?.id', 'ecommerce-catalog-filters-title' );

    $page->script( recordFocus() );
    $page->keys( '#ecommerce-catalog-filters-title', array_fill( 0, 30, 'Tab' ) );

    expect( $closed )->not->toBeEmpty()->not->toContain( 'drawer-toggle' )
        ->and( $page->script( 'window.ecFocused' ) )->not->toBeEmpty()->not->toContain( 'drawer-toggle' );
} );

it( 'has a search link in the header on a phone', function (): void {
    onPhone( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertVisible( '[data-header-search-link]' )
        ->click( '[data-header-search-link]' )
        ->assertPathIs( '/shop/search' );
} );

it( 'does not scroll sideways at 320px', function ( Closure $url ): void {
    Illuminate\Support\Facades\Route::get( 'login', static fn (): string => 'login' )->name( 'login' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    checkoutCart( [ phoneProduct() ], 3 );

    onPhone( $url(), 320, 640 )
        ->assertScript( 'document.documentElement.scrollWidth <= document.documentElement.clientWidth' );
} )->with( [
    'catalog'  => [ fn (): string => route( 'artisanpack.ecommerce.storefront.catalog' ) ],
    'product'  => [ fn (): string => route( 'artisanpack.ecommerce.storefront.product', [ 'product' => phoneProduct()->slug ] ) ],
    'search'   => [ fn (): string => route( 'artisanpack.ecommerce.storefront.search', [ 'q' => 'a' ] ) ],
    'cart'     => [ fn (): string => route( 'artisanpack.ecommerce.storefront.cart' ) ],
    'checkout' => [ fn (): string => route( 'artisanpack.ecommerce.storefront.checkout' ) ],
    'account'  => [ function (): string {
        shopper();

        return route( 'artisanpack.ecommerce.account.dashboard' );
    } ],
] );
