<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutPlacement;
use Tests\Browser\Support\Accessibility;
use Tests\Browser\Support\Locators;

/*
 * axe on every storefront and account screen (#43), against the seeded
 * demo store. Known component-library failures are listed in
 * Accessibility::LIBRARY_ISSUES.
 */

/**
 * The first storefront-visible demo product without variants.
 */
function demoProduct(): Product
{
    return Product::query()->storefrontVisible()->where( 'type', 'simple' )->whereDoesntHave( 'variants' )->orderBy( 'id' )->firstOrFail();
}

it( 'has no accessibility violations for shoppers', function ( Closure $url ): void {
    Accessibility::assertAccessible( visit( $url() )->assertNoJavaScriptErrors() );
} )->with( [
    'catalog'          => [ fn (): string => route( 'artisanpack.ecommerce.storefront.catalog' ) ],
    'category'         => [ function (): string {
        $category = ProductCategory::query()->create( [ 'name' => 'Kitchen', 'slug' => 'kitchen', 'description' => 'Pans, mugs, and more.' ] );
        $category->products()->attach( demoProduct()->id );

        return (string) app( CategoryPaths::class )->url( $category->id );
    } ],
    'tag'              => [ function (): string {
        $tag = ProductTag::query()->create( [ 'name' => 'Gifts', 'slug' => 'gifts' ] );
        $tag->products()->attach( demoProduct()->id );

        return route( 'artisanpack.ecommerce.storefront.tag', [ 'tag' => 'gifts' ] );
    } ],
    'simple product'   => [ fn (): string => route( 'artisanpack.ecommerce.storefront.product', [ 'product' => demoProduct()->slug ] ) ],
    'variable product' => [ fn (): string => route( 'artisanpack.ecommerce.storefront.product', [ 'product' => makeVariableProduct( [ 'red/m' => [ 'price' => 2500, 'stock' => 4 ], 'blue/l' => [ 'price' => 2700, 'stock' => 0 ] ], [ 'slug' => 'linen-shirt' ], 'A11Y' )['product']->slug ] ) ],
    'search'           => [ fn (): string => route( 'artisanpack.ecommerce.storefront.search', [ 'q' => 'shirt' ] ) ],
    'empty search'     => [ fn (): string => route( 'artisanpack.ecommerce.storefront.search', [ 'q' => 'zzzz-nothing' ] ) ],
    'empty cart'       => [ fn (): string => route( 'artisanpack.ecommerce.storefront.cart' ) ],
    'order lookup'     => [ fn (): string => route( 'artisanpack.ecommerce.storefront.lookup' ) ],
    'guest order view' => [ fn (): string => route( 'artisanpack.ecommerce.storefront.order-view', [ 'token' => OrderViewToken::for( placedOrder() ) ] ) ],
    'confirmation'     => [ fn (): string => (string) CheckoutPlacement::confirmationUrl( placedOrder() ) ],
] );

it( 'has no accessibility violations in a filled cart and at each checkout step', function (): void {
    $page = visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => demoProduct()->slug ] ) )
        ->click( '[data-add-to-cart]' )
        ->assertSee( 'Your cart' );

    Accessibility::assertAccessible( $page );

    $page->navigate( route( 'artisanpack.ecommerce.storefront.cart' ) )->assertSee( 'Order summary' );
    Accessibility::assertAccessible( $page );

    $page->navigate( route( 'artisanpack.ecommerce.storefront.checkout' ) )->assertPresent( '[data-checkout-contact-form]' );
    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations on the account screens', function ( string $route, Closure $parameters ): void {
    [ , $customer ] = shopper();

    Accessibility::assertAccessible(
        visit( route( 'artisanpack.ecommerce.account.' . $route, $parameters( $customer ) ) )->assertNoJavaScriptErrors(),
    );
} )->with( [
    'dashboard' => [ 'dashboard', fn (): array => [] ],
    'orders'    => [ 'orders.index', function ( $customer ): array {
        placedOrder( [], $customer );

        return [];
    } ],
    'order'     => [ 'orders.show', fn ( $customer ): array => [ 'order' => placedOrder( [], $customer )->id ] ],
    'addresses' => [ 'addresses', fn (): array => [] ],
    'downloads' => [ 'downloads', function ( $customer ): array {
        ArtisanPackUI\Ecommerce\Models\LicenseKey::factory()->create( [ 'order_item_id' => placedOrder( [], $customer )->items->first()->id, 'is_revoked' => true, 'revoked_at' => now() ] );

        return [];
    } ],
    'profile'   => [ 'profile', fn (): array => [] ],
    'claim'     => [ 'claim', fn (): array => [] ],
] );

it( 'has no accessibility violations when showing errors', function (): void {
    $page = visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => demoProduct()->slug ] ) )
        ->click( '[data-add-to-cart]' )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel', demoProduct()->name )
        ->navigate( route( 'artisanpack.ecommerce.storefront.cart' ) )
        ->type( Locators::field( 'ec-cart-coupon' ), 'NOT-A-CODE' )
        ->click( '[data-cart-coupon] button[type="submit"]' )
        ->assertPresent( '[data-cart-coupon] [data-field-error]' );

    Accessibility::assertAccessible( $page );

    $page->navigate( route( 'artisanpack.ecommerce.storefront.checkout' ) )
        ->click( '[data-checkout-contact-submit]' )
        ->assertPresent( '[data-checkout-errors]' );

    Accessibility::assertAccessible( $page );

    $page->navigate( route( 'artisanpack.ecommerce.storefront.lookup' ) )
        ->type( Locators::field( 'email' ), 'nobody@example.test' )
        ->type( Locators::field( 'orderNumber' ), 'NOPE' )
        ->click( '[data-order-lookup-form] button[type="submit"]' )
        ->assertPresent( '[data-order-lookup-failure]' );

    Accessibility::assertAccessible( $page );
} );

it( 'still reports a missing label inside the page content (the drawer exclusion is narrow)', function (): void {
    $page = visit( route( 'artisanpack.ecommerce.storefront.catalog' ) )->assertPresent( '#ecommerce-storefront-content' );

    $page->script( 'document.getElementById( "ecommerce-storefront-content" ).insertAdjacentHTML( "afterbegin", "<input type=\"text\" data-probe>" )' );

    expect( fn () => Accessibility::assertAccessible( $page ) )
        ->toThrow( PHPUnit\Framework\AssertionFailedError::class, '(label)' );
} );

it( 'links a field to its error and hint, so a screen reader reads both', function (): void {
    checkoutCart();

    visit( route( 'artisanpack.ecommerce.storefront.checkout' ) )
        ->click( '[data-checkout-contact-submit]' )
        ->assertPresent( '[data-checkout-errors]' )
        ->assertAttribute( '[data-field="email"] input', 'aria-invalid', 'true' )
        ->assertScript( <<<'JS'
            ( () => {
                const input = document.querySelector( '[data-field="email"] input' );
                const [ error, hint ] = input.getAttribute( 'aria-describedby' ).split( ' ' ).map( ( id ) => document.getElementById( id )?.textContent.trim() );

                return error?.length > 0 && document.querySelector( '[data-checkout-error="email"]' ).textContent.includes( error ) && hint === "We'll send your order confirmation here.";
            } )()
            JS );
} );
