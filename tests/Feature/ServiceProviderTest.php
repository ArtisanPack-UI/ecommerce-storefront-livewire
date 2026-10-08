<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\EcommerceStorefrontLivewireServiceProvider;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

it( 'boots alongside the engine', function (): void {
    expect( $this->app->getProvider( EcommerceStorefrontLivewireServiceProvider::class ) )
        ->toBeInstanceOf( EcommerceStorefrontLivewireServiceProvider::class );
} );

it( 'merges the config under the artisanpack key', function (): void {
    expect( config( 'artisanpack.ecommerce-storefront-livewire.storefront' ) )->toBe( [
        'routes_enabled' => true,
        'route_prefix'   => 'shop',
        'middleware'     => [ 'web' ],
        'layout'         => 'ecommerce-storefront::layouts.app',
    ] )
        ->and( config( 'artisanpack.ecommerce-storefront-livewire.account' ) )->toBe( [
            'route_prefix' => 'account',
            'middleware'   => [ 'web', 'auth' ],
        ] )
        ->and( config( 'artisanpack.ecommerce-storefront-livewire.auth.login_route' ) )->toBe( 'login' )
        ->and( config( 'artisanpack.ecommerce-storefront-livewire.catalog' ) )->toBe( [
            'per_page'         => 24,
            'per_page_values'  => [ 12, 24, 48 ],
            'default_sort'     => 'newest',
            'show_stock_count' => false,
        ] )
        ->and( config( 'artisanpack.ecommerce-storefront-livewire.checkout.layout' ) )->toBe( 'multi_step' )
        ->and( config( 'artisanpack.ecommerce-storefront-livewire.payments.drivers' ) )->toBe( [] )
        ->and( config( 'artisanpack.ecommerce-storefront-livewire.visual_editor' ) )->toBe( [ 'blocks' => true, 'templates' => false ] );
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

it( 'registers the Blade components', function ( string $name ): void {
    expect( Blade::getClassComponentAliases() )->toHaveKey( 'artisanpack-ec-' . $name );
} )->with( array_keys( EcommerceStorefrontLivewireServiceProvider::BLADE_COMPONENTS ) );

it( 'registers the Livewire components', function ( string $name, string $class ): void {
    expect( Livewire::test( $name )->instance() )->toBeInstanceOf( $class );
} )->with( fn (): array => collect( EcommerceStorefrontLivewireServiceProvider::LIVEWIRE_COMPONENTS )->map( fn ( string $class, string $name ): array => [ $name, $class ] )->values()->all() );

it( 'makes the account middleware persistent, leaving out groups', function (): void {
    expect( EcommerceStorefrontLivewireServiceProvider::accountPersistentMiddleware() )->toBe( [ Authenticate::class ] );

    app()->getProvider( EcommerceStorefrontLivewireServiceProvider::class )->registerPersistentMiddleware();

    expect( app( PersistentMiddleware::class )->getPersistentMiddleware() )->toContain( Authenticate::class );
} );

it( 'resolves the current cart once per request', function (): void {
    expect( app( StorefrontCart::class ) )->toBe( app( StorefrontCart::class ) );
} );

it( 'registers the install command', function (): void {
    expect( Artisan::all() )->toHaveKey( 'ecommerce-storefront:install' );
} );

it( 'registers the publish tags', function ( string $tag ): void {
    expect( ServiceProvider::pathsToPublish( EcommerceStorefrontLivewireServiceProvider::class, $tag ) )->not->toBeEmpty();
} )->with( [ 'ecommerce-storefront-config', 'ecommerce-storefront-views', 'ecommerce-storefront-lang' ] );
