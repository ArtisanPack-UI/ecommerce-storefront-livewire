<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductImage;
use ArtisanPackUI\Ecommerce\Models\ProductRelation;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Dashboard;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Orders;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Drawer;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Index as CartPage;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\Index as Catalog;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\ProductGrid;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\RelatedProducts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Show;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Search\HeaderSearch;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Search\Index as SearchPage;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Fixtures\Media\FakeMedia;

beforeEach( function (): void {
    // Count every query a render makes, not the ones a warm cache saves.
    config( [ 'artisanpack.ecommerce-storefront-livewire.performance.cache_ttl' => 0 ] );
    Model::preventLazyLoading();
} );

afterEach( function (): void {
    Model::preventLazyLoading( false );
    FakeMedia::$items = [];
    RateLimiter::clear( 'ecommerce.catalog.read' );
} );

/**
 * Turns the media library on with lookups that cost one query each, like
 * the library's own (`Media::find()`, or one `whereKey()` for a preload).
 */
function fakeMediaLookups(): void
{
    ProductImages::fake( true, static function ( array $ids ): array {
        DB::table( ( new Product() )->getTable() )->whereIn( 'id', $ids )->count();

        return array_intersect_key( FakeMedia::$items, array_flip( $ids ) );
    } );
}

/**
 * `$count` stocked products whose image is a media-library item.
 *
 * @return array<int, Product>
 */
function mediaProducts( int $count ): array
{
    static $mediaId = 1000;

    $products = [];

    foreach ( range( 1, $count ) as $i ) {
        FakeMedia::register( ++$mediaId, new FakeMedia( 800, 600 ) );

        $product = makeProduct( 1000 + $i, [ 'name' => 'Media mug ' . $i . ' ' . uniqid(), 'featured_image_media_id' => $mediaId ] );
        setStock( $product, 5 );

        $products[] = $product;
    }

    return $products;
}

/**
 * How many queries `$render` runs.
 */
function queriesFor( callable $render ): int
{
    app()->forgetScopedInstances();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $render();

    $count = count( DB::getQueryLog() );
    DB::disableQueryLog();

    return $count;
}

/**
 * `$count` stocked products with an image each.
 *
 * @return array<int, Product>
 */
function stockedProducts( int $count ): array
{
    $products = [];

    foreach ( range( 1, $count ) as $i ) {
        $product = makeProduct( 1000 + $i, [ 'name' => 'Product ' . $i . ' ' . uniqid() ] );
        setStock( $product, 5 );
        ProductImage::factory()->create( [ 'product_id' => $product->id, 'image_url' => 'https://cdn.example.test/' . $product->id . '.jpg' ] );

        $products[] = $product;
    }

    return $products;
}

it( 'renders the catalog with the same number of queries for more products', function (): void {
    stockedProducts( 2 );
    $few = queriesFor( static fn () => Livewire::test( Catalog::class )->html() );

    stockedProducts( 8 );

    expect( queriesFor( static fn () => Livewire::test( Catalog::class )->html() ) )->toBeLessThanOrEqual( $few );
} );

it( 'renders a product grid block with the same number of queries for more products', function (): void {
    stockedProducts( 2 );
    $few = queriesFor( static fn () => Livewire::test( ProductGrid::class, [ 'limit' => 12 ] )->html() );

    stockedProducts( 8 );

    expect( queriesFor( static fn () => Livewire::test( ProductGrid::class, [ 'limit' => 12 ] )->html() ) )->toBeLessThanOrEqual( $few );
} );

it( 'renders related products with the same number of queries for more of them', function (): void {
    [ $product ] = stockedProducts( 1 );

    $relate = static function ( array $related ) use ( $product ): void {
        foreach ( $related as $position => $other ) {
            ProductRelation::query()->create( [ 'product_id' => $product->id, 'related_product_id' => $other->id, 'type' => ProductRelation::RELATED, 'position' => $position ] );
        }
    };

    $relate( stockedProducts( 2 ) );
    $few = queriesFor( static fn () => Livewire::withoutLazyLoading()->test( RelatedProducts::class, [ 'product' => $product, 'limit' => 12 ] )->html() );

    $relate( stockedProducts( 6 ) );

    expect( queriesFor( static fn () => Livewire::withoutLazyLoading()->test( RelatedProducts::class, [ 'product' => $product, 'limit' => 12 ] )->html() ) )->toBeLessThanOrEqual( $few );
} );

