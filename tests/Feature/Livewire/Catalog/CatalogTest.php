<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\CurrencyResolver;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductImage;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Support\GuestCartCookie;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\Index;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.catalog.sorts' );
    Model::preventLazyLoading( false );
} );

/**
 * The product names in the order a rendered catalog lists them.
 *
 * @return array<int, string>
 */
function listedNames( Testable $component, array $names ): array
{
    $html = $component->html();
    $at   = [];

    foreach ( $names as $name ) {
        $position = strpos( $html, '>' . $name . '<' );

        if ( false !== $position ) {
            $at[ $position ] = $name;
        }
    }

    ksort( $at );

    return array_values( $at );
}

it( 'lists storefront-visible products only', function (): void {
    makeProduct( 1000, [ 'name' => 'Visible lamp' ] );
    Product::factory()->draft()->create( [ 'name' => 'Draft lamp' ] );
    Product::factory()->create( [ 'name' => 'Future lamp', 'published_at' => now()->addWeek() ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSee( 'Visible lamp' )
        ->assertDontSee( 'Draft lamp' )
        ->assertDontSee( 'Future lamp' )
        ->assertSee( '1 product' );
} );

it( 'sorts newest first by default and keeps the default out of the URL', function (): void {
    $this->travel( -2 )->days();
    makeProduct( 1000, [ 'name' => 'Old kettle' ] );
    $this->travelBack();
    makeProduct( 1000, [ 'name' => 'New kettle' ] );

    $component = Livewire::test( Index::class )
        ->assertSet( 'sort', 'newest' )
        ->assertSet( 'perPage', 24 );

    expect( listedNames( $component, [ 'Old kettle', 'New kettle' ] ) )->toBe( [ 'New kettle', 'Old kettle' ] );
} );

it( 'sorts by each offered option', function ( string $sort, array $expected ): void {
    makeProduct( 3000, [ 'name' => 'Bravo', 'avg_rating' => 3.0 ] );
    makeProduct( 1000, [ 'name' => 'Charlie', 'avg_rating' => 5.0 ] );
    makeProduct( 2000, [ 'name' => 'Alpha', 'avg_rating' => 4.0 ] );

    $component = Livewire::test( Index::class )->set( 'sort', $sort );

    expect( listedNames( $component, [ 'Alpha', 'Bravo', 'Charlie' ] ) )->toBe( $expected );
} )->with( [
    'price low to high'  => [ 'price', [ 'Charlie', 'Alpha', 'Bravo' ] ],
    'price high to low'  => [ '-price', [ 'Bravo', 'Alpha', 'Charlie' ] ],
    'top rated'          => [ 'rating', [ 'Charlie', 'Alpha', 'Bravo' ] ],
    'name'               => [ 'name', [ 'Alpha', 'Bravo', 'Charlie' ] ],
] );

it( 'sorts by price in the shopper\'s currency', function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'USD', 'EUR' ] );

    $cheapInUsd = makeProduct( 1000, [ 'name' => 'Cheap in dollars' ] );
    $cheapInEur = makeProduct( 2000, [ 'name' => 'Cheap in euros' ] );
    ProductPrice::factory()->forPriceable( $cheapInUsd )->create( [ 'currency' => 'EUR', 'price_amount' => 3000 ] );
    ProductPrice::factory()->forPriceable( $cheapInEur )->create( [ 'currency' => 'EUR', 'price_amount' => 500 ] );

    $this->app->instance( CurrencyResolver::class, new class implements CurrencyResolver {
        public function resolve( Request $request ): string
        {
            return 'EUR';
        }
    } );

    $component = Livewire::test( Index::class )->set( 'sort', 'price' );

    expect( listedNames( $component, [ 'Cheap in dollars', 'Cheap in euros' ] ) )->toBe( [ 'Cheap in euros', 'Cheap in dollars' ] );
    $component->assertSee( '€5.00' )->assertSee( '€30.00' );
} );

it( 'restores sort, page size, and page from the query string', function (): void {
    foreach ( range( 1, 15 ) as $i ) {
        makeProduct( 1000 + $i, [ 'name' => sprintf( 'Item %02d', $i ) ] );
    }

    Livewire::withQueryParams( [ 'sort' => 'name', 'per_page' => 12, 'page' => 2 ] )
        ->test( Index::class )
        ->assertSet( 'sort', 'name' )
        ->assertSet( 'perPage', 12 )
        ->assertSee( 'Item 13' )
        ->assertSee( 'Item 15' )
        ->assertDontSee( 'Item 01' );
} );

it( 'falls back to the defaults for an unknown sort or page size', function (): void {
    Livewire::withQueryParams( [ 'sort' => 'cheapest', 'per_page' => 1000 ] )
        ->test( Index::class )
        ->assertSet( 'sort', 'newest' )
        ->assertSet( 'perPage', 24 );
} );

it( 'rejects an unknown sort or page size set from the browser', function (): void {
    Livewire::test( Index::class )
        ->set( 'sort', 'relevance' )
        ->assertSet( 'sort', 'newest' )
        ->set( 'perPage', 7 )
        ->assertSet( 'perPage', 24 );
} );

