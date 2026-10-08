<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry;
use Tests\Fixtures\ProductTypes\EngravedProductType;

it( 'resolves the core forms by product type', function ( string $type, string $component ): void {
    expect( app( ProductFormRegistry::class )->for( Product::factory()->create( [ 'type' => $type ] ) ) )->toBe( $component );
} )->with( [
    'simple'   => [ 'simple', 'artisanpack-ecommerce-storefront-product-form-simple' ],
    'variable' => [ 'variable', 'artisanpack-ecommerce-storefront-product-form-variable' ],
    'grouped'  => [ 'grouped', 'artisanpack-ecommerce-storefront-product-form-grouped' ],
    'bundled'  => [ 'bundled', 'artisanpack-ecommerce-storefront-product-form-bundled' ],
    'digital'  => [ 'digital', 'artisanpack-ecommerce-storefront-product-form-digital' ],
] );

it( 'lets a satellite register (or replace) a form', function (): void {
    app( ProductTypeRegistry::class )->register( EngravedProductType::KEY, new EngravedProductType() );

    $registry = app( ProductFormRegistry::class );
    $registry->register( EngravedProductType::KEY, 'engraving-form' );
    $registry->register( 'simple', 'my-simple-form' );

    expect( $registry->for( Product::factory()->create( [ 'type' => EngravedProductType::KEY ] ) ) )->toBe( 'engraving-form' )
        ->and( $registry->for( Product::factory()->create() ) )->toBe( 'my-simple-form' );
} );

it( 'falls back to the options form, then to none', function (): void {
    app( ProductTypeRegistry::class )->register( EngravedProductType::KEY, new EngravedProductType() );

    $registry = new ProductFormRegistry();

    expect( $registry->for( Product::factory()->create( [ 'type' => EngravedProductType::KEY ] ) ) )->toBe( ProductFormRegistry::OPTIONS_FORM )
        ->and( $registry->for( Product::factory()->create() ) )->toBeNull()
        ->and( $registry->for( Product::factory()->create( [ 'type' => 'uninstalled-satellite-type' ] ) ) )->toBeNull();
} );

it( 'refuses an empty type or component', function ( string $type, string $component ): void {
    expect( fn () => app( ProductFormRegistry::class )->register( $type, $component ) )->toThrow( InvalidArgumentException::class );
} )->with( [
    'empty type'      => [ ' ', 'form' ],
    'empty component' => [ 'simple', '' ],
] );
