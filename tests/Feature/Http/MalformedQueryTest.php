<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Orders;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index as Checkout;
use Livewire\Livewire;

/*
 * Crawlers and mangled share links send query strings no form would: an
 * array where a string belongs, words where a number belongs. Livewire 3
 * fails on a value that doesn't fit a typed `#[Url]` property, so every
 * page must answer these with 200 rather than 500 (audit C1).
 *
 * To cover another page, add it to `malformedQueryPages()` with the probes
 * that matter for it; every page also gets the shared listing probes when
 * `listing` is true.
 */

beforeEach( function (): void {
    $category = ProductCategory::factory()->create( [ 'name' => 'Clothing', 'slug' => 'clothing' ] );
    ProductCategory::factory()->create( [ 'name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $category->id ] );
    ProductTag::factory()->create( [ 'name' => 'Summer', 'slug' => 'summer' ] );
    makeProduct( 2500, [ 'name' => 'Linen shirt', 'slug' => 'linen-shirt' ] );

    CategoryTree::flush();
} );

/**
 * The probes every product listing (catalog, category, tag, search) gets.
 *
 * @return array<int, string>
 */
function malformedListingProbes(): array
{
    return [
        'per_page=abc',
        'per_page[]=1',
        'sort[]=x',
        'sort=nope',
        'attr=red',
        'attr[size]=m',
        'attr[size][x][]=m',
        'f=x',
        'min_price=abc',
        'price_min=cheap',
        'price_min[]=1',
        'price_max=99999999999999999999999',
        'page=abc',
        'page[]=2',
        'rating=x',
        'rating[]=4',
        'category[]=a',
        'tag[]=a',
        'in_stock[]=1',
        'in_stock=maybe',
        'on_sale[]=1',
    ];
}

/**
 * The pages to probe: route name, route parameters, whether the shared
 * listing probes apply, and the page's own probes.
 *
 * @return array<string, array{route: string, parameters: array<string, string>, listing: bool, probes: array<int, string>}>
 */
function malformedQueryPages(): array
{
    return [
        'catalog'  => [ 'route' => 'artisanpack.ecommerce.storefront.catalog', 'parameters' => [], 'listing' => true, 'probes' => [] ],
        'category' => [ 'route' => 'artisanpack.ecommerce.storefront.category', 'parameters' => [ 'path' => 'clothing' ], 'listing' => true, 'probes' => [] ],
        'tag'      => [ 'route' => 'artisanpack.ecommerce.storefront.tag', 'parameters' => [ 'tag' => 'summer' ], 'listing' => true, 'probes' => [] ],
        'search'   => [
            'route'      => 'artisanpack.ecommerce.storefront.search',
            'parameters' => [],
            'listing'    => true,
            'probes'     => [ 'q[]=x', 'q[a][b]=x', 'q=x&per_page=abc', 'q=shirt&sort[]=x', 'q=shirt&attr=red', 'q=shirt&rating=x' ],
        ],
    ];
}

/**
 * One named case per page and probe.
 *
 * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
 */
function malformedQueryCases(): array
{
    $cases = [];

    foreach ( malformedQueryPages() as $page => $definition ) {
        $probes = [ ...( $definition['listing'] ? malformedListingProbes() : [] ), ...$definition['probes'] ];

        foreach ( $probes as $probe ) {
            $cases[ $page . ' ?' . $probe ] = [ $definition['route'], $definition['parameters'], $probe ];
        }
    }

    return $cases;
}

it( 'answers a malformed query string with the page, not a server error', function ( string $route, array $parameters, string $query ): void {
    $this->get( route( $route, $parameters ) . '?' . $query )->assertOk();
} )->with( malformedQueryCases() );

it( 'treats a malformed checkout step in the query string as missing', function (): void {
    checkoutCart();

    Livewire::withQueryParams( [ 'step' => [ 'payment' ] ] )
        ->test( Checkout::class )
        ->assertOk()
        ->assertSet( 'step', 'contact' );
} );

it( 'treats a malformed order status filter in the query string as missing', function (): void {
    [ , $customer ] = shopper();
    placedOrder( [ 'order_number' => 'OPEN1' ], $customer );

    Livewire::withQueryParams( [ 'status' => [ 'completed' ] ] )
        ->test( Orders::class )
        ->assertOk()
        ->assertSet( 'status', '' )
        ->assertSee( 'OPEN1' )
        ->set( 'status', [ 'x' ] )
        ->assertSet( 'status', '' );
} );
