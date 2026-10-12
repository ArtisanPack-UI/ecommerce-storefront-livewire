<?php

declare( strict_types=1 );

use Tests\Browser\Support\Locators;

/*
 * Keyboard-only use and screen reader announcements (#43): focus moves to
 * the drawer, the next checkout step, and the error summary, and live
 * regions announce cart updates, result counts, and step changes. Focus
 * settles just after Livewire's update, so the checks use assertScript,
 * which retries until it holds.
 */

/**
 * JavaScript: whether the focused element sits inside `$selector`.
 */
function focusIsIn( string $selector ): string
{
    return 'document.activeElement?.closest( ' . json_encode( $selector ) . ' ) !== null';
}

it( 'skips to the content from the first tab stop', function (): void {
    visit( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->keys( 'html > body', 'Tab' )
        ->assertScript( 'document.activeElement?.getAttribute( "href" )', '#ecommerce-storefront-content' )
        ->keys( 'a[href="#ecommerce-storefront-content"]', 'Enter' )
        ->assertScript( 'document.activeElement?.id', 'ecommerce-storefront-content' );
} );

it( 'adds to the cart from the keyboard, moves focus into the drawer, and returns it on Escape', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Keyboard mug', 'slug' => 'keyboard-mug' ] );

    visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) )
        ->keys( '[data-add-to-cart]', 'Enter' )
        ->assertScript( '[ ...document.querySelectorAll( "[data-cart-announcement]" ) ].some( ( el ) => el.textContent.includes( "Added to your cart." ) )' )
        ->assertScript( focusIsIn( '#ecommerce-cart-drawer-panel' ) )
        ->keys( '#ecommerce-cart-drawer-panel [data-cart-drawer-close]', 'Escape' )
        ->assertScript( 'document.activeElement?.hasAttribute( "data-add-to-cart" )' );
} );

it( 'opens the cart drawer from the header button and returns focus to it', function (): void {
    checkoutCart( [ makeProduct( 1900, [ 'name' => 'Drawer mug' ] ) ] );

    visit( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->keys( '[data-header-action="cart"]', 'Enter' )
        ->assertScript( focusIsIn( '#ecommerce-cart-drawer-panel' ) )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel', 'Drawer mug' )
        ->keys( '#ecommerce-cart-drawer-panel [data-cart-drawer-close]', 'Enter' )
        ->assertScript( 'document.activeElement?.dataset.headerAction', 'cart' );
} );

it( 'chooses a variant with the arrow keys and announces what changed', function (): void {
    makeVariableProduct( [
        'red/m'  => [ 'price' => 2500, 'stock' => 4 ],
        'blue/m' => [ 'price' => 2900, 'stock' => 4 ],
    ], [ 'slug' => 'keyboard-shirt' ], 'KEYS' );

    visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'keyboard-shirt' ] ) )
        ->keys( Locators::label( 'Red' ), 'Space' )
        ->assertScript( 'document.activeElement?.checked' )
        ->keys( Locators::label( 'Red' ), 'ArrowRight' )
        ->assertScript( 'document.activeElement?.labels?.[ 0 ]?.textContent.trim()', 'Blue' )
        ->assertScript( 'document.activeElement?.checked' )
        ->keys( Locators::label( 'M' ), 'Space' )
        ->assertSee( '$29.00' )
        ->assertScript( 'document.querySelector( "[data-selection-notice]" ).getAttribute( "aria-live" )', 'polite' );
} );

it( 'announces the result count when a filter changes', function (): void {
    $onSale = makeProduct( 1500, [ 'name' => 'Sale mug' ], 1900 );

    $page = visit( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertPresent( '[data-product-card="' . $onSale->id . '"]' );

    $before = trim( (string) $page->text( '[data-results-count]' ) );

    $page->keys( '[data-filters-sidebar] >> internal:label=/^\\s*On sale/', 'Space' )
        ->assertScript( 'document.querySelector( "[data-results-count]" ).textContent.trim() !== ' . json_encode( $before ) );

    expect( $page->attribute( '[data-results-count]', 'aria-live' ) )->toBe( 'polite' )
        ->and( trim( (string) $page->text( '[data-results-count]' ) ) )->toMatch( '/^\\d+ products?$/' );
} );

it( 'focuses the error summary, then the next step, and announces the step', function (): void {
    checkoutCart();

    visit( route( 'artisanpack.ecommerce.storefront.checkout' ) )
        ->keys( '[data-checkout-contact-submit]', 'Enter' )
        ->assertScript( 'document.activeElement?.hasAttribute( "data-checkout-errors" )' )
        ->assertSeeIn( '[data-checkout-errors]', 'There is 1 problem to fix:' )
        ->keys( '[data-checkout-error="email"]', 'Enter' )
        ->assertScript( focusIsIn( '[data-field="email"]' ) )
        ->type( Locators::label( 'Email' ), 'ada@example.test' )
        ->keys( Locators::label( 'Email' ), 'Enter' )
        ->assertScript( 'document.activeElement?.id', 'ec-checkout-step-address' )
        ->assertScript( 'document.querySelector( "[data-checkout-announcement]" ).textContent.trim().length > 0' );
} );

it( 'moves through header search suggestions with the arrow keys and closes on Escape', function (): void {
    makeProduct( 1900, [ 'name' => 'Arrow key lamp' ] );

    visit( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->typeSlowly( 'input[role="combobox"]', 'Arrow key', 20 )
        ->assertSeeIn( '#ecommerce-header-search-results', 'Arrow key lamp' )
        ->keys( 'input[role="combobox"]', 'ArrowDown' )
        ->assertScript( 'document.querySelector( "input[role=combobox]" ).getAttribute( "aria-activedescendant" )?.length > 0' )
        ->keys( 'input[role="combobox"]', 'Escape' )
        ->assertAttribute( 'input[role="combobox"]', 'aria-expanded', 'false' );
} );

it( 'shows where focus is on a product card link', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Outline mug' ] );
    $link    = '[data-product-card="' . $product->id . '"] [data-product-link]';

    visit( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->keys( 'html > body', 'Tab' )
        ->keys( $link, 'Shift' )
        ->assertScript( 'document.activeElement?.matches( ' . json_encode( $link . ':focus-visible' ) . ' )' )
        ->assertScript( 'getComputedStyle( document.activeElement ).outlineStyle !== "none"' );
} );

it( 'announces why an item could not be added', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Refused mug', 'slug' => 'refused-mug' ] );

    visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) )
        ->type( '[data-quantity] input', '0' )
        ->keys( '[data-add-to-cart]', 'Enter' )
        ->assertScript( 'document.querySelector( "[data-cart-error-announcement]" )?.getAttribute( "role" )', 'alert' )
        ->assertSeeIn( '[data-cart-error-announcement]', 'Not added to your cart: Add at least 1.' )
        ->assertScript( 'document.getElementById( document.querySelector( "[data-quantity] input" ).getAttribute( "aria-describedby" ) )?.textContent.trim()', 'Add at least 1.' );
} );