it( 'pages through the catalog and starts over when the sort or page size changes', function (): void {
    foreach ( range( 1, 30 ) as $i ) {
        makeProduct( 1000 + $i, [ 'name' => sprintf( 'Item %02d', $i ) ] );
    }

    $component = Livewire::test( Index::class )
        ->set( 'sort', 'name' )
        ->set( 'perPage', 12 )
        ->assertSee( '30 products' )
        ->call( 'gotoPage', 3 )
        ->assertSee( 'Item 25' )
        ->assertSee( 'Item 30' )
        ->assertDontSee( 'Item 24' );

    expect( $component->get( 'paginators' )['page'] )->toBe( 3 );

    $component->set( 'sort', '-price' );
    expect( $component->get( 'paginators' )['page'] )->toBe( 1 );
    $component->assertSee( 'Item 30' )->assertDontSee( 'Item 18' );

    $component->call( 'gotoPage', 2 )->set( 'perPage', 24 );
    expect( $component->get( 'paginators' )['page'] )->toBe( 1 );
} );

it( 'offers the configured page sizes', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.catalog.per_page_values', [ 48, 16, 'x', -1 ] );
    config()->set( 'artisanpack.ecommerce-storefront-livewire.catalog.per_page', 16 );
    makeProduct();

    Livewire::test( Index::class )
        ->assertSet( 'perPage', 16 )
        ->set( 'perPage', 48 )
        ->assertSet( 'perPage', 48 )
        ->set( 'perPage', 24 )
        ->assertSet( 'perPage', 16 );
} );

it( 'uses the configured default sort', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.catalog.default_sort', 'name' );

    Livewire::test( Index::class )->assertSet( 'sort', 'name' );
} );

it( 'lets a satellite change the sort options, keeping only engine sorts', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.catalog.sorts', static function ( array $sorts ): array {
        unset( $sorts['popularity'] );

        return [ ...$sorts, 'position' => 'Featured order', 'relevance' => 'Best match', 'cheapest-first' => 'Nope' ];
    } );

    makeProduct();

    Livewire::test( Index::class )
        ->assertSee( 'Featured order' )
        ->assertDontSee( 'Most popular' )
        ->assertDontSee( 'Best match' )
        ->assertDontSee( 'Nope' )
        ->set( 'sort', 'position' )
        ->assertSet( 'sort', 'position' )
        ->set( 'sort', 'popularity' )
        ->assertSet( 'sort', 'newest' );
} );

it( 'announces the result count in a live region', function (): void {
    makeProduct();
    makeProduct();

    Livewire::test( Index::class )
        ->assertSeeHtml( 'role="status"' )
        ->assertSeeHtml( 'aria-live="polite"' )
        ->assertSee( '2 products' );
} );

it( 'shows an empty state when the store has no products', function (): void {
    Livewire::test( Index::class )
        ->assertSee( 'No products to show' )
        ->assertSee( '0 products' )
        ->assertDontSee( 'Sort by' );
} );

it( 'narrows the listing by category, tag, featured flag, or hand-picked ids', function ( Closure $props, array $see, array $dontSee ): void {
    $parent   = ProductCategory::factory()->create( [ 'slug' => 'kitchen' ] );
    $child    = ProductCategory::factory()->create( [ 'slug' => 'mugs', 'parent_id' => $parent->id ] );
    $tag      = ProductTag::factory()->create( [ 'slug' => 'gift' ] );
    $mug      = makeProduct( 1000, [ 'name' => 'Mug', 'is_featured' => true ] );
    $pan      = makeProduct( 1000, [ 'name' => 'Pan' ] );
    $scarf    = makeProduct( 1000, [ 'name' => 'Scarf' ] );

    $mug->categories()->attach( $child->id );
    $pan->categories()->attach( $parent->id );
    $scarf->tags()->attach( $tag->id );

    $component = Livewire::test( Index::class, $props( compact( 'parent', 'child', 'tag', 'mug', 'pan', 'scarf' ) ) );

    foreach ( $see as $name ) {
        $component->assertSee( $name );
    }

    foreach ( $dontSee as $name ) {
        $component->assertDontSee( $name );
    }
} )->with( [
    'category with sub-categories' => [ fn ( array $m ): array => [ 'category' => $m['parent']->id ], [ 'Mug', 'Pan' ], [ 'Scarf' ] ],
    'category by slug'             => [ fn ( array $m ): array => [ 'category' => 'mugs' ], [ 'Mug' ], [ 'Pan', 'Scarf' ] ],
    'tag'                          => [ fn ( array $m ): array => [ 'tag' => 'gift' ], [ 'Scarf' ], [ 'Mug', 'Pan' ] ],
    'featured'                     => [ fn ( array $m ): array => [ 'featured' => true ], [ 'Mug' ], [ 'Pan', 'Scarf' ] ],
    'hand-picked ids'              => [ fn ( array $m ): array => [ 'ids' => [ $m['pan']->id, $m['scarf']->id, 0, -4 ] ], [ 'Pan', 'Scarf' ], [ 'Mug' ] ],
    'unknown category'             => [ fn ( array $m ): array => [ 'category' => 'nope' ], [ 'No products to show' ], [ 'Mug', 'Pan', 'Scarf' ] ],
] );

