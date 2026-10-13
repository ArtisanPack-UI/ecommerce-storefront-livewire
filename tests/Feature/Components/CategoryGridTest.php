<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

beforeEach( function (): void {
    $this->kitchen = ProductCategory::factory()->create( [ 'name' => 'Kitchen', 'slug' => 'kitchen' ] );
    $this->tea     = ProductCategory::factory()->create( [ 'name' => 'Tea', 'slug' => 'tea', 'parent_id' => $this->kitchen->id ] );
    $this->garden  = ProductCategory::factory()->create( [ 'name' => 'Garden', 'slug' => 'garden' ] );

    CategoryTree::flush();
} );

/**
 * The count text on the tile for `$category`.
 */
function tileCount( string $html, ProductCategory $category ): string
{
    preg_match( '#data-category-tile="' . $category->id . '".*?data-category-count>([^<]+)<#s', $html, $match );

    return trim( $match[1] ?? '' );
}

/**
 * Queries `$render` runs against the category pivot table.
 */
function pivotQueries( callable $render ): int
{
    app()->forgetScopedInstances();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $render();

    $pivot = ( new ProductCategory() )->products()->getTable();
    $count = count( array_filter( array_column( DB::getQueryLog(), 'query' ), static fn ( string $sql ): bool => str_contains( $sql, $pivot ) ) );
    DB::disableQueryLog();

    return $count;
}

it( 'counts a product filed in a category and its sub-category once', function (): void {
    $teapot = makeProduct( 2000, [ 'name' => 'Teapot' ] );
    $teapot->categories()->attach( [ $this->kitchen->id, $this->tea->id ] );

    makeProduct( 1000, [ 'name' => 'Spade' ] )->categories()->attach( $this->garden->id );
    Product::factory()->draft()->create( [ 'name' => 'Draft cup' ] )->categories()->attach( $this->kitchen->id );

    $html = Blade::render( '<x-artisanpack-ec-category-grid />' );

    expect( tileCount( $html, $this->kitchen ) )->toBe( '1 product' )
        ->and( tileCount( $html, $this->garden ) )->toBe( '1 product' );
} );

it( 'leaves products whose type package is uninstalled out of the counts', function (): void {
    makeProduct( 1000, [ 'name' => 'Kettle' ] )->categories()->attach( $this->kitchen->id );
    makeProduct( 1000, [ 'name' => 'Ghost kettle', 'type' => 'uninstalled-satellite-type' ] )->categories()->attach( $this->kitchen->id );

    expect( tileCount( Blade::render( '<x-artisanpack-ec-category-grid />' ), $this->kitchen ) )->toBe( '1 product' );
} );

it( 'caches the counts and refreshes them when the catalog changes', function (): void {
    makeProduct( 1000, [ 'name' => 'Kettle' ] )->categories()->attach( $this->kitchen->id );

    $render = static fn (): string => Blade::render( '<x-artisanpack-ec-category-grid />' );

    expect( pivotQueries( $render ) )->toBe( 1 )
        ->and( pivotQueries( $render ) )->toBe( 0 );

    makeProduct( 1000, [ 'name' => 'Teapot' ] )->categories()->attach( $this->tea->id );
    $this->garden->update( [ 'name' => 'Garden & patio' ] );

    expect( pivotQueries( $render ) )->toBe( 1 )
        ->and( tileCount( $render(), $this->kitchen ) )->toBe( '2 products' );
} );

it( 'counts every tile in one query, however many categories there are', function (): void {
    foreach ( range( 1, 10 ) as $i ) {
        $parent = ProductCategory::factory()->create( [ 'name' => 'Room ' . $i, 'slug' => 'room-' . $i ] );
        $child  = ProductCategory::factory()->create( [ 'name' => 'Corner ' . $i, 'slug' => 'corner-' . $i, 'parent_id' => $parent->id ] );

        makeProduct( 1000, [ 'name' => 'Lamp ' . $i ] )->categories()->attach( $child->id );
    }

    CategoryTree::flush();

    expect( pivotQueries( static fn (): string => Blade::render( '<x-artisanpack-ec-category-grid :limit="24" />' ) ) )->toBe( 1 );
} );
