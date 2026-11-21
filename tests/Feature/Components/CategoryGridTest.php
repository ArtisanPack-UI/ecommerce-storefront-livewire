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

it( 'tiles each category as a Card with one link, its count and an arrow', function (): void {
    makeProduct( 1000, [ 'name' => 'Kettle' ] )->categories()->attach( $this->kitchen->id );

    $html = Blade::render( '<x-artisanpack-ec-category-grid heading="Shop by category" />' );

    preg_match( '#data-category-tile="' . $this->kitchen->id . '">(.*?)</li>#s', $html, $tile );

    expect( $html )->toMatch( '#<section class="ec-category-grid"\s+aria-labelledby="(ec-category-grid-[a-z0-9]+)"[^>]*>\s*<h2 id="\1" class="ec-category-grid__title">Shop by category</h2>#' )
        ->and( $tile[1] ?? '' )->toMatch( '#<article class="apui-card ec-category-tile" data-surface="default"\s+data-interactive#' )
        ->toContain( 'class="ec-category-tile__media" data-scope="card" data-part="media" aria-hidden="true"' )
        ->toMatch( '#<h3 class="ec-category-tile__name">\s*<a href="[^"]+/kitchen">Kitchen</a>#' )
        ->toContain( '1 product' )
        ->toMatch( '#apui-icon[^"]*ec-category-tile__arrow"[^>]*data-mirror[^>]*aria-hidden="true"#s' )
        ->and( substr_count( $tile[1] ?? '', '<a ' ) )->toBe( 1 );
} );

it( 'shows the grid icon on the sunken surface for a category without an image', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-category-grid />' ) )
        ->toMatch( '#ec-category-tile__media[^>]*>\s*<span[^>]*apui-icon ec-category-tile__placeholder#s' );
} );

it( 'lays the grid out by data-columns, clamped to 1–6', function ( int $columns, int $expected ): void {
    expect( Blade::render( '<x-artisanpack-ec-category-grid :columns="$columns" />', [ 'columns' => $columns ] ) )
        ->toContain( 'class="ec-category-grid__list" data-columns="' . $expected . '"' );
} )->with( [
    'default'  => [ 3, 3 ],
    'one'      => [ 1, 1 ],
    'too few'  => [ 0, 1 ],
    'too many' => [ 9, 6 ],
] );

it( 'leaves no DaisyUI or Tailwind classes', function (): void {
    expect( classTokens( Blade::render( '<x-artisanpack-ec-category-grid heading="Shop" />' ) ) )
        ->not->toContain( 'flex', 'flex-col', 'gap-4', 'grid', 'grid-cols-2', 'lg:grid-cols-3', 'text-2xl', 'font-bold', 'link-hover', 'bg-base-200', 'aspect-[4/3]', 'text-base-content/70', 'border-base-content/10', 'relative', 'h-full' );
} );
