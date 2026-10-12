<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\ProductCatalogBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlocks;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontTemplates;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontContext;
use Illuminate\Support\Facades\Blade;
use Tests\Fixtures\VisualEditor\TemplateComponent;

/**
 * Simulates visual-editor (with `<x-ve-template>`) and cms-framework's
 * template resolver, with `$saved` templates saved, and turns templates on.
 *
 * @param  array<int, string>  $saved  Saved template slugs.
 */
function installTemplates( array $saved = [], bool $on = true ): void
{
    require_once dirname( __DIR__, 2 ) . '/Fixtures/VisualEditor/VisualEditorStub.php';
    require_once dirname( __DIR__, 2 ) . '/Fixtures/VisualEditor/TemplateResolverStub.php';

    app()->instance( StorefrontBlocks::EDITOR, new ArtisanPackUI\VisualEditor\VisualEditor() );
    Blade::component( StorefrontTemplates::COMPONENT, TemplateComponent::class );
    config( [ 'artisanpack.ecommerce-storefront-livewire.visual_editor.templates' => $on ] );

    TemplateResolver::$saved = $saved;
    TemplateResolver::$fails = false;
}

afterEach( function (): void {
    if ( class_exists( TemplateResolver::class, false ) ) {
        TemplateResolver::$saved = [];
        TemplateResolver::$fails = false;
    }

    foreach ( [ 'ap.visualEditor.templates', 'ap.visualEditor.patterns', 'ap.visualEditor.resources', 'ap.ecommerceStorefrontLivewire.templates', 'ap.ecommerceStorefrontLivewire.patterns' ] as $hook ) {
        removeAllFilters( $hook );
    }
} );

it( 'offers the default templates to the site editor when templates are on', function (): void {
    installTemplates();

    StorefrontBlocks::boot( app() );

    $templates = applyFilters( 'ap.visualEditor.templates', [] );

    expect( array_keys( $templates ) )->toBe( StorefrontTemplates::TEMPLATES )
        ->and( $templates['single-product'] )->toMatchArray( [
            'slug'           => 'single-product',
            'theme'          => StorefrontTemplates::FALLBACK_THEME,
            'title'          => 'Single Product',
            'source'         => 'theme',
            'has_theme_file' => true,
            'is_custom'      => false,
            'raw_content'    => '<!-- wp:artisanpack-commerce/single-product /-->',
        ] )
        ->and( $templates['product-category']['raw_content'] )->toContain( '<!-- wp:artisanpack-commerce/product-catalog /-->' )
        ->and( $templates['cart']['raw_content'] )->toContain( '<!-- wp:artisanpack-commerce/cart-contents /-->' )->toContain( '>Cart</h1>' )
        ->and( $templates['checkout']['raw_content'] )->toContain( '<!-- wp:artisanpack-commerce/checkout-steps /-->' );
} );

it( 'offers no templates when templates are off, and keeps a template the host provides', function (): void {
    installTemplates( [], false );
    StorefrontBlocks::boot( app() );

    expect( applyFilters( 'ap.visualEditor.templates', [] ) )->toBe( [] );

    removeAllFilters( 'ap.visualEditor.patterns' );
    config( [ 'artisanpack.ecommerce-storefront-livewire.visual_editor.templates' => true ] );
    StorefrontBlocks::boot( app() );

    $templates = applyFilters( 'ap.visualEditor.templates', [ 'cart' => [ 'slug' => 'cart', 'title' => 'Our cart' ] ] );

    expect( $templates['cart']['title'] )->toBe( 'Our cart' );
} );

it( 'offers the commerce patterns whenever the blocks are registered', function (): void {
    installTemplates( [], false );

    StorefrontBlocks::boot( app() );

    $patterns = applyFilters( 'ap.visualEditor.patterns', [] );

    expect( array_keys( $patterns ) )->toBe( [
        'artisanpack-commerce/featured-products',
        'artisanpack-commerce/shop-by-category',
        'artisanpack-commerce/sale-banner-grid',
    ] )
        ->and( $patterns['artisanpack-commerce/featured-products'] )->toMatchArray( [ 'title' => 'Featured products', 'source' => 'theme', 'synced' => false, 'categories' => [ 'commerce' ] ] )
        ->and( $patterns['artisanpack-commerce/featured-products']['raw_content'] )->toContain( '<!-- wp:artisanpack-commerce/product-grid {"source":"featured","limit":4,"columns":4} /-->' )
        ->and( $patterns['artisanpack-commerce/shop-by-category']['raw_content'] )->toContain( 'wp:artisanpack-commerce/category-grid' )
        ->and( $patterns['artisanpack-commerce/sale-banner-grid']['raw_content'] )->toContain( 'On sale now' )->toContain( '"source":"on_sale"' );
} );

