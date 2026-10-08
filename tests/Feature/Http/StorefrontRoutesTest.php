<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use Illuminate\Support\Facades\Route;

beforeEach( function (): void {
    // The host owns sign-in (D6); the `auth` middleware redirects here.
    Route::get( 'login', static fn (): string => 'login' )->middleware( 'web' )->name( 'login' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    $this->category = ProductCategory::factory()->create( [ 'name' => 'Clothing', 'slug' => 'clothing' ] );
    $this->child    = ProductCategory::factory()->create( [ 'name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $this->category->id ] );
    $this->tag      = ProductTag::factory()->create( [ 'name' => 'Summer', 'slug' => 'summer' ] );
    $this->product  = makeProduct( 2500, [ 'name' => 'Linen shirt', 'slug' => 'linen-shirt' ] );

    CategoryTree::flush();
} );

/**
 * Every storefront route with its parameters.
 *
 * @return array<string, array{0: string, 1: array<string, string>}>
 */
function storefrontRoutes(): array
{
    return [
        'catalog'         => [ 'artisanpack.ecommerce.storefront.catalog', [] ],
        'category'        => [ 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing' ] ],
        'child category'  => [ 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing/shirts' ] ],
        'tag'             => [ 'artisanpack.ecommerce.storefront.tag', [ 'tag' => 'summer' ] ],
        'product'         => [ 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ] ],
        'search'          => [ 'artisanpack.ecommerce.storefront.search', [] ],
        'cart'            => [ 'artisanpack.ecommerce.storefront.cart', [] ],
        'checkout'        => [ 'artisanpack.ecommerce.storefront.checkout', [] ],
        'checkout return' => [ 'artisanpack.ecommerce.storefront.checkout.return', [] ],
        'confirmation'    => [ 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => '1001' ] ],
        'lookup'          => [ 'artisanpack.ecommerce.storefront.lookup', [] ],
    ];
}

/**
 * Every account route with its parameters.
 *
 * @return array<string, array{0: string, 1: array<string, string>}>
 */
function accountRoutes(): array
{
    return [
        'dashboard' => [ 'artisanpack.ecommerce.account.dashboard', [] ],
        'orders'    => [ 'artisanpack.ecommerce.account.orders.index', [] ],
        'order'     => [ 'artisanpack.ecommerce.account.orders.show', [ 'order' => '1001' ] ],
        'addresses' => [ 'artisanpack.ecommerce.account.addresses', [] ],
        'downloads' => [ 'artisanpack.ecommerce.account.downloads', [] ],
        'profile'   => [ 'artisanpack.ecommerce.account.profile', [] ],
        'claim'     => [ 'artisanpack.ecommerce.account.claim', [] ],
    ];
}

it( 'registers the spec paths under the configured prefixes', function (): void {
    expect( route( 'artisanpack.ecommerce.storefront.catalog', absolute: false ) )->toBe( '/shop' )
        ->and( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing/shirts' ], false ) )->toBe( '/shop/category/clothing/shirts' )
        ->and( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ], false ) )->toBe( '/shop/products/linen-shirt' )
        ->and( route( 'artisanpack.ecommerce.storefront.checkout.return', absolute: false ) )->toBe( '/shop/checkout/return' )
        ->and( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => '1001' ], false ) )->toBe( '/shop/orders/1001/confirmation' )
        ->and( route( 'artisanpack.ecommerce.storefront.lookup', absolute: false ) )->toBe( '/shop/order-lookup' )
        ->and( route( 'artisanpack.ecommerce.account.dashboard', absolute: false ) )->toBe( '/account' )
        ->and( route( 'artisanpack.ecommerce.account.orders.show', [ 'order' => '1001' ], false ) )->toBe( '/account/orders/1001' );
} );

it( 'serves every storefront route to a guest', function ( string $name, array $parameters ): void {
    $this->get( route( $name, $parameters ) )
        ->assertOk()
        ->assertSee( 'data-ecommerce-storefront-global', false );
} )->with( storefrontRoutes() );

it( 'serves every storefront route to a signed-in customer', function ( string $name, array $parameters ): void {
    $this->actingAs( makeUser() )
        ->get( route( $name, $parameters ) )
        ->assertOk();
} )->with( storefrontRoutes() );

