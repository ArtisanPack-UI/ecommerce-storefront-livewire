<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductAttributeValue;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Registries\SearchProviderRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Search\Index;
use Livewire\Livewire;
use Tests\Fixtures\Search\FakeSearchProvider;

/**
 * Makes `$provider` the active search provider.
 */
function useSearchProvider( FakeSearchProvider $provider ): FakeSearchProvider
{
    app( SearchProviderRegistry::class )->register( $provider->key(), $provider );
    config( [ 'artisanpack.ecommerce.search.provider' => $provider->key() ] );

    return $provider;
}

beforeEach( function (): void {
    CategoryTree::flush();

    $this->shirts  = ProductCategory::factory()->create( [ 'name' => 'Shirts', 'slug' => 'shirts' ] );
    $this->linen   = makeProduct( 4500, [ 'name' => 'Linen shirt' ] );
    $this->trouser = makeProduct( 6000, [ 'name' => 'Linen trousers' ] );
    $this->mug     = makeProduct( 1200, [ 'name' => 'Stoneware mug' ] );

    $this->linen->categories()->attach( $this->shirts->id );

    foreach ( [ [ $this->linen, 'm' ], [ $this->trouser, 'l' ] ] as [ $product, $size ] ) {
        $attribute = ProductAttribute::factory()->create( [ 'product_id' => $product->id, 'key' => 'size', 'label' => 'Size', 'is_variation' => false ] );
        ProductAttributeValue::factory()->create( [ 'product_attribute_id' => $attribute->id, 'value' => $size, 'label' => strtoupper( $size ) ] );
    }
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.catalog.filters' );
} );

it( 'invites a search when there is no term', function (): void {
    Livewire::test( Index::class )
        ->assertOk()
        ->assertSee( 'What are you looking for?' )
        ->assertSeeHtml( 'data-empty-reason="idle"' )
        ->assertDontSee( 'Linen shirt' )
        ->assertDontSeeHtml( 'data-filters-sidebar' );
} );

it( 'lists matching products in the catalog grid, by relevance', function (): void {
    Livewire::withQueryParams( [ 'q' => 'linen' ] )
        ->test( Index::class )
        ->assertSet( 'q', 'linen' )
        ->assertSet( 'sort', 'relevance' )
        ->assertSee( 'Linen shirt' )
        ->assertSee( 'Linen trousers' )
        ->assertDontSee( 'Stoneware mug' )
        ->assertSee( '2 results for "linen"' )
        ->assertSeeHtml( 'data-product-card="' . $this->linen->id . '"' );
} );

it( 'offers relevance and the catalog sorts', function (): void {
    $component = Livewire::withQueryParams( [ 'q' => 'linen' ] )->test( Index::class );

    expect( array_keys( $component->viewData( 'sorts' ) ) )->toBe( [ 'relevance', 'newest', 'price', '-price', 'popularity', 'rating', 'name' ] );

    $component->set( 'sort', '-price' )->assertSet( 'sort', '-price' );

    expect( strpos( $component->html(), 'Linen trousers' ) )->toBeLessThan( strpos( $component->html(), 'Linen shirt' ) );

    $component->set( 'sort', 'bogus' )->assertSet( 'sort', 'relevance' );
} );

it( 'searches again, from page one, when the term changes', function (): void {
    Livewire::withQueryParams( [ 'q' => 'linen', 'page' => 2 ] )
        ->test( Index::class )
        ->set( 'q', '  mug ' )
        ->assertSet( 'q', 'mug' )
        ->assertSet( 'paginators.page', 1 )
        ->assertSee( 'Stoneware mug' )
        ->assertDontSee( 'Linen shirt' );
} );

it( 'builds the filter panel from the provider\'s facets and narrows by them', function (): void {
    $component = Livewire::withQueryParams( [ 'q' => 'linen' ] )
        ->test( Index::class )
        ->assertSeeHtml( 'data-filter-group="attr.size"' )
        ->assertSeeHtml( 'data-filter-group="category"' );

    $component->set( 'attributeFilters.size', [ 'm' ] )
        ->assertSee( 'Linen shirt' )
        ->assertDontSee( 'Linen trousers' )
        ->assertSee( '1 result for "linen"' )
        ->assertSeeHtml( 'data-active-filter="attr.size"' );

    $component->set( 'categoryFilter', 'shirts' )->assertSee( 'Linen shirt' );
} );

it( 'keeps the term and filters in the query string', function (): void {
    Livewire::withQueryParams( [ 'q' => 'linen', 'attr' => [ 'size' => [ 'l' ] ] ] )
        ->test( Index::class )
        ->assertSet( 'attributeFilters', [ 'size' => [ 'l' ] ] )
        ->assertSee( 'Linen trousers' )
        ->assertDontSee( 'Linen shirt' );
} );