it( 'renders a product page with the same number of queries for a bigger gallery', function (): void {
    [ $product ] = stockedProducts( 1 );
    $few         = queriesFor( static fn () => Livewire::test( Show::class, [ 'product' => $product, 'recordView' => false ] )->html() );

    foreach ( range( 1, 5 ) as $i ) {
        ProductImage::factory()->create( [ 'product_id' => $product->id, 'image_url' => 'https://cdn.example.test/extra-' . $i . '.jpg' ] );
    }

    expect( queriesFor( static fn () => Livewire::test( Show::class, [ 'product' => $product, 'recordView' => false ] )->html() ) )->toBe( $few );
} );

it( 'renders a variable product page with the same number of queries for more variants', function (): void {
    $small = makeVariableProduct( [ 'red/m' => [ 'price' => 1000, 'stock' => 3 ], 'blue/m' => [ 'price' => 1100, 'stock' => 3 ] ], [ 'slug' => 'small' ], 'SMALL' )['product'];
    $big   = makeVariableProduct( [
        'red/m'  => [ 'price' => 1000, 'stock' => 3 ],
        'red/l'  => [ 'price' => 1000, 'stock' => 3 ],
        'red/xl' => [ 'price' => 1000, 'stock' => 3 ],
        'blue/m' => [ 'price' => 1100, 'stock' => 3 ],
        'blue/l' => [ 'price' => 1100, 'stock' => 3 ],
    ], [ 'slug' => 'big' ], 'BIG' )['product'];

    $few = queriesFor( static fn () => Livewire::test( Show::class, [ 'product' => $small, 'recordView' => false ] )->html() );

    expect( queriesFor( static fn () => Livewire::test( Show::class, [ 'product' => $big, 'recordView' => false ] )->html() ) )->toBeLessThanOrEqual( $few );
} );

it( 'renders the cart and the cart drawer with the same number of queries for more lines', function ( string $component ): void {
    // A signed-in shopper's cart survives the per-request reset between renders.
    shopper();

    $add = static function ( array $products ): void {
        $cart = app( StorefrontCart::class )->current( true );

        foreach ( $products as $product ) {
            app( StorefrontCartService::class )->addItem( $cart, $product->id, null, 1 );
        }
    };

    $add( stockedProducts( 2 ) );
    $few = queriesFor( static fn () => Livewire::withoutLazyLoading()->test( $component )->html() );

    $add( stockedProducts( 6 ) );

    expect( Cart::query()->first()?->items()->count() )->toBe( 8 )
        ->and( queriesFor( static fn () => Livewire::withoutLazyLoading()->test( $component )->html() ) )->toBeLessThanOrEqual( $few );
} )->with( [ 'cart page' => [ CartPage::class ], 'cart drawer' => [ Drawer::class ] ] );

it( 'renders the account dashboard and order history with the same number of queries for more orders', function ( string $component ): void {
    [, $customer ] = shopper();

    foreach ( range( 1, 2 ) as $i ) {
        placedOrder( [], $customer );
    }

    $few = queriesFor( static fn () => Livewire::test( $component )->html() );

    foreach ( range( 1, 6 ) as $i ) {
        placedOrder( [], $customer, [ makeProduct( 1000 + $i ), makeProduct( 2000 + $i ) ] );
    }

    expect( queriesFor( static fn () => Livewire::test( $component )->html() ) )->toBe( $few );
} )->with( [ 'dashboard' => [ Dashboard::class ], 'orders' => [ Orders::class ] ] );

