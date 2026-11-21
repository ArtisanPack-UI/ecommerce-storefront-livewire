<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\RecentlyViewed;
use Livewire\Livewire;

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds' );
} );

/**
 * Answers the recently-viewed filter with `$ids`, recording what it was asked.
 *
 * @param  array<int, mixed>  $ids  The ids to return.
 */
function recentlyViewedIds( array $ids, ?array &$asked = null ): void
{
    addFilter( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds', static function ( array $current, int $limit, $customer, ?Product $exclude ) use ( $ids, &$asked ): array {
        $asked = [ 'limit' => $limit, 'exclude' => $exclude?->id ];

        return $ids;
    }, 10, 4 );
}

it( 'renders the products the satellite returns, most recent first', function (): void {
    $kettle  = makeProduct( 1000, [ 'name' => 'Kettle' ] );
    $toaster = makeProduct( 2000, [ 'name' => 'Toaster' ] );

    recentlyViewedIds( [ $toaster->id, $kettle->id ] );

    $html = Livewire::withoutLazyLoading()->test( RecentlyViewed::class, [ 'heading' => 'Seen lately' ] )
        ->assertOk()
        ->assertSee( 'Seen lately' )
        ->html();

    expect( strpos( $html, 'Toaster' ) )->toBeLessThan( strpos( $html, 'Kettle' ) );
} );

it( 'renders compact rows: a thumbnail, one stretched link and the price', function (): void {
    $kettle = makeProduct( 3400, [ 'name' => 'Chalk bud vase', 'slug' => 'chalk-bud-vase' ] );

    recentlyViewedIds( [ $kettle->id ] );

    $html = Livewire::withoutLazyLoading()->test( RecentlyViewed::class, [ 'columns' => 3 ] )
        ->assertSee( 'Recently viewed' )
        ->assertSee( '$34.00' )
        ->html();

    expect( $html )
        ->toMatch( '/<ul role="list" class="ec-recent__list" data-columns="3">/' )
        ->toMatch( '/<article class="apui-card ec-recent__card" data-surface="default"\s+data-interactive\s+data-recently-viewed-product="' . $kettle->id . '">/' )
        ->toContain( '<div class="ec-recent__media" aria-hidden="true">' )
        ->toMatch( '/<h3 class="ec-recent__name">(\s|<!--.*?-->)*<a href="[^"]*\/chalk-bud-vase" data-product-link>Chalk bud vase<\/a>/' )
        ->toContain( 'ec-price ec-recent__price' )
        ->and( substr_count( $html, '<a ' ) )->toBe( 1 )
        ->and( $html )->not->toContain( 'data-quick-add' );
} );

it( 'goes through the product card filter for each row', function (): void {
    $product = makeProduct( 1000, [ 'name' => 'Seaglass carafe' ] );

    recentlyViewedIds( [ $product->id ] );
    addFilter( 'ap.ecommerceStorefrontLivewire.catalog.productCard', static fn ( array $card ): array => [ ...$card, 'name' => 'Renamed carafe', 'url' => null ] );

    try {
        $html = Livewire::withoutLazyLoading()->test( RecentlyViewed::class )->html();
    } finally {
        removeAllFilters( 'ap.ecommerceStorefrontLivewire.catalog.productCard' );
    }

    expect( $html )
        ->toContain( 'Renamed carafe' )
        ->not->toContain( 'data-product-link' )
        ->not->toContain( 'data-interactive' );
} );

it( 'leaves no DaisyUI or Tailwind classes', function (): void {
    recentlyViewedIds( [ makeProduct( 1000 )->id ] );

    expect( classTokens( Livewire::withoutLazyLoading()->test( RecentlyViewed::class )->html() ) )
        ->not->toContain( 'flex', 'flex-col', 'gap-4', 'grid', 'text-2xl', 'font-bold', 'lg:grid-cols-4' );
} );

