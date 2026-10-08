<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\ProductPriceBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlocks;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\RecentlyViewed;

/**
 * Simulates an installed visual-editor and returns its stub.
 */
function installVisualEditor(): ArtisanPackUI\VisualEditor\VisualEditor
{
    require_once dirname( __DIR__, 2 ) . '/Fixtures/VisualEditor/VisualEditorStub.php';

    $editor = new ArtisanPackUI\VisualEditor\VisualEditor();

    app()->instance( StorefrontBlocks::EDITOR, $editor );

    return $editor;
}

afterEach( function (): void {
    removeAllFilters( 'ap.visualEditor.resources' );
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.blocks' );
} );

it( 'registers nothing without visual-editor', function (): void {
    expect( StorefrontBlocks::available( app() ) )->toBeFalse();

    StorefrontBlocks::boot( app() );

    expect( applyFilters( 'ap.visualEditor.resources', [] ) )->toBe( [] );
} );

it( 'registers the commerce blocks as server blocks when visual-editor is installed', function (): void {
    $editor = installVisualEditor();

    expect( StorefrontBlocks::available( app() ) )->toBeTrue();

    StorefrontBlocks::boot( app() );

    expect( array_keys( $editor->blocks ) )->toBe( [
        'artisanpack-commerce/product-grid',
        'artisanpack-commerce/category-grid',
        'artisanpack-commerce/related-products',
        'artisanpack-commerce/single-product',
        'artisanpack-commerce/product-gallery',
        'artisanpack-commerce/product-price',
        'artisanpack-commerce/add-to-cart',
        'artisanpack-commerce/product-reviews',
    ] );

    $grid = $editor->blocks['artisanpack-commerce/product-grid'];

    expect( $grid['metadata']['title'] )->toBe( 'Product Grid' )
        ->and( $grid['metadata']['category'] )->toBe( StorefrontBlock::CATEGORY )
        ->and( $grid['metadata']['keywords'] )->toContain( 'commerce' )
        ->and( $grid['metadata']['attributes']['limit']['apControl'] )->toMatchArray( [ 'control' => 'range', 'min' => 1, 'max' => 24 ] )
        ->and( $grid['metadata']['attributes']['source']['apControl']['options'][0] )->toBe( [ 'label' => 'Newest', 'value' => 'newest' ] )
        ->and( $grid['render'] )->toBeCallable()
        ->and( array_keys( $grid['callbacks'] ) )->toBe( [ 'validateAttrs', 'authorize' ] );
} );

it( 'skips the blocks when visual_editor.blocks is off', function (): void {
    $editor = installVisualEditor();
    config( [ 'artisanpack.ecommerce-storefront-livewire.visual_editor.blocks' => false ] );

    StorefrontBlocks::boot( app() );

    expect( $editor->blocks )->toBe( [] );
} );

it( 'adds the blocks to an enabled_blocks allow-list', function (): void {
    installVisualEditor();
    config( [ 'artisanpack.visual-editor.enabled_blocks' => [ 'artisanpack/paragraph' ] ] );

    StorefrontBlocks::boot( app() );

    expect( config( 'artisanpack.visual-editor.enabled_blocks' ) )
        ->toContain( 'artisanpack/paragraph' )
        ->toContain( 'artisanpack-commerce/product-grid' )
        ->toContain( 'artisanpack-commerce/product-reviews' );
} );

it( 'leaves an empty allow-list empty, so every block stays allowed', function (): void {
    installVisualEditor();
    config( [ 'artisanpack.visual-editor.enabled_blocks' => [] ] );

    StorefrontBlocks::boot( app() );

    expect( config( 'artisanpack.visual-editor.enabled_blocks' ) )->toBe( [] );
} );

it( 'maps products in visual-editor\'s resources without overriding the host', function (): void {
    installVisualEditor();

    StorefrontBlocks::boot( app() );

    expect( applyFilters( 'ap.visualEditor.resources', [] ) )->toBe( [ 'products' => Product::class ] )
        ->and( applyFilters( 'ap.visualEditor.resources', [ 'products' => 'App\\Models\\Product' ] ) )->toBe( [ 'products' => 'App\\Models\\Product' ] );
} );

it( 'registers Recently Viewed only with the recently-viewed satellite', function (): void {
    $editor = installVisualEditor();

    app( SatelliteRegistry::class )->register( [
        'package_name'    => RecentlyViewed::SATELLITE,
        'version'         => '1.0.0',
        'label'           => 'Recently viewed',
        'migration_paths' => [],
        'config_keys'     => [],
        'meta_namespaces' => [],
        'tables'          => [],
        'columns'         => [],
        'product_types'   => [],
    ] );

    StorefrontBlocks::register( app() );

    expect( $editor->blocks )->toHaveKey( 'artisanpack-commerce/recently-viewed' );
} );

it( 'lets the blocks filter remove a block', function (): void {
    $editor = installVisualEditor();

    addFilter( 'ap.ecommerceStorefrontLivewire.blocks', fn ( array $blocks ): array => array_values( array_diff( $blocks, [ ProductPriceBlock::class ] ) ) );

    StorefrontBlocks::register( app() );

    expect( $editor->blocks )->not->toHaveKey( 'artisanpack-commerce/product-price' )
        ->and( $editor->blocks )->toHaveKey( 'artisanpack-commerce/product-grid' );
} );

it( 'wires the blocks from the service provider once the app has booted', function (): void {
    $editor = installVisualEditor();

    $provider = app()->getProvider( ArtisanPackUI\EcommerceStorefrontLivewire\EcommerceStorefrontLivewireServiceProvider::class );

    ( fn () => $this->registerVisualEditorBlocks() )->call( $provider );

    expect( $editor->blocks )->toHaveKey( 'artisanpack-commerce/single-product' );
} );