it( 'lets hosts change the templates and patterns, dropping incomplete ones', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.templates', static fn ( array $templates ): array => [
        'single-product' => [ 'title' => 'Product', 'content' => '<!-- wp:artisanpack-commerce/product-price /-->' ],
        'broken'         => [ 'title' => '', 'content' => 'x' ],
        'Bad Slug!'      => [ 'title' => 'Bad', 'content' => 'x' ],
    ] );
    addFilter( 'ap.ecommerceStorefrontLivewire.patterns', static fn (): string => 'nope' );

    expect( StorefrontTemplates::templates() )->toBe( [ 'single-product' => [ 'title' => 'Product', 'description' => '', 'content' => '<!-- wp:artisanpack-commerce/product-price /-->' ] ] )
        ->and( StorefrontTemplates::patterns() )->toBe( [] );
} );

it( 'escapes the text it puts into template markup', function (): void {
    app()->setLocale( 'en' );
    app( 'translator' )->addLines( [ '*.Shop' => '<script>alert(1)</script>' ], 'en' );

    expect( StorefrontTemplates::templates()['product-archive']['content'] )
        ->not->toContain( '<script>' )
        ->toContain( '&lt;script&gt;' );
} );

it( 'builds each page\'s template chain, most specific first', function (): void {
    $product  = makeProduct( 1000, [ 'slug' => 'linen-shirt', 'type' => 'simple' ] );
    $category = ProductCategory::factory()->create( [ 'slug' => 'gifts' ] );
    $tag      = ProductTag::factory()->create( [ 'slug' => 'sale' ] );

    expect( StorefrontTemplates::chain( 'product', $product ) )->toBe( [ 'single-product-linen-shirt', 'single-product-simple', 'single-product' ] )
        ->and( StorefrontTemplates::chain( 'category', $category ) )->toBe( [ 'product-category-gifts', 'product-category', 'product-archive' ] )
        ->and( StorefrontTemplates::chain( 'tag', $tag ) )->toBe( [ 'product-tag-sale', 'product-tag', 'product-archive' ] )
        ->and( StorefrontTemplates::chain( 'catalog' ) )->toBe( [ 'product-archive' ] )
        ->and( StorefrontTemplates::chain( 'cart' ) )->toBe( [ 'cart' ] )
        ->and( StorefrontTemplates::chain( 'search' ) )->toBe( [ 'search-results' ] )
        ->and( StorefrontTemplates::chain( 'unknown' ) )->toBe( [] );
} );

it( 'picks the most specific saved template, or none', function (): void {
    $product = makeProduct( 1000, [ 'slug' => 'linen-shirt' ] );

    installTemplates( [ 'single-product', 'single-product-simple' ] );
    expect( StorefrontTemplates::for( 'product', $product ) )->toBe( 'single-product-simple' );

    installTemplates( [ 'single-product-linen-shirt', 'single-product' ] );
    expect( StorefrontTemplates::for( 'product', $product ) )->toBe( 'single-product-linen-shirt' );

    installTemplates( [] );
    expect( StorefrontTemplates::for( 'product', $product ) )->toBeNull();

    installTemplates( [ 'single-product' ], false );
    expect( StorefrontTemplates::for( 'product', $product ) )->toBeNull();
} );

it( 'renders the page as usual when the template lookup fails', function (): void {
    makeProduct( 1000, [ 'name' => 'Linen shirt', 'slug' => 'linen-shirt' ] );
    installTemplates( [ 'single-product' ] );
    TemplateResolver::$fails = true;

    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ] ) )
        ->assertOk()
        ->assertDontSee( 'data-ve-template', false )
        ->assertSee( 'Linen shirt' );
} );

