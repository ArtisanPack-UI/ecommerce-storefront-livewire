<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Services\InventoryService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\Index as Catalog;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * `$product`'s stock row, created with 5 units.
 */
function stockRow( Product $product ): InventoryItem
{
    setStock( $product, 5 );

    return InventoryItem::query()->where( 'stockable_id', $product->id )->firstOrFail();
}

/**
 * Queries `$render` runs against the facet tables.
 */
function facetQueries( callable $render ): int
{
    app()->forgetScopedInstances();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $render();

    $count = count( array_filter( array_column( DB::getQueryLog(), 'query' ), static fn ( string $sql ): bool => str_contains( $sql, 'COUNT(' ) ) );
    DB::disableQueryLog();

    return $count;
}

it( 'caches facet counts across requests', function (): void {
    makeProduct( 1000, [ 'name' => 'Kettle' ] );

    $render = static fn () => Livewire::test( Catalog::class )->html();

    expect( facetQueries( $render ) )->toBeGreaterThan( 0 )
        ->and( facetQueries( $render ) )->toBe( 0 );
} );

it( 'keeps facet counts apart per filter set and currency', function (): void {
    $red = makeProduct( 1000, [ 'name' => 'Red kettle' ] );
    makeProduct( 1000, [ 'name' => 'Blue kettle' ] );
    setStock( $red, 0 );

    Livewire::test( Catalog::class )->assertSee( '2 products' );

    Livewire::withQueryParams( [ 'in_stock' => '1' ] )->test( Catalog::class )->assertSee( '1 product' );
} );

it( 'clears the cache when the catalog changes', function ( Closure $change ): void {
    $product = makeProduct( 1000, [ 'name' => 'Kettle' ] );
    $render  = static fn () => Livewire::test( Catalog::class )->html();

    facetQueries( $render );
    $change( $product );

    expect( facetQueries( $render ) )->toBeGreaterThan( 0 );
} )->with( [
    'product saved'     => [ static fn ( Product $product ) => $product->update( [ 'name' => 'Steel kettle' ] ) ],
    'product deleted'   => [ static fn ( Product $product ) => $product->delete() ],
    'category saved'    => [ static fn () => ProductCategory::factory()->create() ],
    'tag deleted'       => [ static fn () => ProductTag::factory()->create()->delete() ],
    'stock adjusted'    => [ static fn ( Product $product ) => app( InventoryService::class )->adjust( stockRow( $product ), 3, 'restock' ) ],
    'went out of stock' => [ static fn ( Product $product ) => doAction( 'ap.ecommerce.inventory.outOfStock', stockRow( $product ) ) ],
] );

it( 'caches the walked category tree', function (): void {
    ProductCategory::factory()->create( [ 'name' => 'Kitchen', 'slug' => 'kitchen' ] );
    CategoryTree::flush();

    expect( app( CategoryPaths::class )->bySlug( 'kitchen' )['name'] )->toBe( 'Kitchen' );

    DB::table( 'ecommerce_product_categories' )->update( [ 'name' => 'Renamed behind the cache' ] );
    CategoryTree::flush();
    app()->forgetScopedInstances();

    expect( app( CategoryPaths::class )->bySlug( 'kitchen' )['name'] )->toBe( 'Kitchen' );

    ProductCategory::query()->first()->update( [ 'name' => 'Cookware' ] );
    app()->forgetScopedInstances();

    expect( app( CategoryPaths::class )->bySlug( 'kitchen' )['name'] )->toBe( 'Cookware' );
} );

it( 'tags its entries on stores that support tags', function (): void {
    StorefrontCache::remember( 'probe', static fn (): string => 'cached' );

    expect( Cache::tags( [ StorefrontCache::TAG ] )->get( StorefrontCache::TAG . ':probe' ) )->toBe( 'cached' );

    StorefrontCache::flush();

    expect( Cache::tags( [ StorefrontCache::TAG ] )->get( StorefrontCache::TAG . ':probe' ) )->toBeNull();
} );

it( 'versions its keys on stores without tags', function (): void {
    config( [ 'cache.stores.storefront-file' => [ 'driver' => 'file', 'path' => storage_path( 'framework/cache/storefront-test' ) ] ] );
    Cache::setDefaultDriver( 'storefront-file' );
    Cache::flush();

    $calls = 0;
    $value = static function () use ( &$calls ): int {
        return ++$calls;
    };

    expect( StorefrontCache::remember( 'probe', $value ) )->toBe( 1 )
        ->and( StorefrontCache::remember( 'probe', $value ) )->toBe( 1 );

    StorefrontCache::flush();

    expect( StorefrontCache::remember( 'probe', $value ) )->toBe( 2 )
        ->and( Cache::get( StorefrontCache::VERSION_KEY ) )->toBe( 1 );

    Cache::flush();
} );

it( 'turns off with a cache_ttl of 0', function (): void {
    config( [ 'artisanpack.ecommerce-storefront-livewire.performance.cache_ttl' => 0 ] );

    $calls = 0;

    StorefrontCache::remember( 'probe', static function () use ( &$calls ): int {
        return ++$calls;
    } );
    StorefrontCache::remember( 'probe', static function () use ( &$calls ): int {
        return ++$calls;
    } );

    expect( $calls )->toBe( 2 );
} );

it( 'computes the value when the cache store fails', function (): void {
    Cache::shouldReceive( 'getStore' )->andThrow( new RuntimeException( 'Redis is down.' ) );

    expect( StorefrontCache::remember( 'probe', static fn (): string => 'fresh' ) )->toBe( 'fresh' );

    StorefrontCache::flush();
} );
