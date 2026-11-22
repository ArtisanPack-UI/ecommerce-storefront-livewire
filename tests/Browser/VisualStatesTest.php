<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductRelation;

/*
 * Visual states the axe runs can't judge (2.0 review): which live regions
 * speak after filtering, the related-products chevrons in right to left,
 * the sticky header over focused controls, the selected swatch, the card
 * focus rings, and the related-products scroller's room for them.
 */

/**
 * The first storefront-visible demo product without variants.
 */
function visualProduct(): Product
{
    return Product::query()->storefrontVisible()->where( 'type', 'simple' )->whereDoesntHave( 'variants' )->orderBy( 'id' )->firstOrFail();
}

/**
 * A product page URL whose product has three related products.
 */
function productWithRelated(): string
{
    $products = Product::query()->storefrontVisible()->where( 'type', 'simple' )->whereDoesntHave( 'variants' )->orderBy( 'id' )->take( 4 )->get();
    $product  = $products->shift();

    foreach ( $products->values() as $position => $related ) {
        ProductRelation::query()->create( [ 'product_id' => $product->id, 'related_product_id' => $related->id, 'type' => 'related', 'position' => $position ] );
    }

    return route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] );
}

it( 'reports an emptied catalog once, from the results count, not again from the empty state (FE-5)', function (): void {
    ArtisanPackUI\Ecommerce\Models\ProductCategory::query()->create( [ 'name' => 'Nothing here', 'slug' => 'nothing-here' ] );

    visit( route( 'artisanpack.ecommerce.storefront.catalog', [ 'category' => 'nothing-here' ] ) )
        ->assertPresent( '[data-empty-reason="no-match"]' )
        ->assertScript( 'null === document.querySelector( "[data-empty-reason=\'no-match\']" ).closest( "[role=status]" )' )
        ->assertScript( 'null === document.querySelector( "[data-empty-reason=\'no-match\'] [role=status], [data-empty-reason=\'no-match\'][role=status]" )' )
        ->assertScript( 'document.querySelector( "[data-results-count]" ).getAttribute( "role" )', 'status' );
} );

it( 'points the related-products chevrons the way the list moves in right to left (FE-12)', function (): void {
    config()->set( 'app.locale', 'ar' );

    $page = visit( productWithRelated() )
        ->resize( 375, 812 )
        ->assertScript( 'document.documentElement.dir', 'rtl' );

    // The list loads lazily, once it scrolls into view.
    $page->script( 'window.scrollTo( 0, document.documentElement.scrollHeight ); true' );

    $page->assertPresent( '[data-related-previous] svg' )
        // Mirrored once, by the design system's `scale`; no second flip from a transform.
        ->assertScript( 'getComputedStyle( document.querySelector( "[data-related-previous] svg" ) ).scale', '-1 1' )
        ->assertScript( 'getComputedStyle( document.querySelector( "[data-related-previous] svg" ) ).transform', 'none' );
} );

it( 'keeps a control focused while tabbing back up the page below the sticky header (FE-13)', function (): void {
    $page = visit( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->resize( 1280, 720 )
        ->assertPresent( '[data-storefront-header]' );

    $page->script( 'window.scrollTo( 0, document.documentElement.scrollHeight ); true' );

    // Moves focus to a product link scrolled out above the viewport, as Shift+Tab back up the page does.
    $page->script( 'document.querySelector( "[data-ecommerce-catalog] .ec-product-card__name a" ).focus(); true' );

    $page->assertScript( <<<'JS'
        ( () => {
            const focused = document.activeElement;
            const header = document.querySelector( '[data-storefront-header]' );

            return focused.matches( '.ec-product-card__name a' ) && focused.getBoundingClientRect().top >= header.getBoundingClientRect().bottom;
        } )()
        JS );
} );

it( 'marks the selected swatch with more than a colour change (FE-14)', function (): void {
    makeVariableProduct( [
        'red/m'  => [ 'price' => 2500, 'stock' => 4 ],
        'blue/l' => [ 'price' => 2700, 'stock' => 4 ],
    ], [ 'slug' => 'swatch-shirt' ], 'SWATCH' );

    visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'swatch-shirt' ] ) )
        ->click( 'label.ec-swatch:has-text("M")' )
        ->assertScript( '"none" !== getComputedStyle( document.querySelector( ".ec-swatch:has( .ec-swatch__input:checked )" ) ).boxShadow' )
        ->assertScript( '"none" === getComputedStyle( document.querySelector( ".ec-swatch:not( :has( .ec-swatch__input:checked ) )" ) ).boxShadow' );
} );

it( 'rings a product card only when its name link has keyboard focus (FE-15)', function (): void {
    $page = visit( route( 'artisanpack.ecommerce.storefront.catalog' ) )->assertPresent( '.ec-product-card [data-quick-add]' );

    // After a key press, focus() counts as keyboard focus, so it matches :focus-visible.
    $page->keys( 'html > body', 'Shift' );
    $page->script( 'document.querySelector( ".ec-product-card [data-quick-add]" ).focus(); true' );

    $page->assertScript( 'document.activeElement.matches( "[data-quick-add]" )' )
        ->assertScript( 'document.activeElement.matches( ":focus-visible" )' )
        ->assertScript( '"none" === getComputedStyle( document.activeElement.closest( ".ec-product-card" ) ).boxShadow' );

    $page->script( 'document.activeElement.closest( ".ec-product-card" ).querySelector( ".ec-product-card__name a" ).focus(); true' );

    $page->assertScript( 'document.activeElement.matches( ".ec-product-card__name a" )' )
        ->assertScript( '"none" !== getComputedStyle( document.activeElement.closest( ".ec-product-card" ) ).boxShadow' );
} );

it( 'leaves room in the related-products scroller for a card\'s focus ring (FE-16)', function (): void {
    $page = visit( productWithRelated() )->resize( 375, 812 );

    $page->script( 'window.scrollTo( 0, document.documentElement.scrollHeight ); true' );

    $page->assertPresent( '.ec-related__list .ec-product-card' )
        ->assertScript( <<<'JS'
            ( () => {
                const list = document.querySelector( '.ec-related__list' ).getBoundingClientRect();
                const card = document.querySelector( '.ec-related__list .ec-product-card' ).getBoundingClientRect();

                return list.top <= card.top - 4 && list.left <= card.left - 4;
            } )()
            JS );
} );
