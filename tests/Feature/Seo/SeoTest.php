<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontSeo;
use Illuminate\Testing\TestResponse;

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.seo.meta' );
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.seo.schema' );
} );

/**
 * The JSON-LD entries a page printed.
 *
 * @return array<int, array<string, mixed>>
 */
function jsonLd( TestResponse $response ): array
{
    preg_match_all( '#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getContent(), $matches );

    return array_map( static fn ( string $json ): array => json_decode( $json, true, flags: JSON_THROW_ON_ERROR ), $matches[1] );
}

/**
 * The JSON-LD entry of `$type`.
 *
 * @return array<string, mixed>|null
 */
function jsonLdOf( TestResponse $response, string $type ): ?array
{
    return collect( jsonLd( $response ) )->firstWhere( '@type', $type );
}

it( 'describes a product page: title, description, canonical, and social tags', function (): void {
    makeProduct( 1900, [ 'name' => 'Linen shirt', 'slug' => 'linen-shirt', 'short_description' => '<p>A <strong>breezy</strong>   shirt.</p>' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt', 'variant' => 4, 'utm_source' => 'x' ] ) )
        ->assertOk()
        ->assertSee( '<title>Linen shirt', false )
        ->assertSee( '<meta name="description" content="A breezy shirt.">', false )
        ->assertSee( '<link rel="canonical" href="' . route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ] ) . '">', false )
        ->assertSee( '<meta property="og:type" content="product">', false )
        ->assertSee( '<meta property="og:title" content="Linen shirt">', false )
        ->assertDontSee( '<meta name="robots"', false );
} );

it( 'adds Product, Offer, and AggregateRating structured data', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Linen shirt', 'slug' => 'linen-shirt', 'sku' => 'LS-1', 'avg_rating' => 4.5, 'reviews_count' => 12 ] );
    setStock( $product, 3 );

    $schema = jsonLdOf( $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ] ) ), 'Product' );

    expect( $schema )->toMatchArray( [
        '@context' => 'https://schema.org',
        'name'     => 'Linen shirt',
        'sku'      => 'LS-1',
        'url'      => route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ] ),
    ] )
        ->and( $schema['offers'] )->toBe( [
            '@type'         => 'Offer',
            'price'         => '19.00',
            'priceCurrency' => 'USD',
            'availability'  => 'https://schema.org/InStock',
            'url'           => route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ] ),
        ] )
        ->and( $schema['aggregateRating'] )->toBe( [ '@type' => 'AggregateRating', 'ratingValue' => 4.5, 'reviewCount' => 12, 'bestRating' => 5, 'worstRating' => 1 ] );
} );

it( 'marks a product out of stock and leaves out a rating it doesn\'t have', function (): void {
    $product = makeProduct( 1900, [ 'slug' => 'mug' ] );
    setStock( $product, 0 );

    $schema = jsonLdOf( $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'mug' ] ) ), 'Product' );

    expect( $schema['offers']['availability'] )->toBe( 'https://schema.org/OutOfStock' )
        ->and( $schema )->not->toHaveKey( 'aggregateRating' );
} );

it( 'gives a product with a price range an AggregateOffer', function (): void {
    $product = Product::factory()->variable()->create( [ 'slug' => 'tee' ] );

    foreach ( [ 1500, 2500 ] as $position => $amount ) {
        $variant = ProductVariant::factory()->create( [ 'product_id' => $product->id, 'position' => $position ] );
        ProductPrice::factory()->forPriceable( $variant )->create( [ 'currency' => 'USD', 'price_amount' => $amount, 'compare_at_amount' => null ] );
    }

    $offer = jsonLdOf( $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'tee' ] ) ), 'Product' )['offers'];

    expect( $offer )->toMatchArray( [ '@type' => 'AggregateOffer', 'lowPrice' => '15.00', 'highPrice' => '25.00', 'priceCurrency' => 'USD' ] );
} );

it( 'reports a pricing failure and still describes the product', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Mug', 'slug' => 'mug' ] );
    $this->mock( PriceDisplayResolver::class, static fn ( $mock ) => $mock->shouldReceive( 'for' )->andThrow( new RuntimeException( 'Pricing is down.' ) ) );

    $seo = app( StorefrontSeo::class )->product( $product, 'USD' );

    expect( collect( $seo->schemas() )->firstWhere( '@type', 'Product' ) )->toHaveKey( 'name', 'Mug' )->not->toHaveKey( 'offers' );
} );