it( 'shows a heading above the grid when given one', function (): void {
    Livewire::test( Index::class, [ 'heading' => 'Best sellers' ] )
        ->assertSeeHtml( '<h2 id="ecommerce-catalog-heading"' )
        ->assertSee( 'Best sellers' );
} );

it( 'keeps narrowing props out of the browser\'s reach', function (): void {
    expect( fn () => Livewire::test( Index::class, [ 'featured' => true ] )->set( 'featured', false ) )
        ->toThrow( CannotUpdateLockedPropertyException::class );
} );

it( 'adds a simple product to a new cart from its card', function (): void {
    $mug = makeProduct( 1200, [ 'name' => 'Mug' ] );

    $component = Livewire::test( Index::class )
        ->call( 'quickAdd', $mug->id )
        ->assertDispatched( 'ecommerce-cart-updated', count: 1 );

    $cart = Cart::query()->sole();

    expect( $cart->items()->sole()->product_id )->toBe( $mug->id )
        ->and( Cookie::queued( GuestCartCookie::name() )?->getValue() )->toBe( $cart->token )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Added to your cart' );
} );

it( 'adds again to the same cart', function (): void {
    $mug = makeProduct( 1200, [ 'name' => 'Mug' ] );

    Livewire::test( Index::class )
        ->call( 'quickAdd', $mug->id )
        ->call( 'quickAdd', $mug->id )
        ->assertDispatched( 'ecommerce-cart-updated', count: 2 );

    expect( Cart::query()->count() )->toBe( 1 );
} );

it( 'explains why a product could not be added', function ( Closure $make, string $message ): void {
    $product = $make();

    $component = Livewire::test( Index::class )
        ->call( 'quickAdd', $product->id )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )
        ->toContain( 'Not added to your cart' )
        ->toContain( $message );
} )->with( [
    'out of stock' => [ function (): Product {
        $product = makeProduct();
        InventoryItem::factory()->create( [ 'stockable_type' => $product->getMorphClass(), 'stockable_id' => $product->id, 'track_inventory' => true, 'allow_backorder' => false, 'quantity_on_hand' => 0 ] );

        return $product;
    }, 'stock' ],
    'not visible'  => [ fn (): Product => Product::factory()->draft()->create(), 'That product is not available.' ],
] );

it( 'throttles quick adds per cart', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.cart.mutate.per_cart', 2 );

    $mug = makeProduct( 1200, [ 'name' => 'Mug' ] );

    // The first add happens before there is a cart, so it counts against the
    // shopper's IP; the next two count against the new cart and use up its
    // allowance.
    $component = Livewire::test( Index::class )
        ->call( 'quickAdd', $mug->id )
        ->call( 'quickAdd', $mug->id )
        ->call( 'quickAdd', $mug->id )
        ->call( 'quickAdd', $mug->id );

    expect( Cart::query()->sole()->items()->sole()->quantity )->toBe( 3 )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Too many attempts' );
} );

it( 'eager-loads what the cards need, so listing more products adds no image queries', function (): void {
    Model::preventLazyLoading();

    $imageQueries = static function ( int $products ): int {
        Product::query()->delete();

        foreach ( range( 1, $products ) as $i ) {
            $product = makeProduct( 1000 + $i );
            ProductImage::factory()->create( [ 'product_id' => $product->id, 'media_id' => null, 'image_url' => 'https://cdn.example.test/' . $i . '.jpg' ] );
        }

        $queries = [];
        DB::listen( static function ( $query ) use ( &$queries ): void {
            $queries[] = $query->sql;
        } );

        Livewire::test( Index::class )->assertSee( 'https://cdn.example.test/' . $products . '.jpg' );

        $count = count( array_filter( $queries, static fn ( string $sql ): bool => str_contains( $sql, 'ecommerce_product_images' ) ) );
        DB::flushQueryLog();

        return $count;
    };

    $few  = $imageQueries( 2 );
    $many = $imageQueries( 8 );

    expect( $few )->toBe( 1 )
        ->and( $many )->toBe( $few );
} );

it( 'escapes product names in toasts, which the toast container renders as HTML', function (): void {
    $product = makeProduct( 1200, [ 'name' => '<img src=x onerror=alert(1)>' ] );

    $toasts = json_encode( Livewire::test( Index::class )->call( 'quickAdd', $product->id )->effects['xjs'] ?? [] );

    expect( $toasts )->toContain( 'u0026lt;img src=x onerror=alert(1)' )
        ->not->toContain( 'u003Cimg' );
} );

it( 'adds to the cart in hosts that prevent lazy loading', function (): void {
    Model::preventLazyLoading();

    $mug = makeProduct( 1200, [ 'name' => 'Mug' ] );

    Livewire::test( Index::class )
        ->call( 'quickAdd', $mug->id )
        ->assertDispatched( 'ecommerce-cart-updated', count: 1 );
} );
