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
