<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\EcommerceStorefrontLivewireServiceProvider;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\PaymentDriverRegistry;

it( 'registers the core drivers', function (): void {
    expect( app( PaymentDriverRegistry::class )->all() )->toMatchArray( [
        'redirect'               => 'artisanpack-ecommerce-storefront-payment-redirect',
        'stripe-payment-element' => 'artisanpack-ecommerce-storefront-payment-stripe',
    ] );

    foreach ( EcommerceStorefrontLivewireServiceProvider::PAYMENT_DRIVERS as $component ) {
        expect( EcommerceStorefrontLivewireServiceProvider::LIVEWIRE_COMPONENTS )->toHaveKey( $component );
    }
} );

it( 'lets a satellite register (or replace) a driver', function (): void {
    $registry = app( PaymentDriverRegistry::class );

    $registry->register( 'paypal-buttons', 'paypal-storefront-buttons' );
    $registry->register( 'redirect', 'my-redirect' );

    expect( $registry->has( 'paypal-buttons' ) )->toBeTrue()
        ->and( $registry->component( 'paypal-buttons' ) )->toBe( 'paypal-storefront-buttons' )
        ->and( $registry->component( 'redirect' ) )->toBe( 'my-redirect' )
        ->and( $registry->component( 'square-web-payments' ) )->toBeNull()
        ->and( $registry->has( 'square-web-payments' ) )->toBeFalse();
} );

it( 'refuses an empty driver or component', function ( string $driver, string $component ): void {
    app( PaymentDriverRegistry::class )->register( $driver, $component );
} )->with( [
    'driver'    => [ ' ', 'component' ],
    'component' => [ 'driver', '' ],
] )->throws( InvalidArgumentException::class );

it( 'registers the drivers listed in config, over the core ones', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.payments.drivers', [
        'square-web-payments' => 'square-storefront-card',
        'redirect'            => 'host-redirect',
        ''                    => 'ignored',
        'bad'                 => [ 'not-a-string' ],
    ] );

    $provider = new EcommerceStorefrontLivewireServiceProvider( app() );
    ( fn () => $this->registerPaymentDrivers() )->call( $provider );

    $registry = app( PaymentDriverRegistry::class );

    expect( $registry->component( 'square-web-payments' ) )->toBe( 'square-storefront-card' )
        ->and( $registry->component( 'redirect' ) )->toBe( 'host-redirect' )
        ->and( $registry->component( 'stripe-payment-element' ) )->toBe( 'artisanpack-ecommerce-storefront-payment-stripe' )
        ->and( $registry->has( 'bad' ) )->toBeFalse()
        ->and( $registry->has( '' ) )->toBeFalse();
} );

it( 'leaves a driver a satellite registered first in place', function (): void {
    $registry = app( PaymentDriverRegistry::class );
    $registry->register( 'stripe-payment-element', 'satellite-stripe' );

    $provider = new EcommerceStorefrontLivewireServiceProvider( app() );
    ( fn () => $this->registerPaymentDrivers() )->call( $provider );

    expect( $registry->component( 'stripe-payment-element' ) )->toBe( 'satellite-stripe' );
} );
