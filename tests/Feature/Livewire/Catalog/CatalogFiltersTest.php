<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CatalogQuery;
use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductAttributeValue;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\Index;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Gives a product an attribute value (with an optional swatch).
 */
function giveAttribute( Product $product, string $key, string $label, string $value, string $valueLabel, ?string $swatch = null ): void
{
    $attribute = ProductAttribute::query()->firstOrCreate(
        [ 'product_id' => $product->id, 'key' => $key ],
        [ 'label' => $label, 'is_variation' => false ],
    );

    ProductAttributeValue::factory()->create( [ 'product_attribute_id' => $attribute->id, 'value' => $value, 'label' => $valueLabel, 'swatch' => $swatch ] );
}

beforeEach( function (): void {
    CategoryTree::flush();

    $this->shoes   = ProductCategory::factory()->create( [ 'name' => 'Shoes', 'slug' => 'shoes' ] );
    $this->boots   = ProductCategory::factory()->create( [ 'name' => 'Boots', 'slug' => 'boots', 'parent_id' => $this->shoes->id ] );
    $this->hats    = ProductCategory::factory()->create( [ 'name' => 'Hats', 'slug' => 'hats' ] );
    $this->summer  = ProductTag::factory()->create( [ 'name' => 'Summer', 'slug' => 'summer' ] );

    $this->runner  = makeProduct( 6000, [ 'name' => 'Red runner', 'avg_rating' => 4.5 ] );
    $this->hiker   = makeProduct( 9000, [ 'name' => 'Blue hiker', 'avg_rating' => 3.2 ] );
    $this->sandal  = makeProduct( 3000, [ 'name' => 'Red sandal', 'avg_rating' => 0 ], 4000 );
    $this->panama  = makeProduct( 5000, [ 'name' => 'Panama hat', 'avg_rating' => 2.0 ] );

    $this->runner->categories()->attach( $this->shoes->id );
    $this->hiker->categories()->attach( $this->boots->id );
    $this->sandal->categories()->attach( $this->shoes->id );
    $this->panama->categories()->attach( $this->hats->id );
    $this->sandal->tags()->attach( $this->summer->id );
    $this->panama->tags()->attach( $this->summer->id );

    giveAttribute( $this->runner, 'colour', 'Colour', 'red', 'Red', '#ff0000' );
    giveAttribute( $this->sandal, 'colour', 'Colour', 'red', 'Red', '#ff0000' );
    giveAttribute( $this->hiker, 'colour', 'Colour', 'blue', 'Blue', '#0000ff' );
    giveAttribute( $this->runner, 'size', 'Size', '42', '42' );
    giveAttribute( $this->hiker, 'size', 'Size', '42', '42' );
    giveAttribute( $this->sandal, 'size', 'Size', '40', '40' );

    setStock( $this->hiker, 0 );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.catalog.filters' );
} );

it( 'renders every filter group with facet counts', function (): void {
    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( 'data-filters-sidebar' )
        ->assertSeeHtml( 'data-filters-drawer' )
        ->assertSeeHtml( 'data-filter-group="category"' )
        ->assertSeeHtml( 'data-filter-group="tag"' )
        ->assertSeeHtml( 'data-filter-group="price"' )
        ->assertSeeHtml( 'data-filter-group="attr.colour"' )
        ->assertSeeHtml( 'data-filter-group="attr.size"' )
        ->assertSeeHtml( 'data-filter-group="in_stock"' )
        ->assertSeeHtml( 'data-filter-group="on_sale"' )
        ->assertSeeHtml( 'data-filter-group="rating"' )
        ->assertSee( 'Boots' )
        ->assertSee( 'Summer' )
        ->assertSee( 'In stock only (3)' )
        ->assertSee( 'On sale (1)' )
        ->assertSeeHtml( 'background-color: #ff0000' );
} );

it( 'filters the shopper\'s example: size 42, under €80, in stock', function (): void {
    Livewire::test( Index::class )
        ->set( 'attributeFilters.size', [ '42' ] )
        ->set( 'priceMax', 8000 )
        ->set( 'inStock', true )
        ->assertSee( 'Red runner' )
        ->assertDontSee( 'Blue hiker' )
        ->assertDontSee( 'Red sandal' )
        ->assertDontSee( 'Panama hat' )
        ->assertSee( '1 product' );
} );

it( 'filters by a category with its sub-categories', function (): void {
    Livewire::test( Index::class )
        ->set( 'categoryFilter', 'shoes' )
        ->assertSee( 'Red runner' )
        ->assertSee( 'Blue hiker' )
        ->assertDontSee( 'Panama hat' );
} );

