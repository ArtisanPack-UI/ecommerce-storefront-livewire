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

it( 'builds the featured products pattern from a heading row with "Shop all" over the featured grid', function (): void {
    $content = StorefrontTemplates::patterns()['artisanpack-commerce/featured-products']['content'];

    preg_match( '#<!-- wp:paragraph \{"className":"ec-pattern__link"\} -->\n<p class="ec-pattern__link">(.+)</p>\n<!-- /wp:paragraph -->#', $content, $link );

    expect( $content )->toStartWith( '<!-- wp:group {"className":"ec-pattern"} -->' )
        ->toContain( '<!-- wp:group {"className":"ec-pattern__header","layout":{"type":"flex","justifyContent":"space-between","verticalAlignment":"center"}} -->' )
        ->toContain( '<!-- wp:heading {"level":2,"className":"ec-pattern__title"} -->' . "\n" . '<h2 class="wp-block-heading ec-pattern__title">Featured products</h2>' )
        ->toContain( '<!-- wp:artisanpack-commerce/product-grid {"source":"featured","limit":4,"columns":4} /-->' )
        ->and( $link )->not->toBeEmpty()
        ->and( $link[1] )->toContain( 'href="' . route( 'artisanpack.ecommerce.storefront.catalog', [], false ) . '"' )
        ->not->toContain( 'href="http' )
        ->toContain( 'class="apui-link"' )
        ->toContain( 'data-standalone' )
        ->toContain( 'Shop all' )
        ->toContain( 'data-part="iconEnd"' )
        ->toContain( 'data-mirror' )
        ->not->toContain( 'wire:navigate' )
        ->not->toContain( "\n" )
        // The heading row comes before the grid.
        ->and( strpos( $content, 'ec-pattern__header' ) )->toBeLessThan( strpos( $content, 'wp:artisanpack-commerce/product-grid' ) )
        ->and( classTokens( $content ) )->not->toContain( 'flex', 'flex-col', 'gap-6', 'text-2xl', 'font-bold', 'rounded-box', 'bg-base-200', 'link', 'link-primary' );
} );

it( 'leaves "Shop all" out of the featured products pattern without the storefront routes', function (): void {
    app( 'router' )->setRoutes( new Illuminate\Routing\RouteCollection() );

    $content = StorefrontTemplates::patterns()['artisanpack-commerce/featured-products']['content'];

    expect( $content )->toContain( 'ec-pattern__header' )
        ->toContain( '>Featured products</h2>' )
        ->not->toContain( 'ec-pattern__link' )
        ->not->toContain( 'Shop all' );
} );

it( 'builds the shop by category pattern from a heading over three columns of top-level categories', function (): void {
    $content = StorefrontTemplates::patterns()['artisanpack-commerce/shop-by-category']['content'];

    expect( $content )->toStartWith( '<!-- wp:group {"className":"ec-pattern"} -->' )
        ->toContain( '<h2 class="wp-block-heading ec-pattern__title">Shop by category</h2>' )
        ->toContain( '<!-- wp:artisanpack-commerce/category-grid {"limit":6,"columns":3} /-->' )
        ->not->toContain( '"parent"' )
        ->and( classTokens( $content ) )->not->toContain( 'flex', 'flex-col', 'gap-6', 'text-2xl', 'font-bold' );
} );

it( 'translates the pattern copy', function (): void {
    app()->setLocale( 'fr' );
    app( 'translator' )->addLines( [
        '*.Featured products' => 'Produits phares',
        '*.Shop all'          => 'Tout voir',
        '*.Shop by category'  => 'Acheter par catégorie',
    ], 'fr', '*' );

    $patterns = StorefrontTemplates::patterns();

    expect( $patterns['artisanpack-commerce/featured-products']['title'] )->toBe( 'Produits phares' )
        ->and( $patterns['artisanpack-commerce/featured-products']['content'] )->toContain( '>Produits phares</h2>' )->toContain( 'Tout voir' )
        ->and( $patterns['artisanpack-commerce/shop-by-category']['content'] )->toContain( '>Acheter par catégorie</h2>' );
} );

it( 'styles the pattern title at the heading-xl type with tokens only', function (): void {
    $css = file_get_contents( dirname( __DIR__, 3 ) . '/resources/css/storefront.css' );

    preg_match( '/\.ec-pattern__title,\s*\.ec-template__title \{([^}]+)\}/', $css, $rule );

    expect( $rule )->not->toBeEmpty()
        ->and( $rule[1] )->toContain( 'var(--apui-type-heading-xl-font-size)' )
        ->toContain( 'var(--apui-type-heading-xl-font-weight)' )
        ->toContain( 'var(--apui-text-default)' )
        ->not->toMatch( '/#[0-9a-f]{3,8}\b|oklch\(|--color-base|--p\b/i' );
} );