it( 'adds breadcrumbs from the shop through the product\'s category', function (): void {
    $home    = ProductCategory::factory()->create( [ 'name' => 'Home', 'slug' => 'home' ] );
    $kitchen = ProductCategory::factory()->create( [ 'name' => 'Kitchen', 'slug' => 'kitchen', 'parent_id' => $home->id ] );
    $product = makeProduct( 1900, [ 'name' => 'Kettle', 'slug' => 'kettle' ] );
    $product->categories()->attach( $kitchen->id );
    CategoryTree::flush();

    $crumbs = jsonLdOf( $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'kettle' ] ) ), 'BreadcrumbList' )['itemListElement'];

    expect( array_column( $crumbs, 'name' ) )->toBe( [ 'Shop', 'Home', 'Kitchen', 'Kettle' ] )
        ->and( array_column( $crumbs, 'position' ) )->toBe( [ 1, 2, 3, 4 ] )
        ->and( $crumbs[2]['item'] )->toBe( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'home/kitchen' ] ) );
} );

it( 'keeps a closing script tag in a product name from breaking out of the JSON-LD', function (): void {
    makeProduct( 1900, [ 'name' => 'Mug </script><script>alert(1)</script>', 'slug' => 'mug' ] );

    $response = $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'mug' ] ) );

    expect( (string) $response->getContent() )->not->toContain( '<script>alert(1)' )
        ->and( jsonLdOf( $response, 'Product' )['name'] )->toBe( 'Mug </script><script>alert(1)</script>' );
} );

it( 'describes a category page with its own description and page number', function (): void {
    ProductCategory::factory()->create( [ 'name' => 'Kitchen', 'slug' => 'kitchen', 'description' => 'Pots, pans, and kettles.' ] );
    CategoryTree::flush();

    $url = route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'kitchen' ] );

    $this->get( $url . '?sort=price&in_stock=1&page=2' )
        ->assertSee( '<title>Kitchen – Page 2', false )
        ->assertSee( '<meta name="description" content="Pots, pans, and kettles.">', false )
        ->assertSee( '<link rel="canonical" href="' . $url . '?page=2">', false );

    $this->get( $url . '?page=1&sort=name' )->assertSee( '<link rel="canonical" href="' . $url . '">', false );
} );

it( 'keeps a category filter and the page in the catalog\'s canonical URL, and drops the rest', function (): void {
    $url = route( 'artisanpack.ecommerce.storefront.catalog' );

    $this->get( $url . '?sort=-price&price_min=100&category=kitchen&attr[colour][]=red&page=3' )
        ->assertSee( '<link rel="canonical" href="' . $url . '?category=kitchen&amp;page=3">', false )
        ->assertSee( '<title>Shop – Page 3', false );

    $this->get( $url . '?per_page=48&on_sale=1' )->assertSee( '<link rel="canonical" href="' . $url . '">', false );
} );