it( 'filters by tag, sale, and minimum rating', function ( string $property, mixed $value, array $see, array $dontSee ): void {
    $component = Livewire::test( Index::class )->set( $property, $value );

    foreach ( $see as $name ) {
        $component->assertSee( $name );
    }

    foreach ( $dontSee as $name ) {
        $component->assertDontSee( $name );
    }
} )->with( [
    'tag'      => [ 'tagFilter', 'summer', [ 'Red sandal', 'Panama hat' ], [ 'Red runner', 'Blue hiker' ] ],
    'on sale'  => [ 'onSale', true, [ 'Red sandal' ], [ 'Red runner', 'Blue hiker', 'Panama hat' ] ],
    'rating 4' => [ 'minRating', 4, [ 'Red runner' ], [ 'Blue hiker', 'Red sandal', 'Panama hat' ] ],
    'price'    => [ 'priceMin', 5500, [ 'Red runner', 'Blue hiker' ], [ 'Red sandal', 'Panama hat' ] ],
] );

it( 'counts a group\'s options without its own selection, so other values stay selectable', function (): void {
    $html = Livewire::test( Index::class )
        ->set( 'attributeFilters.colour', [ 'red' ] )
        ->assertSee( 'Red runner' )
        ->assertDontSee( 'Blue hiker' )
        ->html();

    // Blue still counts 1 (the hiker), so it is not disabled.
    expect( $html )->toMatch( '/value="blue"\s+class="peer sr-only"[^>]*>/' )
        ->and( $html )->not->toMatch( '/value="blue"[^>]*disabled/' );
} );

it( 'disables, but still shows, options the other filters rule out', function (): void {
    $html = Livewire::test( Index::class )
        ->set( 'categoryFilter', 'hats' )
        ->html();

    // No hat has a colour: red is shown and disabled.
    expect( $html )->toMatch( '/value="red"[^>]*disabled/' )
        ->and( $html )->toContain( 'Boots' );
} );

it( 'keeps a selected option enabled even when it matches nothing', function (): void {
    $html = Livewire::test( Index::class )
        ->set( 'categoryFilter', 'hats' )
        ->set( 'attributeFilters.colour', [ 'red' ] )
        ->assertSee( 'No products match your filters' )
        ->html();

    expect( $html )->not->toMatch( '/value="red"[^>]*disabled/' );
} );

it( 'shows active filters as removable badges and clears them', function (): void {
    $component = Livewire::test( Index::class )
        ->set( 'attributeFilters.colour', [ 'red' ] )
        ->set( 'onSale', true )
        ->set( 'priceMax', 8000 )
        ->assertSeeHtml( 'data-active-filters' )
        ->assertSee( 'Colour: Red' )
        ->assertSee( 'On sale' )
        ->assertSee( 'Up to $80.00' );

    $component->call( 'removeFilter', 'attr.colour', 'red' )
        ->assertSet( 'attributeFilters', [] )
        ->assertSet( 'onSale', true )
        ->call( 'removeFilter', 'price' )
        ->assertSet( 'priceMax', null )
        ->call( 'clearFilters' )
        ->assertSet( 'onSale', false )
        ->assertDontSeeHtml( 'data-active-filters' );
} );

it( 'restores every filter from the query string', function (): void {
    Livewire::withQueryParams( [ 'category' => 'shoes', 'attr' => [ 'colour' => [ 'red' ] ], 'price_max' => 8000, 'in_stock' => 1, 'rating' => 4 ] )
        ->test( Index::class )
        ->assertSet( 'categoryFilter', 'shoes' )
        ->assertSet( 'attributeFilters', [ 'colour' => [ 'red' ] ] )
        ->assertSet( 'priceMax', 8000 )
        ->assertSet( 'inStock', true )
        ->assertSet( 'minRating', 4 )
        ->assertSee( 'Red runner' )
        ->assertSee( '1 product' );
} );

it( 'drops filter values that can\'t apply', function ( string $property, mixed $value, mixed $expected ): void {
    Livewire::test( Index::class )
        ->set( $property, $value )
        ->assertSet( $property, $expected );
} )->with( [
    'unknown category'   => [ 'categoryFilter', 'nope', '' ],
    'unknown tag'        => [ 'tagFilter', 'winter', '' ],
    'negative price'     => [ 'priceMin', -50, null ],
    'rating too high'    => [ 'minRating', 9, 0 ],
    'malformed key'      => [ 'attributeFilters', [ 'bad key!' => [ 'x' ], 'colour' => [ '', 'red', 'red' ] ], [ 'colour' => [ 'red' ] ] ],
    'unknown extra'      => [ 'extraFilters', [ 'nope' => [ 'x' ] ], [] ],
] );

it( 'swaps a reversed price range', function (): void {
    Livewire::withQueryParams( [ 'price_min' => 9000, 'price_max' => 1000 ] )
        ->test( Index::class )
        ->assertSet( 'priceMin', 1000 )
        ->assertSet( 'priceMax', 9000 );
} );

it( 'starts again from page one when a filter changes', function (): void {
    foreach ( range( 1, 14 ) as $i ) {
        makeProduct( 1000 + $i, [ 'name' => sprintf( 'Item %02d', $i ) ] );
    }

    $component = Livewire::test( Index::class )->set( 'perPage', 12 )->call( 'gotoPage', 2 );

    expect( $component->get( 'paginators' )['page'] )->toBe( 2 );

    $component->set( 'onSale', true );

    expect( $component->get( 'paginators' )['page'] )->toBe( 1 );
} );