it( 'drops hidden and missing-type products before cutting the list to the limit', function (): void {
    $hidden  = [ Product::factory()->draft()->create( [ 'name' => 'Draft one' ] ), Product::factory()->draft()->create( [ 'name' => 'Draft two' ] ) ];
    $missing = Product::factory()->create( [ 'name' => 'Uninstalled thing', 'type' => 'uninstalled-satellite-type' ] );
    $shown   = collect( [ 'Alpha', 'Bravo', 'Charlie', 'Delta' ] )->map( fn ( string $name ): Product => makeProduct( 1000, [ 'name' => $name ] ) );

    recentlyViewedIds( [ $hidden[0]->id, $hidden[1]->id, $missing->id, ...$shown->pluck( 'id' )->all() ] );

    $component = Livewire::withoutLazyLoading()->test( RecentlyViewed::class, [ 'limit' => 4 ] );

    expect( substr_count( $component->html(), 'wire:key="recently-viewed-' ) )->toBe( 4 );

    $component->assertSee( [ 'Alpha', 'Bravo', 'Charlie', 'Delta' ] )
        ->assertDontSee( 'Draft one' )
        ->assertDontSee( 'Uninstalled thing' );
} );

it( 'leaves out the product being viewed and passes it and the limit to the satellite', function (): void {
    $current = makeProduct( 1000, [ 'name' => 'Current kettle' ] );
    $other   = makeProduct( 1000, [ 'name' => 'Other kettle' ] );

    recentlyViewedIds( [ $current->id, $other->id ], $asked );

    Livewire::withoutLazyLoading()->test( RecentlyViewed::class, [ 'product' => $current, 'limit' => 2 ] )
        ->assertSee( 'Other kettle' )
        ->assertDontSee( 'Current kettle' );

    expect( $asked )->toBe( [ 'limit' => 2, 'exclude' => $current->id ] );
} );

it( 'takes at most 50 ids from the satellite', function (): void {
    $late = makeProduct( 1000, [ 'name' => 'Fifty-first' ] );

    recentlyViewedIds( [ ...range( 900001, 900050 ), $late->id ] );

    Livewire::withoutLazyLoading()->test( RecentlyViewed::class )
        ->assertDontSee( 'Fifty-first' )
        ->assertDontSee( 'Recently viewed' );
} );

it( 'renders nothing without history or for ids that are not products', function ( mixed $ids ): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds', static fn (): mixed => $ids );

    Livewire::withoutLazyLoading()->test( RecentlyViewed::class )
        ->assertOk()
        ->assertSeeHtml( 'data-recently-viewed' )
        ->assertDontSee( 'Recently viewed' );
} )->with( [
    'no history'      => [ [] ],
    'unknown ids'     => [ [ 999999, -3, 0 ] ],
    'not an array'    => [ 'kettle' ],
    'non-numeric ids' => [ [ 'abc', null ] ],
] );

it( 'renders nothing when the satellite throws', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds', static function (): array {
        throw new RuntimeException( 'History down.' );
    } );

    Livewire::withoutLazyLoading()->test( RecentlyViewed::class )
        ->assertOk()
        ->assertDontSee( 'Recently viewed' );
} );

it( 'loads with skeleton rows the size of the real ones, in the same grid', function (): void {
    $html = view( 'ecommerce-storefront::livewire.product.recently-viewed-placeholder', [ 'title' => 'Recently viewed', 'count' => 3, 'columns' => 2 ] )->render();

    expect( $html )
        ->toContain( '<span class="apui-sr-only" role="status">Loading recently viewed products…</span>' )
        ->toContain( '<ul class="ec-recent__list" data-columns="2" aria-busy="true" aria-hidden="true" data-skeleton="recent">' )
        ->and( substr_count( $html, 'class="apui-card ec-recent__card"' ) )->toBe( 3 )
        ->and( substr_count( $html, 'apui-skeleton ec-recent__skeleton-thumb' ) )->toBe( 3 )
        ->and( substr_count( $html, 'apui-skeleton ec-recent__skeleton-line' ) )->toBe( 6 );
} );

it( 'clamps the placeholder to 1–12 rows and 1–6 columns', function ( int $count, int $columns, int $rows, int $grid ): void {
    $html = view( 'ecommerce-storefront::livewire.product.recently-viewed-placeholder', [ 'title' => 'Recently viewed', 'count' => $count, 'columns' => $columns ] )->render();

    expect( substr_count( $html, 'class="ec-recent__item"' ) )->toBe( $rows )
        ->and( $html )->toContain( 'data-columns="' . $grid . '"' );
} )->with( [
    'none'     => [ 0, 0, 1, 1 ],
    'four'     => [ 4, 4, 4, 4 ],
    'too many' => [ 20, 9, 12, 6 ],
] );