it( 'describes a tag page', function (): void {
    ProductTag::factory()->create( [ 'name' => 'Gift', 'slug' => 'gift' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.tag', [ 'tag' => 'gift' ] ) . '?sort=name' )
        ->assertSee( '<title>Gift', false )
        ->assertSee( '<meta name="description" content="Shop products tagged Gift.">', false )
        ->assertSee( '<link rel="canonical" href="' . route( 'artisanpack.ecommerce.storefront.tag', [ 'tag' => 'gift' ] ) . '">', false );
} );

it( 'keeps private pages out of search engines', function ( string $route, array $parameters, string $robots, bool $auth ): void {
    if ( $auth ) {
        shopper();
    }

    if ( 'artisanpack.ecommerce.storefront.checkout' === $route ) {
        checkoutCart();
    }

    $this->get( route( $route, $parameters ) )
        ->assertSee( '<meta name="robots" content="' . $robots . '">', false )
        ->assertHeader( 'X-Robots-Tag', $robots )
        ->assertDontSee( 'og:title', false );
} )->with( [
    'cart'      => [ 'artisanpack.ecommerce.storefront.cart', [], StorefrontSeo::NOINDEX, false ],
    'checkout'  => [ 'artisanpack.ecommerce.storefront.checkout', [], StorefrontSeo::NOINDEX, false ],
    'lookup'    => [ 'artisanpack.ecommerce.storefront.lookup', [], StorefrontSeo::NOINDEX, false ],
    'search'    => [ 'artisanpack.ecommerce.storefront.search', [ 'q' => 'mug' ], StorefrontSeo::NOINDEX_FOLLOW, false ],
    'account'   => [ 'artisanpack.ecommerce.account.dashboard', [], StorefrontSeo::NOINDEX, true ],
    'orders'    => [ 'artisanpack.ecommerce.account.orders.index', [], StorefrontSeo::NOINDEX, true ],
    'addresses' => [ 'artisanpack.ecommerce.account.addresses', [], StorefrontSeo::NOINDEX, true ],
] );

it( 'keeps an order confirmation out of search engines', function (): void {
    [, $customer ] = shopper();
    $order         = placedOrder( [], $customer );

    $this->get( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) )
        ->assertSee( '<meta name="robots" content="noindex, nofollow">', false );
} );

it( 'lets a filter change any page\'s tags', function (): void {
    makeProduct( 1900, [ 'name' => 'Linen shirt', 'slug' => 'linen-shirt' ] );

    addFilter( 'ap.ecommerceStorefrontLivewire.seo.meta', static function ( array $meta, ?string $page, $subject ): array {
        if ( 'product' === $page ) {
            $meta['title']       = 'Buy ' . $subject->name;
            $meta['description'] = str_repeat( 'Long ', 60 );
            $meta['image']       = 'javascript:alert(1)';
        }

        return $meta;
    }, 10, 3 );

    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ] ) )
        ->assertSee( '<title>Buy Linen shirt', false )
        ->assertSee( 'content="' . trim( str_repeat( 'Long ', 32 ) ) . '...', false )
        ->assertDontSee( 'javascript:alert', false );
} );

it( 'lets a filter change the structured data', function (): void {
    makeProduct( 1900, [ 'slug' => 'mug' ] );

    addFilter( 'ap.ecommerceStorefrontLivewire.seo.schema', static function ( array $schemas, ?string $page ): array {
        $schemas   = array_values( array_filter( $schemas, static fn ( array $schema ): bool => 'BreadcrumbList' !== $schema['@type'] ) );
        $schemas[] = [ '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Acme ' . $page ];
        $schemas[] = 'not an entry';

        return $schemas;
    }, 10, 2 );

    $types = array_column( jsonLd( $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'mug' ] ) ) ), '@type' );

    expect( $types )->toBe( [ 'Product', 'Organization' ] );
} );

it( 'hands the structured data to the SEO package when it is installed', function (): void {
    require_once dirname( __DIR__, 2 ) . '/Fixtures/Seo/SchemaCollectorStub.php';

    $collector = new ArtisanPackUI\SEO\Support\SchemaCollector();
    app()->instance( StorefrontSeo::SCHEMA_COLLECTOR, $collector );

    makeProduct( 1900, [ 'name' => 'Linen shirt', 'slug' => 'linen-shirt' ] );

    $seo = app( StorefrontSeo::class )->product( Product::query()->first(), 'USD' );

    expect( StorefrontSeo::seoPackageInstalled() )->toBeTrue()
        ->and( $seo->inlineSchemas() )->toBe( [] )
        ->and( $seo->inlineSchemas() )->toBe( [] )
        ->and( array_column( $collector->entries, '@type' ) )->toBe( [ 'Product', 'BreadcrumbList' ] );
} );

it( 'renders the structured data itself without the SEO package', function (): void {
    expect( StorefrontSeo::seoPackageInstalled() )->toBeFalse();

    makeProduct( 1900, [ 'slug' => 'mug' ] );

    expect( jsonLd( $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'mug' ] ) ) ) )->toHaveCount( 2 );
} );

it( 'prints the tags in the layout\'s head', function (): void {
    makeProduct( 1900, [ 'slug' => 'mug' ] );

    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'mug' ] ) )->getContent();

    expect( strpos( $html, '<link rel="canonical"' ) )->toBeLessThan( strpos( $html, '</head>' ) )
        ->and( strpos( $html, 'application/ld+json' ) )->toBeLessThan( strpos( $html, '</head>' ) );
} );