it( 'focuses the error summary after an empty review submit', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Reviewed mug', 'slug' => 'reviewed-mug' ] );

    visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) )
        ->click( '[data-review-open]' )
        ->click( '[data-review-submit]' )
        ->assertScript( 'document.activeElement?.hasAttribute( "data-error-summary" )' )
        ->assertSeeIn( '[data-error-summary]', 'Choose a rating.' )
        ->click( '[data-error-summary-link="rating"]' )
        ->assertScript( focusIsIn( '[data-field-key="rating"]' ) );
} );

it( 'names the quick-add button starting with its visible text', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Quick mug' ] );

    visit( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertPresent( '[data-quick-add="' . $product->id . '"]' )
        ->assertScript( 'document.querySelector( \'[data-quick-add="' . $product->id . '"]\' ).textContent.replace( /\\s+/g, " " ).trim()', 'Add to cart: Quick mug' )
        ->assertPresent( 'internal:role=button[name=/^Add to cart\\s*:\\s*Quick mug$/]' );
} );

it( 'keeps focus in the drawer when a line is removed, then on the restored line after undo', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Removable mug', 'slug' => 'removable-mug' ] );

    visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) )
        ->keys( '[data-add-to-cart]', 'Enter' )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel', 'Removable mug' )
        ->keys( '#ecommerce-cart-drawer-panel [data-cart-line-remove]', 'Enter' )
        ->assertPresent( '#ecommerce-cart-drawer-panel [data-cart-undo-button]' )
        ->assertScript( 'document.activeElement?.hasAttribute( "data-cart-undo-button" ) && document.activeElement.closest( "#ecommerce-cart-drawer-panel" ) !== null' )
        ->keys( '#ecommerce-cart-drawer-panel [data-cart-undo-button]', 'Enter' )
        ->assertPresent( '#ecommerce-cart-drawer-panel [data-cart-line-remove]' )
        ->assertScript( 'document.activeElement?.hasAttribute( "data-cart-line-remove" ) && document.activeElement.closest( "#ecommerce-cart-drawer-panel" ) !== null' );
} );

it( 'moves focus into the review form and back to its toggle on cancel', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Toggle mug', 'slug' => 'toggle-mug' ] );

    visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) )
        ->assertAttribute( '[data-review-open]', 'aria-expanded', 'false' )
        ->keys( '[data-review-open]', 'Enter' )
        ->assertScript( 'document.activeElement?.id', 'ec-review-form-' . $product->id . '-heading' )
        ->assertAttribute( '[data-review-open]', 'aria-expanded', 'true' )
        ->keys( '[data-review-cancel]', 'Enter' )
        ->assertNotPresent( '[data-review-form]' )
        ->assertScript( 'document.activeElement?.hasAttribute( "data-review-open" )' )
        ->assertAttribute( '[data-review-open]', 'aria-expanded', 'false' );
} );

it( 'moves focus to "Add an address" after deleting one', function (): void {
    [ , $customer ] = shopper();

    $customer->addresses()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'address1' => '1 Main Street', 'city' => 'Springfield', 'region_code' => 'IL', 'postal_code' => '62701', 'country_code' => 'US' ] );

    visit( route( 'artisanpack.ecommerce.account.addresses' ) )
        ->click( '[data-account-address-delete]' )
        ->click( '[data-account-address-delete-confirm]' )
        ->assertNotPresent( '[data-account-address]' )
        ->assertScript( 'document.activeElement?.hasAttribute( "data-account-address-add" )' );
} );
