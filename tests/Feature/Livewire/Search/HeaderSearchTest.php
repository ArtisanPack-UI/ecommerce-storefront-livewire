<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Registries\SearchProviderRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Search\HeaderSearch;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Fixtures\Search\FakeSearchProvider;

beforeEach( function (): void {
    CategoryTree::flush();

    foreach ( [ 'Mug', 'Mugs & cups', 'Muggle', 'Mug rack', 'Mug tree', 'Mug cosy' ] as $index => $name ) {
        makeProduct( 1000 + $index * 100, [ 'name' => $name . ' item' ] );
    }

    $this->cups = ProductCategory::factory()->create( [ 'name' => 'Mugs and cups', 'slug' => 'mugs-and-cups' ] );
    ProductCategory::factory()->create( [ 'name' => 'Plates', 'slug' => 'plates' ] );
} );

afterEach( function (): void {
    RateLimiter::clear( 'ecommerce.catalog.read' );
} );

it( 'renders a combobox in a search form without suggestions at first', function (): void {
    Livewire::test( HeaderSearch::class )
        ->assertOk()
        ->assertSeeHtml( 'role="search"' )
        ->assertSeeHtml( 'role="combobox"' )
        ->assertSeeHtml( 'aria-controls="ecommerce-header-search-results"' )
        ->assertSeeHtml( 'role="listbox"' )
        ->assertSeeHtml( 'action="' . route( 'artisanpack.ecommerce.storefront.search' ) . '"' )
        ->assertDontSeeHtml( 'data-header-search-product=' );
} );

it( 'starts from the search page\'s term without looking it up', function (): void {
    $provider = new FakeSearchProvider();
    app( SearchProviderRegistry::class )->register( $provider->key(), $provider );
    config( [ 'artisanpack.ecommerce.search.provider' => $provider->key() ] );

    Livewire::withQueryParams( [ 'q' => 'mug' ] )
        ->test( HeaderSearch::class )
        ->assertSet( 'q', 'mug' );

    expect( $provider->queries )->toBe( [] );
} );

it( 'suggests the top five products with image, name, price, and matching categories', function (): void {
    $component = Livewire::test( HeaderSearch::class )
        ->set( 'q', 'mug' )
        ->assertSeeHtml( 'data-header-search-category="' . $this->cups->id . '"' )
        ->assertSee( 'Mugs and cups' )
        ->assertDontSee( 'Plates' )
        ->assertSee( '$10.00' )
        ->assertSee( 'See all results for "mug"' )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.storefront.search', [ 'q' => 'mug' ] ) . '"' )
        ->assertSee( '6 suggestions' );

    expect( substr_count( $component->html(), 'data-header-search-product=' ) )->toBe( 5 )
        ->and( substr_count( $component->html(), 'role="option"' ) )->toBe( 7 );
} );

it( 'numbers every option for aria-activedescendant', function (): void {
    $html = Livewire::test( HeaderSearch::class )->set( 'q', 'mug' )->html();

    foreach ( range( 0, 6 ) as $index ) {
        expect( $html )->toContain( 'id="ecommerce-header-search-results-option-' . $index . '"' );
    }
} );

it( 'waits for two characters', function (): void {
    Livewire::test( HeaderSearch::class )
        ->set( 'q', 'm' )
        ->assertDontSeeHtml( 'data-header-search-product=' )
        ->assertDontSee( 'See all results' );
} );

it( 'says when nothing matches', function (): void {
    Livewire::test( HeaderSearch::class )
        ->set( 'q', 'zeppelin' )
        ->assertSeeHtml( 'data-header-search-empty' )
        ->assertSee( 'No matches for "zeppelin".' )
        ->assertSee( '0 suggestions' );
} );

it( 'rejects a term over 200 characters without looking it up', function (): void {
    Livewire::test( HeaderSearch::class )
        ->set( 'q', str_repeat( 'm', 201 ) )
        ->assertHasErrors( [ 'q' ] )
        ->assertDontSeeHtml( 'data-header-search-product=' );
} );

it( 'tells the shopper to press Enter when the provider fails', function (): void {
    $provider = new FakeSearchProvider( fails: true );
    app( SearchProviderRegistry::class )->register( $provider->key(), $provider );
    config( [ 'artisanpack.ecommerce.search.provider' => $provider->key() ] );

    Livewire::test( HeaderSearch::class )
        ->set( 'q', 'mug' )
        ->assertOk()
        ->assertSeeHtml( 'data-header-search-failed' )
        ->assertDontSee( 'See all results' );
} );

it( 'counts lookups against the catalog read limit', function (): void {
    config( [ 'artisanpack.ecommerce.rate_limits.catalog.read.per_ip' => 2 ] );

    $component = Livewire::test( HeaderSearch::class );

    $component->set( 'q', 'mug' )->assertDontSeeHtml( 'data-header-search-throttled' );
    $component->set( 'q', 'mugs' )->assertDontSeeHtml( 'data-header-search-throttled' );
    $component->set( 'q', 'mug r' )
        ->assertSeeHtml( 'data-header-search-throttled' )
        ->assertDontSeeHtml( 'data-header-search-product=' );
} );

it( 'keeps a custom input id usable', function (): void {
    Livewire::test( HeaderSearch::class, [ 'inputId' => 'mobile-search' ] )
        ->assertSeeHtml( 'aria-controls="mobile-search-results"' );

    Livewire::test( HeaderSearch::class, [ 'inputId' => '"><script>' ] )
        ->assertSet( 'inputId', 'ecommerce-header-search' );
} );

it( 'is in the package layout and available as a Blade component for host headers', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertOk()
        ->assertSeeLivewire( HeaderSearch::class );

    expect( Blade::render( '<x-artisanpack-ec-search-box input-id="host-search" class="host" />' ) )
        ->toContain( 'data-search-box' )
        ->toContain( 'class="host"' )
        ->toContain( 'aria-controls="host-search-results"' )
        ->and( view( 'ecommerce-storefront::partials.header.search' )->render() )
        ->toContain( 'data-header-search' );
} );
