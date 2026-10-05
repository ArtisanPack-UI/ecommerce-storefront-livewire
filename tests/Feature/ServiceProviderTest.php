<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\EcommerceStorefrontLivewireServiceProvider;
use Illuminate\Support\ServiceProvider;

it( 'boots alongside the engine', function (): void {
    expect( $this->app->getProvider( EcommerceStorefrontLivewireServiceProvider::class ) )
        ->toBeInstanceOf( EcommerceStorefrontLivewireServiceProvider::class );
} );

it( 'merges the config under the artisanpack key', function (): void {
    expect( config( 'artisanpack.ecommerce-storefront-livewire.storefront' ) )->toBe( [
        'routes_enabled' => true,
        'route_prefix'   => 'shop',
        'middleware'     => [ 'web' ],
    ] )
        ->and( config( 'artisanpack.ecommerce-storefront-livewire.account' ) )->toBe( [
            'route_prefix' => 'account',
            'middleware'   => [ 'web', 'auth' ],
        ] );
} );

it( 'registers itself with the satellite registry', function (): void {
    $registry = $this->app->make( SatelliteRegistry::class );

    expect( $registry->has( EcommerceStorefrontLivewireServiceProvider::PACKAGE_NAME ) )->toBeTrue()
        ->and( $registry->isActive( EcommerceStorefrontLivewireServiceProvider::PACKAGE_NAME ) )->toBeTrue();

    $descriptor = $registry->get( EcommerceStorefrontLivewireServiceProvider::PACKAGE_NAME );

    expect( $descriptor->version )->toBe( EcommerceStorefrontLivewireServiceProvider::VERSION )
        ->and( $descriptor->configKeys )->toBe( [ 'artisanpack.ecommerce-storefront-livewire' ] )
        ->and( $descriptor->tables )->toBe( [] )
        ->and( $descriptor->migrationPaths )->toBe( [] );
} );

it( 'registers the view namespace', function (): void {
    expect( $this->app['view']->getFinder()->getHints() )->toHaveKey( 'ecommerce-storefront' );
} );

it( 'registers the publish tags', function ( string $tag ): void {
    expect( ServiceProvider::pathsToPublish( EcommerceStorefrontLivewireServiceProvider::class, $tag ) )->not->toBeEmpty();
} )->with( [ 'ecommerce-storefront-config', 'ecommerce-storefront-views', 'ecommerce-storefront-lang' ] );
