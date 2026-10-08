<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CatalogQuery;
use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductRelation;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\CategoryGridBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\ProductGridBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\RecentlyViewedBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\RelatedProductsBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\ProductGrid;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\RecentlyViewed;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontContext;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Renders `$block` the way visual-editor's renderer does.
 *
 * @param  array<string, mixed>  $attrs  Raw attributes.
 */
function renderBlock( StorefrontBlock $block, array $attrs = [] ): string
{
    return $block->render( $block->validateAttrs( $attrs ) );
}

/**
 * The product names in `$html`, in order.
 *
 * @param  array<int, string>  $names  Names to look for.
 *
 * @return array<int, string>
 */
function namesInOrder( string $html, array $names ): array
{
    $found = [];

    foreach ( $names as $name ) {
        if ( false !== ( $at = strpos( $html, '>' . $name . '<' ) ) ) {
            $found[ $at ] = $name;
        }
    }

    ksort( $found );

    return array_values( $found );
}

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds' );
} );

beforeEach( function (): void {
    CategoryTree::flush();

    $this->travel( -3 )->days();
    $this->kettle = makeProduct( 3000, [ 'name' => 'Kettle', 'is_featured' => true ] );
    $this->travel( 1 )->days();
    $this->teapot = makeProduct( 2000, [ 'name' => 'Teapot' ], 2500 );
    $this->travel( 1 )->days();
    $this->mug    = makeProduct( 1000, [ 'name' => 'Mug', 'is_featured' => true ] );
    $this->travelBack();
    Product::factory()->draft()->create( [ 'name' => 'Draft cup', 'is_featured' => true ] );

    $this->kitchen = ProductCategory::factory()->create( [ 'name' => 'Kitchen', 'slug' => 'kitchen' ] );
    $this->tea     = ProductCategory::factory()->create( [ 'name' => 'Tea', 'slug' => 'tea', 'parent_id' => $this->kitchen->id ] );
    $this->garden  = ProductCategory::factory()->create( [ 'name' => 'Garden', 'slug' => 'garden' ] );
    $this->gift    = ProductTag::factory()->create( [ 'name' => 'Gift', 'slug' => 'gift' ] );

    $this->kettle->categories()->attach( $this->kitchen->id );
    $this->teapot->categories()->attach( $this->tea->id );
    $this->mug->tags()->attach( $this->gift->id );
} );

it( 'shows the newest products by default', function (): void {
    $html = renderBlock( new ProductGridBlock() );

    expect( namesInOrder( $html, [ 'Kettle', 'Teapot', 'Mug' ] ) )->toBe( [ 'Mug', 'Teapot', 'Kettle' ] )
        ->and( $html )->toContain( 'data-commerce-block="product-grid"' )
        ->not->toContain( 'Draft cup' );
} );

it( 'lists products from each source', function ( string $source, array $attrs, array $expected ): void {
    $html = renderBlock( new ProductGridBlock(), [ 'source' => $source, 'sort' => 'name', ...$attrs ] );

    expect( namesInOrder( $html, [ 'Kettle', 'Teapot', 'Mug' ] ) )->toBe( $expected );
} )->with( [
    'featured'          => [ 'featured', [], [ 'Kettle', 'Mug' ] ],
    'on sale'           => [ 'on_sale', [], [ 'Teapot' ] ],
    'category + subs'   => [ 'category', [ 'category' => 'kitchen' ], [ 'Kettle', 'Teapot' ] ],
    'tag'               => [ 'tag', [ 'tag' => 'gift' ], [ 'Mug' ] ],
    'unknown category'  => [ 'category', [ 'category' => 'nope' ], [] ],
    'category, no slug' => [ 'category', [], [] ],
] );

it( 'keeps hand-picked products in the order picked', function (): void {
    $html = renderBlock( new ProductGridBlock(), [ 'source' => 'hand_picked', 'ids' => "{$this->teapot->id}, x, {$this->kettle->id}" ] );

    expect( namesInOrder( $html, [ 'Kettle', 'Teapot', 'Mug' ] ) )->toBe( [ 'Teapot', 'Kettle' ] );
} );

it( 'sorts, limits, and lays out the grid', function (): void {
    $html = renderBlock( new ProductGridBlock(), [ 'source' => 'featured', 'sort' => '-price', 'limit' => 1, 'columns' => 3 ] );

    expect( namesInOrder( $html, [ 'Kettle', 'Teapot', 'Mug' ] ) )->toBe( [ 'Kettle' ] )
        ->and( $html )->toContain( 'lg:grid-cols-3' );
} );

it( 'hides prices, ratings, and quick add when asked', function (): void {
    $this->mug->update( [ 'avg_rating' => 4.5, 'reviews_count' => 3 ] );

    $shown  = renderBlock( new ProductGridBlock(), [ 'source' => 'tag', 'tag' => 'gift' ] );
    $hidden = renderBlock( new ProductGridBlock(), [ 'source' => 'tag', 'tag' => 'gift', 'showPrice' => false, 'showRating' => false, 'showAddToCart' => false ] );

    expect( $shown )->toContain( '$10.00' )->toContain( 'data-quick-add' )->toContain( '4.5' )
        ->and( $hidden )->not->toContain( '$10.00' )->not->toContain( 'data-quick-add' )->not->toContain( 'Rated 4.5' );
} );

it( 'adds a grid product to the cart', function (): void {
    Livewire::test( ProductGrid::class, [ 'source' => 'featured' ] )
        ->call( 'quickAdd', $this->mug->id )
        ->assertDispatched( 'ecommerce-cart-updated' );

    expect( app( StorefrontCart::class )->current()?->items()->count() )->toBe( 1 );
} );