it( 'builds the sale banner pattern on the accent fill, with its copy beside an image, over four products on sale', function (): void {
    $content = StorefrontTemplates::patterns()['artisanpack-commerce/sale-banner-grid']['content'];

    preg_match( '#<!-- wp:paragraph \{"className":"ec-sale-banner__badge"\} -->\n<p class="ec-sale-banner__badge">(.+)</p>#', $content, $badge );
    preg_match( '#<!-- wp:paragraph \{"className":"ec-sale-banner__action"\} -->\n<p class="ec-sale-banner__action">(.+)</p>#', $content, $action );

    expect( $content )->toStartWith( '<!-- wp:group {"className":"ec-pattern"} -->' )
        ->toContain( '<!-- wp:group {"className":"ec-sale-banner","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"stretch"},"style":{"spacing":{"blockGap":"0"}}} -->' )
        ->toContain( '<!-- wp:group {"className":"ec-sale-banner__copy"} -->' )
        ->toContain( '<h2 class="wp-block-heading ec-sale-banner__title">On sale now</h2>' )
        ->toContain( '<p class="ec-sale-banner__text">Save on these favourites while they last.</p>' )
        ->toContain( '<!-- wp:image {"width":"100%","aspectRatio":"4/3","scale":"cover","className":"ec-sale-banner__media","style":{"spacing":{"margin":{"bottom":"0"}}}} -->' )
        ->toContain( '<figure class="wp-block-image ec-sale-banner__media" style="margin-bottom:0"><img alt=""/></figure>' )
        ->toContain( '<!-- wp:artisanpack-commerce/product-grid {"source":"on_sale","limit":4,"columns":4} /-->' )
        ->and( $badge[1] )->toMatch( '/class="apui-badge"[^>]*data-variant="solid"[^>]*data-color="accent"/' )->toContain( 'Sale' )
        ->and( $action[1] )->toContain( 'href="' . route( 'artisanpack.ecommerce.storefront.catalog', [ 'on_sale' => 1 ], false ) . '"' )
        ->toMatch( '/data-variant="solid"[^>]*data-color="neutral"[^>]*data-size="lg"/' )
        ->toContain( 'Shop the sale' )
        ->not->toContain( 'href="http' )
        ->and( strpos( $content, 'ec-sale-banner__copy' ) )->toBeLessThan( strpos( $content, 'ec-sale-banner__media' ) )
        ->and( classTokens( $content ) )->not->toContain( 'rounded-box', 'bg-base-200', 'p-8', 'text-center', 'text-2xl', 'font-bold', 'flex', 'flex-col', 'gap-6' );
} );

it( 'leaves "Shop the sale" out of the sale banner without the storefront routes', function (): void {
    app( 'router' )->setRoutes( new Illuminate\Routing\RouteCollection() );

    expect( StorefrontTemplates::patterns()['artisanpack-commerce/sale-banner-grid']['content'] )
        ->toContain( 'On sale now' )
        ->not->toContain( 'ec-sale-banner__action' );
} );

it( 'styles the sale banner with tokens only, hiding its image until it has one', function (): void {
    $css = file_get_contents( dirname( __DIR__, 3 ) . '/resources/css/storefront.css' );

    preg_match( '/\.ec-sale-banner \{([^}]+)\}/', $css, $banner );

    expect( $banner[1] )->toContain( 'var(--apui-color-accent-soft)' )->toContain( 'var(--apui-radius-surface)' )
        ->and( $css )->toContain( '.ec-sale-banner__media:not(:has(img[src]))' );
} );

it( 'builds each default template as a group of its blocks under a design-system page heading', function ( string $slug, ?string $heading, string $block ): void {
    $content = StorefrontTemplates::templates()[ $slug ]['content'];

    expect( $content )->toStartWith( '<!-- wp:group {"className":"ec-template"} -->' )
        ->toContain( '<!-- wp:artisanpack-commerce/' . $block . ' /-->' )
        ->and( classTokens( $content ) )->not->toContain( 'flex', 'flex-col', 'gap-6', 'text-3xl', 'font-bold' );

    if ( null !== $heading ) {
        expect( $content )->toContain( '<!-- wp:heading {"level":1,"className":"ec-template__title"} -->' . "\n" . '<h1 class="wp-block-heading ec-template__title">' . $heading . '</h1>' );
    } else {
        expect( $content )->not->toContain( 'wp:heading' );
    }
} )->with( [
    'archive'  => [ 'product-archive', 'Shop', 'product-catalog' ],
    'category' => [ 'product-category', null, 'product-catalog' ],
    'tag'      => [ 'product-tag', null, 'product-catalog' ],
    'cart'     => [ 'cart', 'Cart', 'cart-contents' ],
    'checkout' => [ 'checkout', 'Checkout', 'checkout-steps' ],
    'search'   => [ 'search-results', 'Search', 'product-catalog' ],
] );

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