it( 'sends a guest to the sign-in route from every account route', function ( string $name, array $parameters ): void {
    $this->get( route( $name, $parameters ) )->assertRedirect( route( 'login' ) );
} )->with( accountRoutes() );

it( 'serves every account route to a signed-in customer', function ( string $name, array $parameters ): void {
    $this->actingAs( makeUser() )
        ->get( route( $name, $parameters ) )
        ->assertOk()
        ->assertSee( 'data-screen-pending', false );
} )->with( accountRoutes() );

it( 'embeds the catalog component on the catalog, category, and tag pages', function ( string $name, array $parameters ): void {
    $this->get( route( $name, $parameters ) )
        ->assertOk()
        ->assertSee( 'data-ecommerce-catalog', false );
} )->with( [
    'catalog'  => [ 'artisanpack.ecommerce.storefront.catalog', [] ],
    'category' => [ 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing' ] ],
    'tag'      => [ 'artisanpack.ecommerce.storefront.tag', [ 'tag' => 'summer' ] ],
] );

it( 'shows only the category\'s products (with its sub-categories) on a category page', function (): void {
    $this->product->categories()->attach( $this->child->id );
    makeProduct( 900, [ 'name' => 'Garden hose' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing' ] ) )
        ->assertOk()
        ->assertSee( 'Clothing' )
        ->assertSee( 'Linen shirt' )
        ->assertDontSee( 'Garden hose' );
} );

it( 'shows only the tagged products on a tag page', function (): void {
    $this->product->tags()->attach( $this->tag->id );
    makeProduct( 900, [ 'name' => 'Wool scarf' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.tag', [ 'tag' => 'summer' ] ) )
        ->assertOk()
        ->assertSee( 'Linen shirt' )
        ->assertDontSee( 'Wool scarf' );
} );

it( 'resolves a product by slug and titles the page with its name', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ] ) )
        ->assertOk()
        ->assertSee( '<title>Linen shirt &middot;', false );
} );

it( 'answers 404 for a product that is unknown, not visible, or of a missing type', function ( Closure $make ): void {
    $slug = $make();

    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $slug ] ) )->assertNotFound();
} )->with( [
    'unknown'      => fn (): string => 'no-such-product',
    'draft'        => fn (): string => Product::factory()->draft()->create()->slug,
    'scheduled'    => fn (): string => Product::factory()->create( [ 'published_at' => now()->addWeek() ] )->slug,
    'missing type' => fn (): string => Product::factory()->create( [ 'type' => 'uninstalled-satellite-type' ] )->slug,
] );

it( 'answers 404 for an unknown category or tag', function ( string $name, array $parameters ): void {
    $this->get( route( $name, $parameters ) )->assertNotFound();
} )->with( [
    'unknown category'      => [ 'artisanpack.ecommerce.storefront.category', [ 'path' => 'nope' ] ],
    'unknown leaf in chain' => [ 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing/nope' ] ],
    'unknown tag'           => [ 'artisanpack.ecommerce.storefront.tag', [ 'tag' => 'winter' ] ],
] );

it( 'redirects a wrong category chain to the canonical path of its last slug, keeping the query string', function ( string $path, string $canonical ): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => $path, 'sort' => 'name' ] ) )
        ->assertStatus( 301 )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => $canonical, 'sort' => 'name' ] ) );
} )->with( [
    'child without root' => [ 'shirts', 'clothing/shirts' ],
    'unknown parent'     => [ 'nope/shirts', 'clothing/shirts' ],
    'root under a child' => [ 'shirts/clothing', 'clothing' ],
] );

it( 'uses the configured prefixes and middleware', function (): void {
    expect( Route::getRoutes()->getByName( 'artisanpack.ecommerce.storefront.catalog' )->gatherMiddleware() )->toBe( [ 'web' ] )
        ->and( Route::getRoutes()->getByName( 'artisanpack.ecommerce.account.dashboard' )->gatherMiddleware() )->toBe( [ 'web', 'auth' ] );
} );

it( 'routes every page through a controller action so routes can be cached', function (): void {
    foreach ( Route::getRoutes()->getRoutes() as $route ) {
        if ( str_starts_with( (string) $route->getName(), 'artisanpack.ecommerce.storefront.' ) || str_starts_with( (string) $route->getName(), 'artisanpack.ecommerce.account.' ) ) {
            expect( $route->getActionName() )->not->toBe( 'Closure' );
        }
    }
} );