it( 'clamps the grid component\'s props and locks them', function (): void {
    Livewire::test( ProductGrid::class, [ 'source' => 'bogus', 'limit' => 500, 'columns' => 9, 'ids' => [ 0, -2, 5, 5 ] ] )
        ->assertSet( 'source', 'newest' )
        ->assertSet( 'limit', 24 )
        ->assertSet( 'columns', 6 )
        ->assertSet( 'ids', [ 5 ] )
        ->set( 'limit', 2 );
} )->throws( CannotUpdateLockedPropertyException::class );

it( 'shows nothing when the engine can\'t list products', function (): void {
    $this->mock( CatalogQuery::class )->shouldReceive( 'currency' )->andThrow( new RuntimeException( 'Catalog is down.' ) );

    Livewire::test( ProductGrid::class )->assertOk()->assertDontSeeHtml( 'data-product-grid-list' );
} );

it( 'tiles the top-level categories with counts and links', function (): void {
    $html = renderBlock( new CategoryGridBlock() );

    expect( $html )->toContain( 'data-category-tile="' . $this->kitchen->id . '"' )
        ->toContain( 'data-category-tile="' . $this->garden->id . '"' )
        ->not->toContain( 'data-category-tile="' . $this->tea->id . '"' )
        ->toContain( '2 products' )
        ->toContain( '0 products' )
        ->toContain( 'href="' . route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'kitchen' ] ) . '"' );
} );

it( 'tiles a parent\'s sub-categories, without counts or images when asked', function (): void {
    $html = renderBlock( new CategoryGridBlock(), [ 'parent' => 'kitchen', 'showCounts' => false, 'showImages' => false, 'heading' => 'Tea time' ] );

    expect( $html )->toContain( 'data-category-tile="' . $this->tea->id . '"' )
        ->not->toContain( 'data-category-tile="' . $this->kitchen->id . '"' )
        ->not->toContain( 'data-category-count' )
        ->not->toContain( 'o-squares-2x2' )
        ->toContain( 'Tea time' );
} );

it( 'limits the categories and renders nothing for an unknown parent', function (): void {
    expect( substr_count( renderBlock( new CategoryGridBlock(), [ 'limit' => 1 ] ), 'data-category-tile=' ) )->toBe( 1 )
        ->and( renderBlock( new CategoryGridBlock(), [ 'parent' => 'missing' ] ) )->toBe( '' );
} );

it( 'tiles categories without counts when the engine can\'t count', function (): void {
    $this->mock( CatalogQuery::class )->shouldReceive( 'facets' )->andThrow( new RuntimeException( 'Counts are down.' ) );

    expect( renderBlock( new CategoryGridBlock() ) )->toContain( 'Kitchen' )->not->toContain( 'data-category-count' );
} );

it( 'shows the page product\'s related products, upsells, or cross-sells', function (): void {
    foreach ( [ [ $this->teapot, ProductRelation::UPSELL ], [ $this->mug, ProductRelation::CROSS_SELL ] ] as [ $other, $type ] ) {
        ProductRelation::query()->create( [ 'product_id' => $this->kettle->id, 'related_product_id' => $other->id, 'type' => $type, 'position' => 0 ] );
    }

    app( StorefrontContext::class )->setProduct( $this->kettle );

    expect( renderBlock( new RelatedProductsBlock(), [ 'type' => 'upsell' ] ) )->toContain( 'data-related-placeholder' );

    Livewire::withoutLazyLoading();

    expect( renderBlock( new RelatedProductsBlock(), [ 'type' => 'upsell', 'heading' => 'Upgrade' ] ) )
        ->toContain( 'Upgrade' )
        ->toContain( 'data-product-card="' . $this->teapot->id . '"' )
        ->not->toContain( 'data-product-card="' . $this->mug->id . '"' )
        ->and( renderBlock( new RelatedProductsBlock(), [ 'type' => 'cross_sell', 'columns' => 2 ] ) )
        ->toContain( 'data-product-card="' . $this->mug->id . '"' )
        ->toContain( 'lg:grid-cols-2' );
} );

it( 'needs a product for related products but suggests cross-sells for the cart', function (): void {
    ProductRelation::query()->create( [ 'product_id' => $this->kettle->id, 'related_product_id' => $this->mug->id, 'type' => ProductRelation::CROSS_SELL, 'position' => 0 ] );
    app( StorefrontCartService::class )->addItem( app( StorefrontCart::class )->current( true ), $this->kettle->id, null, 1 );

    Livewire::withoutLazyLoading();

    expect( renderBlock( new RelatedProductsBlock() ) )->toBe( '' )
        ->and( renderBlock( new RelatedProductsBlock(), [ 'type' => 'cross_sell' ] ) )->toContain( 'data-product-card="' . $this->mug->id . '"' );
} );

it( 'shows the recently-viewed satellite\'s products, visible ones only, without the current one', function (): void {
    $draft = Product::factory()->draft()->create();

    addFilter( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds', fn ( array $ids, int $limit ): array => [ $this->kettle->id, $draft->id, $this->mug->id, $this->teapot->id ], 10, 2 );

    app( StorefrontContext::class )->setProduct( $this->kettle );

    $html = renderBlock( new RecentlyViewedBlock(), [ 'limit' => 2 ] );

    expect( namesInOrder( $html, [ 'Kettle', 'Teapot', 'Mug' ] ) )->toBe( [ 'Mug' ] )
        ->and( $html )->toContain( 'Recently viewed' );
} );

it( 'renders no recently-viewed section without history', function (): void {
    Livewire::test( RecentlyViewed::class )->assertOk()->assertDontSee( 'Recently viewed' );
} );
