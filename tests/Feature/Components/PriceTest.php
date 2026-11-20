<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\TaxRate;
use ArtisanPackUI\Ecommerce\Pricing\DisplayPrice;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\Ecommerce\ValueObjects\Address;
use Money\Money;

/**
 * A display price without tax.
 */
function plainPrice( int $amount, ?int $compareAt = null, ?int $min = null, ?int $max = null ): DisplayPrice
{
    return new DisplayPrice(
        Money::USD( $amount ),
        null === $compareAt ? null : Money::USD( $compareAt ),
        null === $min ? null : Money::USD( $min ),
        null === $max ? null : Money::USD( $max ),
        Money::USD( $amount ),
        Money::USD( $amount ),
        false,
        null,
    );
}

it( 'renders a regular price', function (): void {
    $this->blade( '<x-artisanpack-ec-price :price="$price" />', [ 'price' => plainPrice( 1900 ) ] )
        ->assertSee( '$19.00' )
        ->assertDontSee( '<del', false )
        ->assertDontSee( 'Sale' );
} );

it( 'shows the sale price, then the original struck through, each labelled for screen readers', function (): void {
    $view = $this->blade( '<x-artisanpack-ec-price :price="$price" />', [ 'price' => plainPrice( 1900, 2500 ) ] );

    $view->assertSeeInOrder( [ 'data-tone="sale"', 'Sale price', '$19.00', '<del class="ec-price__was">', 'Original price', '$25.00' ], false )
        ->assertSee( '24% off' )
        ->assertSee( 'data-price-discount', false )
        ->assertSee( 'data-on-sale', false );
} );

it( 'hides the percentage-off badge when asked', function (): void {
    $this->blade( '<x-artisanpack-ec-price :price="$price" :sale-badge="false" />', [ 'price' => plainPrice( 1900, 2500 ) ] )
        ->assertSee( 'Sale price' )
        ->assertDontSee( 'data-price-discount', false )
        ->assertDontSee( '% off' );
} );

it( 'says "Free" for a zero price, in the success tone', function (): void {
    $this->blade( '<x-artisanpack-ec-price :price="$price" />', [ 'price' => plainPrice( 0 ) ] )
        ->assertSee( 'Free' )
        ->assertSee( 'data-tone="free"', false )
        ->assertDontSee( '$0.00' );
} );

it( 'shows a range as "From" and its lowest price when asked', function (): void {
    $this->blade( '<x-artisanpack-ec-price :price="$price" from />', [ 'price' => plainPrice( 3400, null, 3400, 5000 ) ] )
        ->assertSeeInOrder( [ 'ec-price__from', 'From', '$34.00' ], false )
        ->assertDontSee( '$50.00' );
} );

it( 'sizes the price for its context, falling back to md', function ( string $size, string $expected ): void {
    $this->blade( '<x-artisanpack-ec-price :price="$price" size="' . $size . '" />', [ 'price' => plainPrice( 1900 ) ] )
        ->assertSee( 'data-size="' . $expected . '"', false );
} )->with( [
    'small'   => [ 'sm', 'sm' ],
    'large'   => [ 'lg', 'lg' ],
    'unknown' => [ 'huge', 'md' ],
] );

it( 'shows a variable product\'s range with a spoken "from / to"', function (): void {
    $this->blade( '<x-artisanpack-ec-price :price="$price" />', [ 'price' => plainPrice( 1000, null, 1000, 2400 ) ] )
        ->assertSee( 'From $10.00 to $24.00' )
        ->assertSee( '$10.00 &ndash; $24.00', false );
} );

it( 'shows a single price when every variant costs the same', function (): void {
    $this->blade( '<x-artisanpack-ec-price :price="$price" />', [ 'price' => plainPrice( 1000, null, 1000, 1000 ) ] )
        ->assertDontSee( 'From' )
        ->assertSee( '$10.00' );
} );

it( 'says the price is unavailable when there is none', function (): void {
    $this->blade( '<x-artisanpack-ec-price :price="null" />' )
        ->assertSee( 'Price unavailable' );
} );

it( 'formats in the app locale', function (): void {
    app()->setLocale( 'de' );

    $this->blade( '<x-artisanpack-ec-price :price="$price" />', [ 'price' => plainPrice( 123456 ) ] )
        ->assertSee( '1.234,56' );
} );

it( 'labels a tax-inclusive store\'s price "incl." the tax, from the engine resolver', function (): void {
    config()->set( 'artisanpack.ecommerce.store.country', 'GB' );
    config()->set( 'artisanpack.ecommerce.tax.prices_include_tax', true );
    TaxRate::factory()->create( [ 'country_code' => 'GB', 'rate_ubps' => 200_000_000, 'label' => 'VAT' ] );

    $price = app( PriceDisplayResolver::class )->for( makeProduct( 1200 ), 'USD' );

    expect( $price->pricesIncludeTax )->toBeTrue();

    $this->blade( '<x-artisanpack-ec-price :price="$price" />', [ 'price' => $price ] )
        ->assertSee( '$12.00' )
        ->assertSee( 'incl. VAT' );
} );

it( 'labels a tax-exclusive store\'s price "excl." the tax', function (): void {
    config()->set( 'artisanpack.ecommerce.store.country', 'US' );
    TaxRate::factory()->create( [ 'country_code' => 'US', 'rate_ubps' => 100_000_000, 'label' => 'Sales tax' ] );

    $price = app( PriceDisplayResolver::class )->for( makeProduct( 1000 ), 'USD', Address::fromArray( [ 'country_code' => 'US' ] ) );

    $this->blade( '<x-artisanpack-ec-price :price="$price" />', [ 'price' => $price ] )
        ->assertSee( '$10.00' )
        ->assertSee( 'excl. Sales tax' );

    $this->blade( '<x-artisanpack-ec-price :price="$price" :show-tax="false" />', [ 'price' => $price ] )
        ->assertDontSee( 'Sales tax' );
} );

it( 'renders an engine sale price', function (): void {
    $price = app( PriceDisplayResolver::class )->for( makeProduct( 1900, [], 2500 ), 'USD' );

    $this->blade( '<x-artisanpack-ec-price :price="$price" />', [ 'price' => $price ] )
        ->assertSeeInOrder( [ 'Sale price', '$19.00', 'Original price', '$25.00' ] );
} );
