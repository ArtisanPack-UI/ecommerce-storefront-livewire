<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\Index;
use Livewire\Livewire;

/**
 * The markup without Livewire's `<!--[if BLOCK]>` markers.
 */
function withoutBlockMarkers( string $html ): string
{
    return (string) preg_replace( '/<!--\[if (?:END)?BLOCK\]><!\[endif\]-->/', '', $html );
}

/**
 * The catalog page and listing on the design system (#130).
 */
beforeEach( function (): void {
    CategoryTree::flush();

    $this->shoes = ProductCategory::factory()->create( [ 'name' => 'Shoes', 'slug' => 'shoes' ] );
    $this->boots = ProductCategory::factory()->create( [ 'name' => 'Boots', 'slug' => 'boots', 'parent_id' => $this->shoes->id ] );

    $this->runner = makeProduct( 6000, [ 'name' => 'Red runner', 'avg_rating' => 4.5 ] );
    $this->hiker  = makeProduct( 9000, [ 'name' => 'Blue hiker', 'avg_rating' => 3.2 ] );
    $this->sandal = makeProduct( 3000, [ 'name' => 'Red sandal' ], 4000 );

    $this->runner->categories()->attach( $this->shoes->id );
    $this->hiker->categories()->attach( $this->boots->id );
    $this->sandal->categories()->attach( $this->shoes->id );
} );

it( 'heads the catalog page with breadcrumbs, the "All products" title and the configured description', function (): void {
    config( [ 'artisanpack.ecommerce-storefront-livewire.catalog.description' => 'Stoneware for the everyday table.' ] );

    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->assertOk()->getContent();

    expect( $html )->toContain( 'class="apui-page-header ec-page__header"' )
        ->toMatch( '#<h1 data-scope="page-header" data-part="title"[^>]*>All products</h1>#' )
        ->toContain( 'Stoneware for the everyday table.' )
        ->toContain( 'aria-label="Breadcrumb"' )
        ->toContain( 'data-catalog-page' );
} );

it( 'leaves the description out when none is configured', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertOk()
        ->assertDontSee( 'data-scope="page-header" data-part="description"', false );
} );

it( 'lays the filters out as radio groups with counts and indented sub-categories', function (): void {
    $html = withoutBlockMarkers( Livewire::test( Index::class )->html() );

    preg_match( '#<fieldset class="apui-radio-group ec-filters__options" id="ecommerce-filters-sidebar-category".*?</fieldset>#s', $html, $category );

    expect( $category )->not->toBeEmpty()
        ->and( $category[0] )->toContain( '<legend data-scope="radio-group" data-part="label" class="ec-filters__legend">Category</legend>' )
        ->toMatch( '#data-depth="0"[^>]*>.*?>All</span>#s' )
        ->toMatch( '#data-depth="0".*?>Shoes</span>\s*<span data-scope="radio-group" data-part="item-meta" class="ec-filters__count">3</span>#s' )
        ->toMatch( '#data-depth="1".*?>Boots</span>\s*<span data-scope="radio-group" data-part="item-meta" class="ec-filters__count">1</span>#s' )
        ->and( $html )->toContain( '>All ratings</span>' )
        ->toContain( 'role="switch"' )
        ->toMatch( '/In stock only \(\d+\)/' )
        ->and( classTokens( $html ) )->not->toContain( 'radio', 'radio-sm', 'checkbox-sm', 'text-base-content/70', 'mb-2', 'text-sm', 'font-semibold', 'cursor-pointer', 'opacity-50' );
} );

it( 'puts the sidebar "Clear all", the chips and the drawer\'s count behind active filters', function (): void {
    $component = Livewire::test( Index::class );

    $component->assertDontSeeHtml( 'data-sidebar-clear-filters' )
        ->assertDontSeeHtml( 'data-active-filters' )
        ->assertSeeHtml( '>Filters</' )
        ->set( 'categoryFilter', 'shoes' )
        ->set( 'onSale', true );

    $html = $component->html();

    expect( $html )->toContain( 'data-sidebar-clear-filters' )
        ->toMatch( '#<button\s+type="button"\s+class="ec-chip"\s+data-variant="filter"\s+aria-label="Remove filter: Shoes"#' )
        ->toContain( 'aria-label="Remove filter: On sale"' )
        ->toContain( 'Filters (2)' )
        ->toContain( '2 applied' )
        ->toContain( 'Show 1 product' );
} );

it( 'stands skeleton cards in for the cards while the results load', function (): void {
    $html = Livewire::test( Index::class )->html();

    preg_match( '#<div class="ec-catalog__results" wire:loading\.delay\.attr="aria-busy" wire:target="([^"]+)" data-catalog-results>#', $html, $results );

    expect( $results )->not->toBeEmpty()
        ->and( $results[1] )->toContain( 'sort' )->toContain( 'categoryFilter' )->toContain( 'gotoPage' )->toContain( 'clearFilters' )
        ->and( $html )->toMatch( '#<ul role="list" class="ec-catalog__grid ec-catalog__skeleton"\s+aria-hidden="true" data-skeleton="card" data-catalog-skeleton>#' )
        ->and( substr_count( $html, 'class="apui-card ec-skeleton-card"' ) )->toBe( 3 )
        ->and( $html )->not->toContain( 'opacity-60' );
} );

it( 'lays the cards out in three columns beside the filters, and four without them', function (): void {
    expect( Livewire::test( Index::class )->html() )->toMatch( '#class="ec-catalog__layout"\s+data-with-filters#' )
        ->toMatch( '#<ul role="list" class="ec-catalog__grid"\s+data-catalog-grid>#' );

    expect( Livewire::test( Index::class, [ 'filterable' => false ] )->html() )->not->toContain( 'data-with-filters' )
        ->toMatch( '#<ul role="list" class="ec-catalog__grid"\s*data-wide\s*data-catalog-grid>#' );
} );

it( 'shows the no-match state in a card with "Clear filters"', function (): void {
    Livewire::test( Index::class )
        ->set( 'priceMin', 50000 )
        ->assertSeeHtml( 'data-empty-reason="no-match"' )
        ->assertSeeHtml( 'data-empty-state-card' )
        ->assertSee( 'No products match your filters' )
        ->assertSeeHtml( 'data-empty-clear-filters' );
} );

it( 'keeps the toolbar and pagination on the design system', function (): void {
    $html = Livewire::test( Index::class )->html();

    expect( $html )->toContain( 'class="apui-select ec-catalog__select ec-catalog__sort"' )
        ->toContain( 'class="apui-select ec-catalog__select ec-catalog__per-page"' )
        ->toContain( 'class="ec-catalog__count" role="status" aria-live="polite" aria-atomic="true"' )
        ->toContain( 'class="ec-catalog__pagination"' )
        ->and( classTokens( $html ) )->not->toContain( 'flex', 'flex-col', 'flex-wrap', 'gap-6', 'grid', 'grid-cols-2', 'sm:grid-cols-3', 'xl:grid-cols-4', 'lg:hidden', 'hidden', 'lg:block', 'rounded-full', 'text-2xl' );
} );
