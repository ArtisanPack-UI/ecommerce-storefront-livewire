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

it( 'strikes through the compare-at price and reads "was / now" to screen readers on a sale', function (): void {
    $view = $this->blade( '<x-artisanpack-ec-price :price="$price" />', [ 'price' => plainPrice( 1900, 2500 ) ] );

    $view->assertSee( 'Sale price: was $25.00, now $19.00' )
        ->assertSee( '<del class="tabular-nums text-base-content/70" aria-hidden="true">$25.00</del>', false )
        ->assertSee( 'Sale' )
        ->assertSee( 'data-on-sale', false );
} );

it( 'hides the sale badge when asked', function (): void {
    $this->blade( '<x-artisanpack-ec-price :price="$price" :sale-badge="false" />', [ 'price' => plainPrice( 1900, 2500 ) ] )
        ->assertDontSee( 'badge-error', false );
} );

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
        ->assertSee( 'Sale price: was $25.00, now $19.00' );
} );