it( 'offers only sub-categories of the scope on a category listing', function (): void {
    Livewire::test( Index::class, [ 'category' => $this->shoes->id ] )
        ->assertSeeHtml( 'value="boots"' )
        ->assertDontSeeHtml( 'value="hats"' )
        ->set( 'categoryFilter', 'hats' )
        ->assertSet( 'categoryFilter', '' )
        ->set( 'categoryFilter', 'boots' )
        ->assertSee( 'Blue hiker' )
        ->assertDontSee( 'Red runner' );
} );

it( 'leaves the tag filter off a tag listing', function (): void {
    Livewire::test( Index::class, [ 'tag' => 'summer' ] )
        ->assertDontSeeHtml( 'data-filter-group="tag"' )
        ->set( 'tagFilter', 'summer' )
        ->assertSet( 'tagFilter', '' );
} );

it( 'offers only the configured attributes', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.catalog.filter_attributes', [ 'size' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-filter-group="attr.size"' )
        ->assertDontSeeHtml( 'data-filter-group="attr.colour"' )
        ->set( 'attributeFilters', [ 'colour' => [ 'red' ] ] )
        ->assertSet( 'attributeFilters', [] );
} );

it( 'lets a satellite remove a group or add its own', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.catalog.filters', static function ( array $filters ): array {
        unset( $filters['rating'] );

        $filters['hats-only'] = [
            'type'  => 'toggle',
            'label' => 'Only hats',
            'apply' => static fn ( CatalogQuery $query ) => $query->inCategory( 'hats' ),
        ];

        $filters['broken'] = [ 'type' => 'toggle', 'label' => 'No apply' ];

        return $filters;
    } );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'data-filter-group="rating"' )
        ->assertSeeHtml( 'data-filter-group="hats-only"' )
        ->assertDontSeeHtml( 'data-filter-group="broken"' )
        ->set( 'extraFilters.hats-only', true )
        ->assertSet( 'extraFilters', [ 'hats-only' => '1' ] )
        ->assertSee( 'Panama hat' )
        ->assertDontSee( 'Red runner' )
        ->assertSee( 'Only hats' )
        ->set( 'minRating', 4 )
        ->assertSet( 'minRating', 4 )
        ->assertSee( 'Panama hat' );
} );

it( 'hides the filters when the listing is not filterable', function (): void {
    Livewire::test( Index::class, [ 'filterable' => false ] )
        ->assertDontSeeHtml( 'data-filters-sidebar' )
        ->assertDontSeeHtml( 'data-filters-drawer' );
} );

it( 'offers a mobile drawer with a "Show N results" button that closes it', function (): void {
    Livewire::test( Index::class )
        ->set( 'onSale', true )
        ->assertSeeHtml( 'id="ecommerce-catalog-filters-drawer"' )
        ->assertSeeHtml( 'x-on:ecommerce-filters-open.window="open = true"' )
        ->assertSeeHtml( '@keydown.window.escape="close()"' )
        ->assertSee( 'Show 1 result' )
        ->assertSee( 'Filters (1)' );
} );

it( 'announces the new result count in a live region', function (): void {
    Livewire::test( Index::class )
        ->assertSee( '4 products' )
        ->set( 'tagFilter', 'summer' )
        ->assertSeeHtml( 'aria-live="polite"' )
        ->assertSee( '2 products' );
} );

it( 'renders without filters when the engine\'s facets fail', function (): void {
    $this->app->bind( CatalogQuery::class, function ( $app ) {
        return new class( $app->make( CategoryTree::class ), $app->make( ArtisanPackUI\Ecommerce\Services\StoreCurrencies::class ), $app->make( ArtisanPackUI\Ecommerce\Registries\CurrencyRateProviderRegistry::class ) ) extends CatalogQuery {
            public function facets(): array
            {
                throw new RuntimeException( 'Facets are down.' );
            }
        };
    } );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSee( 'Red runner' )
        ->assertDontSeeHtml( 'data-filters-sidebar' );
} );

it( 'keeps the number of filter queries independent of the catalog size', function (): void {
    $queries = static function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::withQueryParams( [ 'per_page' => 12 ] )->test( Index::class )->set( 'attributeFilters.colour', [ 'red' ] );

        $count = count( DB::getQueryLog() );
        DB::disableQueryLog();

        return $count;
    };

    // Enough red products to fill a page either way, so the cards cost the same.
    foreach ( range( 1, 12 ) as $i ) {
        giveAttribute( makeProduct( 1000 + $i ), 'colour', 'Colour', 'red', 'Red', '#ff0000' );
    }

    $few = $queries();

    foreach ( range( 1, 12 ) as $i ) {
        giveAttribute( makeProduct( 2000 + $i ), 'colour', 'Colour', 'red', 'Red', '#ff0000' );
    }

    // Allow for pagination differences; per-product growth would be dozens.
    expect( $queries() )->toBeLessThanOrEqual( $few + 2 );
} );