it( 'serves a variable product page with the same number of queries for more variants', function (): void {
    makeVariableProduct( [ 'red/m' => [ 'price' => 1000, 'stock' => 3 ], 'blue/m' => [ 'price' => 1100, 'stock' => 3 ] ], [ 'slug' => 'small' ], 'SMALL' );
    makeVariableProduct( [
        'red/m'  => [ 'price' => 1000, 'stock' => 3 ],
        'red/l'  => [ 'price' => 1000, 'stock' => 3 ],
        'red/xl' => [ 'price' => 1000, 'stock' => 3 ],
        'blue/m' => [ 'price' => 1100, 'stock' => 3 ],
        'blue/l' => [ 'price' => 1100, 'stock' => 3 ],
    ], [ 'slug' => 'big' ], 'BIG' );

    $few = queriesFor( fn () => $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'small' ] ) )->assertOk() );

    expect( queriesFor( fn () => $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'big' ] ) )->assertOk() ) )->toBeLessThanOrEqual( $few );
} );

it( 'looks up media-library images once per listing, not once per card', function ( Closure $render ): void {
    fakeMediaLookups();

    mediaProducts( 2 );
    $few = queriesFor( $render );

    mediaProducts( 6 );

    expect( queriesFor( $render ) )->toBeLessThanOrEqual( $few );
} )->with( [
    'catalog'       => [ static fn () => Livewire::test( Catalog::class )->html() ],
    'product grid'  => [ static fn () => Livewire::test( ProductGrid::class, [ 'limit' => 12 ] )->html() ],
    'search page'   => [ static fn () => Livewire::withQueryParams( [ 'q' => 'media mug' ] )->test( SearchPage::class )->html() ],
    'header search' => [ static fn () => Livewire::test( HeaderSearch::class )->set( 'q', 'media mug' )->html() ],
] );

it( 'looks up the cart lines\' media-library images once, not once per line', function ( string $component ): void {
    fakeMediaLookups();
    shopper();

    $add = static function ( array $products ): void {
        $cart = app( StorefrontCart::class )->current( true );

        foreach ( $products as $product ) {
            app( StorefrontCartService::class )->addItem( $cart, $product->id, null, 1 );
        }
    };

    $add( mediaProducts( 2 ) );
    $few = queriesFor( static fn () => Livewire::withoutLazyLoading()->test( $component )->html() );

    $add( mediaProducts( 6 ) );

    expect( queriesFor( static fn () => Livewire::withoutLazyLoading()->test( $component )->html() ) )->toBeLessThanOrEqual( $few );
} )->with( [ 'cart page' => [ CartPage::class ], 'cart drawer' => [ Drawer::class ] ] );

it( 'looks up category tile images once per grid, not once per tile', function (): void {
    fakeMediaLookups();

    $categories = static function ( int $count ): void {
        static $mediaId = 5000;

        foreach ( range( 1, $count ) as $i ) {
            FakeMedia::register( ++$mediaId, new FakeMedia( 800, 600 ) );
            ProductCategory::factory()->create( [ 'name' => 'Room ' . $mediaId, 'slug' => 'room-' . $mediaId, 'image_media_id' => $mediaId ] );
        }
    };

    $render = static fn () => Blade::render( '<x-artisanpack-ec-category-grid :limit="24" />' );

    $categories( 2 );
    CategoryTree::flush();
    $few = queriesFor( $render );

    $categories( 6 );
    CategoryTree::flush();

    expect( queriesFor( $render ) )->toBeLessThanOrEqual( $few )
        ->and( $render() )->toContain( 'https://media.example.test/medium.jpg' );
} );

it( 'keeps preloaded media to one request', function (): void {
    fakeMediaLookups();
    FakeMedia::register( 77, new FakeMedia( 800, 600 ) );

    ProductImages::preload( [ 77 ] );
    FakeMedia::$items = [];

    expect( ProductImages::media( 77, 'Mug' ) )->not->toBeNull();

    app()->forgetScopedInstances();

    expect( ProductImages::media( 77, 'Mug' ) )->toBeNull();
} );