it( 'renders a page through its saved template inside the layout', function (): void {
    makeProduct( 1000, [ 'name' => 'Linen shirt', 'slug' => 'linen-shirt' ] );
    installTemplates( [ 'single-product' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ] ) )
        ->assertOk()
        ->assertSee( 'data-ve-template="single-product"', false )
        ->assertSee( '<title>Linen shirt', false )
        ->assertSee( 'data-ecommerce-storefront-global', false );
} );

it( 'gives a category its own template', function (): void {
    ProductCategory::factory()->create( [ 'name' => 'Gifts', 'slug' => 'gifts' ] );
    ProductCategory::factory()->create( [ 'name' => 'Lamps', 'slug' => 'lamps' ] );
    CategoryTree::flush();
    installTemplates( [ 'product-category-gifts', 'product-archive' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'gifts' ] ) )->assertSee( 'data-ve-template="product-category-gifts"', false );
    $this->get( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'lamps' ] ) )->assertSee( 'data-ve-template="product-archive"', false );
} );

it( 'renders every templated page as usual until a template is saved', function ( string $route, array $parameters, string $marker ): void {
    makeProduct( 1000, [ 'name' => 'Linen shirt', 'slug' => 'linen-shirt' ] );
    ProductTag::factory()->create( [ 'name' => 'Sale', 'slug' => 'sale' ] );
    installTemplates();

    $this->get( route( $route, $parameters ) )
        ->assertOk()
        ->assertDontSee( 'data-ve-template', false )
        ->assertSee( $marker, false );
} )->with( [
    'catalog'  => [ 'artisanpack.ecommerce.storefront.catalog', [], 'Linen shirt' ],
    'product'  => [ 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ], 'Linen shirt' ],
    'tag'      => [ 'artisanpack.ecommerce.storefront.tag', [ 'tag' => 'sale' ], 'Sale' ],
    'search'   => [ 'artisanpack.ecommerce.storefront.search', [ 'q' => 'linen' ], 'Search' ],
    'cart'     => [ 'artisanpack.ecommerce.storefront.cart', [], 'data-ecommerce-cart' ],
] );

it( 'renders the cart, checkout, and search pages through saved templates', function ( string $route, string $slug ): void {
    installTemplates( [ $slug ] );

    $this->get( route( $route ) )->assertSee( 'data-ve-template="' . $slug . '"', false );
} )->with( [
    'cart'     => [ 'artisanpack.ecommerce.storefront.cart', 'cart' ],
    'checkout' => [ 'artisanpack.ecommerce.storefront.checkout', 'checkout' ],
    'search'   => [ 'artisanpack.ecommerce.storefront.search', 'search-results' ],
    'catalog'  => [ 'artisanpack.ecommerce.storefront.catalog', 'product-archive' ],
] );

it( 'lists the page\'s products in the Product Catalog block', function (): void {
    $kettle  = makeProduct( 1000, [ 'name' => 'Kettle' ] );
    $mug     = makeProduct( 1000, [ 'name' => 'Mug' ] );
    $kitchen = ProductCategory::factory()->create( [ 'name' => 'Kitchen', 'slug' => 'kitchen' ] );
    $gift    = ProductTag::factory()->create( [ 'name' => 'Gift', 'slug' => 'gift' ] );
    $kettle->categories()->attach( $kitchen->id );
    $mug->tags()->attach( $gift->id );
    CategoryTree::flush();

    $block   = app( ProductCatalogBlock::class );
    $context = app( StorefrontContext::class );

    expect( $block->render( [] ) )->toContain( 'Kettle' )->toContain( 'Mug' );

    $context->setPage( 'category' )->setCategory( $kitchen );
    expect( $block->render( [] ) )->toContain( 'Kettle' )->not->toContain( '>Mug<' )->toContain( '<h1' );

    $context->setPage( 'tag' )->setCategory( null )->setTag( $gift );
    expect( $block->render( [] ) )->toContain( 'Mug' )->not->toContain( '>Kettle<' );

    $context->setPage( 'search' )->setTag( null );
    expect( $block->render( [] ) )->toContain( 'data-search-page' );
} );
