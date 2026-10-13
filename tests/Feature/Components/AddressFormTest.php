<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

beforeEach( function (): void {
    // Normally shared by the `web` group's ShareErrorsFromSession.
    View::share( 'errors', new ViewErrorBag() );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.address.regions' );
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.address.postcodeExamples' );
} );

it( 'binds every field under the model with its autocomplete token', function (): void {
    $view = $this->blade( '<x-artisanpack-ec-address-form model="shipping" />' );

    foreach ( [
        'shipping.first_name'   => 'given-name',
        'shipping.last_name'    => 'family-name',
        'shipping.company'      => 'organization',
        'shipping.phone'        => 'tel',
        'shipping.address1'     => 'address-line1',
        'shipping.address2'     => 'address-line2',
        'shipping.city'         => 'address-level2',
        'shipping.region'       => 'address-level1',
        'shipping.postal_code'  => 'postal-code',
        'shipping.country_code' => 'country',
    ] as $model => $token ) {
        $view->assertSee( '"' . $model . '"', false )->assertSee( 'autocomplete="' . $token . '"', false );
    }

    $view->assertSee( 'wire:model.live="shipping.country_code"', false )
        ->assertSee( 'wire:model="shipping.city"', false )
        ->assertSee( 'type="tel"', false );
} );

it( 'prefixes autocomplete tokens with the section', function (): void {
    $this->blade( '<x-artisanpack-ec-address-form model="billing" section="billing" />' )
        ->assertSee( 'autocomplete="billing postal-code"', false )
        ->assertSee( 'autocomplete="billing country"', false );
} );

it( 'offers a state select and ZIP code hint for the United States', function (): void {
    $this->blade( '<x-artisanpack-ec-address-form model="shipping" country="us" />' )
        ->assertSee( 'wire:model.live="shipping.region_code"', false )
        ->assertSee( 'Illinois' )
        ->assertSee( 'State' )
        ->assertSee( 'ZIP code' )
        ->assertSee( 'For example: 12345' )
        ->assertDontSee( '"shipping.region"', false );
} );

it( 'offers province and territory names for Canada', function (): void {
    $this->blade( '<x-artisanpack-ec-address-form model="shipping" country="CA" />' )
        ->assertSee( 'Province or territory' )
        ->assertSee( 'Quebec' )
        ->assertSee( 'For example: K1A 0B1' );
} );

it( 'types the region freely and hints the postcode elsewhere', function ( string $country, string $label, ?string $hint ): void {
    $view = $this->blade( '<x-artisanpack-ec-address-form model="shipping" :country="$country" />', [ 'country' => $country ] );

    $view->assertSee( 'wire:model="shipping.region"', false )
        ->assertSee( $label );

    null === $hint ? $view->assertDontSee( 'For example:' ) : $view->assertSee( $hint );
} )->with( [
    'France'         => [ 'FR', 'Postal code', 'For example: 75001' ],
    'United Kingdom' => [ 'GB', 'Postcode', 'For example: SW1A 1AA' ],
    'Kenya'          => [ 'KE', 'Postal code', null ],
] );

it( 'binds text fields live on blur when asked', function (): void {
    $this->blade( '<x-artisanpack-ec-address-form model="form.address" live />' )
        ->assertSee( 'wire:model.live.blur="form.address.address1"', false )
        ->assertSee( 'wire:model.live.blur="form.address.postal_code"', false );
} );

it( 'leaves out the name, company, and phone fields when asked', function (): void {
    $this->blade( '<x-artisanpack-ec-address-form model="billing" :with-name="false" />' )
        ->assertDontSee( '"billing.first_name"', false )
        ->assertDontSee( '"billing.phone"', false )
        ->assertSee( '"billing.address1"', false );
} );

it( 'renders the legend and lists countries by localized name', function (): void {
    app()->setLocale( 'de' );

    $this->blade( '<x-artisanpack-ec-address-form model="shipping" legend="Lieferadresse" />' )
        ->assertSee( '<legend', false )
        ->assertSee( 'Lieferadresse' )
        ->assertSee( 'Deutschland' );
} );

it( 'lets a store add regions and postcode examples', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.address.regions', static fn ( array $regions ): array => $regions + [ 'NZ' => [ 'AUK' => 'Auckland' ] ] );
    addFilter( 'ap.ecommerceStorefrontLivewire.address.postcodeExamples', static fn ( array $examples ): array => [ ...$examples, 'KE' => '00100' ] );

    $this->blade( '<x-artisanpack-ec-address-form model="shipping" country="NZ" />' )->assertSee( 'Auckland' );
    $this->blade( '<x-artisanpack-ec-address-form model="shipping" country="KE" />' )->assertSee( 'For example: 00100' );
} );
