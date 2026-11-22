<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\BackInStockSubscription;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\Ecommerce\Services\PriceQuoteService;
use Tests\Browser\Support\Accessibility;
use Tests\Fixtures\ProductTypes\ConfiguredPrintProductType;

/*
 * The richer options form (#66), its cart line summary (#67), and the
 * "Notify me" signup (#155) in a real browser: keyboard use, the live
 * regions, and axe. They need engine 1.4.
 */

/**
 * A configured print's product page URL.
 */
function configuredPrintUrl(): string
{
    app( ProductTypeRegistry::class )->register( ConfiguredPrintProductType::KEY, new ConfiguredPrintProductType() );

    $print = Product::factory()->create( [ 'type' => ConfiguredPrintProductType::KEY, 'name' => 'Harbour print', 'slug' => 'harbour-print' ] );
    ProductPrice::factory()->forPriceable( $print )->create( [ 'currency' => 'USD', 'price_amount' => 6000, 'compare_at_amount' => null ] );
    setStock( $print, 10 );

    return route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $print->slug ] );
}

/**
 * A sold-out mug's product page URL.
 */
function soldOutMugUrl(): string
{
    $mug = makeProduct( 2800, [ 'name' => 'Sold-out mug', 'slug' => 'sold-out-mug' ] );
    setStock( $mug, 0 );

    return route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $mug->slug ] );
}

it( 'shows a field, disables a conflicting choice, and quotes the price as choices change', function (): void {
    if ( ! class_exists( PriceQuoteService::class ) ) {
        $this->markTestSkipped( 'Needs engine 1.4.' );
    }

    $page = visit( configuredPrintUrl() )
        ->assertNoJavaScriptErrors()
        ->assertMissing( '[data-option-field="mat"]' )
        ->keys( '[data-option-field="frame"] input[value="oak"]', 'Space' )
        ->assertPresent( '[data-option-field="mat"]' )
        ->assertSeeIn( '[data-quoted-price-value]', '$75.00 including options' )
        ->assertScript( 'document.querySelector( "[data-quoted-price-value]" ).getAttribute( "aria-live" )', 'polite' )
        ->click( '[data-choice-card="large"]' )
        ->assertSeeIn( '[data-quoted-price-value]', '$120.00 including options' )
        ->assertScript( 'document.querySelector( "[data-option-field=paper] input[value=rag]" ).disabled' )
        ->assertSee( 'Not available with 24 × 36' );

    Accessibility::assertAccessible( $page, '[data-purchase-form="options"]' );
} );

it( 'adds the configuration and summarizes it in the cart drawer', function (): void {
    if ( ! class_exists( PriceQuoteService::class ) ) {
        $this->markTestSkipped( 'Needs engine 1.4.' );
    }

    visit( configuredPrintUrl() )
        ->keys( '[data-option-field="frame"] input[value="ash"]', 'Space' )
        ->assertPresent( '[data-option-field="mat"]' )
        ->click( '[data-add-to-cart]' )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel', 'Frame: Natural ash · Size: 8 × 10 · Paper: Matte' );
} );

it( 'signs up for a sold-out product from the keyboard and announces it', function (): void {
    if ( ! engineHasBackInStock() ) {
        $this->markTestSkipped( 'Needs engine 1.4.' );
    }

    $page = visit( soldOutMugUrl() )
        ->assertNoJavaScriptErrors()
        ->assertSee( 'This product is sold out' )
        ->assertMissing( '[data-add-to-cart]' );

    Accessibility::assertAccessible( $page, '[data-notify-me]' );

    $page->type( '[data-notify-me-form] input[type=email]', 'not-an-email' )
        ->keys( '[data-notify-me-form] input[type=email]', 'Enter' )
        ->assertSee( 'Enter an email address like name@example.com.' )
        ->assertScript( 'document.querySelector( "[data-notify-me-form] input[type=email]" ).getAttribute( "aria-invalid" )', 'true' )
        ->assertScript( 'document.querySelector( "[data-notify-me-form] input[type=email]" ).getAttribute( "aria-describedby" ).includes( "-notify-email-error" )' )
        ->clear( '[data-notify-me-form] input[type=email]' )
        ->type( '[data-notify-me-form] input[type=email]', 'sam@example.com' )
        ->keys( '[data-notify-me-form] input[type=email]', 'Enter' )
        ->assertScript( '[ ...document.querySelectorAll( "[data-cart-announcement]" ) ].some( ( el ) => el.textContent.includes( "We\'ll email you when it\'s back." ) )' )
        ->assertPresent( '[data-notify-me-done]' );

    expect( BackInStockSubscription::query()->where( 'email', 'sam@example.com' )->exists() )->toBeTrue();
} );

it( 'lays the signup out right to left without overflow on a phone', function (): void {
    if ( ! engineHasBackInStock() ) {
        $this->markTestSkipped( 'Needs engine 1.4.' );
    }

    config()->set( 'artisanpack.ecommerce-storefront-livewire.storefront.rtl_locales', [ 'en' ] );

    visit( soldOutMugUrl() )
        ->on()->mobile()
        ->assertScript( 'document.documentElement.dir', 'rtl' )
        ->assertScript( 'document.documentElement.scrollWidth <= window.innerWidth' )
        ->assertVisible( '[data-notify-me-submit]' );
} );

it( 'reveals a field that depends on a quantity when the stepper changes it', function (): void {
    if ( ! class_exists( PriceQuoteService::class ) ) {
        $this->markTestSkipped( 'Needs engine 1.4.' );
    }

    visit( configuredPrintUrl() )
        ->assertMissing( '[data-option-field="copy_note"]' )
        ->click( '[data-option-field="copies"] button[aria-label^="Increase quantity"]' )
        ->assertPresent( '[data-option-field="copy_note"]' );
} );

it( 'lets the shopper pick a sold-out variant from the swatches and sign up for it', function (): void {
    if ( ! engineHasBackInStock() ) {
        $this->markTestSkipped( 'Needs engine 1.4.' );
    }

    makeVariableProduct( [
        'red/m'  => [ 'price' => 2500, 'stock' => 4 ],
        'blue/m' => [ 'price' => 2500, 'stock' => 0 ],
    ], [ 'slug' => 'sold-out-shirt' ], 'SOLD' );

    visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'sold-out-shirt' ] ) )
        ->assertPresent( 'label[data-sold-out]' )
        ->click( 'label[data-sold-out]' )
        ->click( 'label.ec-swatch:has-text("M")' )
        ->assertSee( 'This option is sold out' )
        ->assertPresent( '[data-notify-me-form]' );
} );