it( 'passes the term, filters, sort, page, and currency to the active provider', function (): void {
    $provider = useSearchProvider( new FakeSearchProvider() );

    Livewire::withQueryParams( [ 'q' => 'linen', 'in_stock' => '1', 'sort' => 'price' ] )->test( Index::class );

    $query = $provider->queries[0];

    expect( $query->term )->toBe( 'linen' )
        ->and( $query->filters )->toBe( [ 'in_stock' => '1' ] )
        ->and( $query->sort )->toBe( 'price' )
        ->and( $query->page )->toBe( 1 )
        ->and( $query->perPage )->toBe( 24 )
        ->and( $query->currency )->toBe( 'USD' )
        ->and( $query->with )->toContain( 'images', 'prices', 'inventoryItems' )->toHaveKey( 'variants' );
} );

it( 'shows "did you mean" suggestions and searches for one', function (): void {
    useSearchProvider( new FakeSearchProvider( [ 'linen', 'LNEN', '' ] ) );

    Livewire::withQueryParams( [ 'q' => 'lnen' ] )
        ->test( Index::class )
        ->assertSee( 'No results for "lnen"' )
        ->assertSeeHtml( 'data-search-suggestions' )
        ->assertSeeInOrder( [ 'Did you mean:', 'linen' ] )
        ->call( 'useSuggestion', 'linen' )
        ->assertSet( 'q', 'linen' )
        ->assertSee( 'Linen shirt' );
} );

it( 'finds a typo through a typo-tolerant provider', function (): void {
    useSearchProvider( new FakeSearchProvider( aliases: [ 'lnen' => 'linen' ] ) );

    Livewire::withQueryParams( [ 'q' => 'lnen' ] )
        ->test( Index::class )
        ->assertSee( 'Linen shirt' );
} );

it( 'shows tips when nothing matches', function (): void {
    Livewire::withQueryParams( [ 'q' => 'zeppelin' ] )
        ->test( Index::class )
        ->assertSeeHtml( 'data-empty-reason="no-results"' )
        ->assertSee( 'Check the spelling.' )
        ->assertSee( 'Use fewer or more general words.' )
        ->assertDontSee( 'Remove a filter or two.' )
        ->assertDontSeeHtml( 'data-search-suggestions' );
} );

it( 'rejects a term over 200 characters without searching', function (): void {
    $provider = useSearchProvider( new FakeSearchProvider() );

    Livewire::test( Index::class )
        ->set( 'q', str_repeat( 'a', 201 ) )
        ->assertHasErrors( [ 'q' ] )
        ->assertSee( 'Search for at most 200 characters.' )
        ->assertSeeHtml( 'data-empty-reason="idle"' )
        ->set( 'q', 'linen' )
        ->assertHasNoErrors();

    expect( collect( $provider->queries )->pluck( 'term' )->unique()->all() )->toBe( [ 'linen' ] );
} );

it( 'says search is unavailable when the provider fails', function (): void {
    useSearchProvider( new FakeSearchProvider( fails: true ) );

    Livewire::withQueryParams( [ 'q' => 'linen' ] )
        ->test( Index::class )
        ->assertOk()
        ->assertSeeHtml( 'data-empty-reason="unavailable"' )
        ->assertSee( 'Search isn\'t available right now' )
        ->assertDontSeeHtml( 'data-filters-sidebar' );
} );

it( 'leaves custom filter groups out and tells callbacks the term', function (): void {
    $context = null;

    addFilter( 'ap.ecommerceStorefrontLivewire.catalog.filters', function ( array $filters, array $given ) use ( &$context ): array {
        $context = $given;

        $filters['material'] = [ 'type' => 'toggle', 'label' => 'Organic', 'apply' => fn ( $query ) => $query ];

        return $filters;
    }, 10, 2 );

    Livewire::withQueryParams( [ 'q' => 'linen' ] )
        ->test( Index::class )
        ->assertDontSeeHtml( 'data-filter-group="material"' );

    expect( $context['search'] )->toBe( 'linen' );
} );

it( 'only lists storefront-visible products', function (): void {
    Product::factory()->draft()->create( [ 'name' => 'Linen draft' ] );

    Livewire::withQueryParams( [ 'q' => 'linen' ] )
        ->test( Index::class )
        ->assertDontSee( 'Linen draft' );
} );

it( 'serves the search page with the term in the title, not indexed', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.search', [ 'q' => 'linen' ] ) )
        ->assertOk()
        ->assertHeader( 'X-Robots-Tag', 'noindex, follow' )
        ->assertSee( 'Search results for &quot;linen&quot;', false )
        ->assertSeeLivewire( Index::class )
        ->assertSee( 'Linen shirt' );
} );
