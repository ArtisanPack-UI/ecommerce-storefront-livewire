<?php

declare( strict_types=1 );

/*
| Every extension seam (spec §8.4, docs/hooks.md) with one test proving a
| callback changes what the storefront does. Deeper behaviour of each seam is
| covered next to the component that owns it.
*/

use ArtisanPackUI\Ecommerce\Catalog\CatalogQuery;
use ArtisanPackUI\Ecommerce\Exceptions\CartOperationException;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Registries\AccountMenuRegistry;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\ProductPriceBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlocks;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontTemplates;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Orders;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Index as CartPage;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\Index as Catalog;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index as Checkout;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\RecentlyViewed;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Show;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\PaymentDriverRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\AddressFormats;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontSeo;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\AgeCheckStep;
use Tests\Fixtures\ProductTypes\EngravedProductType;

/**
 * Every storefront hook name used in src/ and resources/.
 *
 * @return array<int, string>
 */
function storefrontHookNames(): array
{
    $root  = dirname( __DIR__, 3 );
    $names = [];

    $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src' ) );
    $views = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/resources' ) );

    foreach ( [ $files, $views ] as $iterator ) {
        foreach ( $iterator as $file ) {
            if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
                continue;
            }

            preg_match_all( "/(?:applyFilters|doAction)\\(\\s*'(ap\\.ecommerceStorefrontLivewire\\.[A-Za-z.]+)'/", (string) file_get_contents( $file->getPathname() ), $matches );

            array_push( $names, ...$matches[1] );
        }
    }

    $names = array_values( array_unique( $names ) );
    sort( $names );

    return $names;
}

afterEach( function (): void {
    foreach ( storefrontHookNames() as $hook ) {
        removeAllFilters( $hook );
        removeAllActions( $hook );
    }

    removeAllActions( 'ap.ecommerce.product.viewed' );
} );

it( 'documents every hook it fires', function (): void {
    $docs = (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/hooks.md' );

    expect( storefrontHookNames() )->not->toBeEmpty();

    foreach ( storefrontHookNames() as $hook ) {
        expect( $docs )->toContain( '`' . $hook . '`' );
    }
} );

it( 'catalog.filters: adds and removes filter groups', function (): void {
    makeProduct( 1000, [ 'name' => 'Wool scarf' ] );

    addFilter( 'ap.ecommerceStorefrontLivewire.catalog.filters', static function ( array $filters, array $context ): array {
        unset( $filters['rating'] );

        $filters['material'] = [
            'type'    => 'options',
            'label'   => 'Material',
            'options' => [ 'wool' => 'Wool' ],
            'apply'   => static fn ( CatalogQuery $query, array $values ): CatalogQuery => $query,
        ];

        return $filters;
    }, 10, 2 );

    Livewire::test( Catalog::class )
        ->assertSee( 'Material' )
        ->assertDontSeeHtml( 'data-filter-group="rating"' );
} );

it( 'catalog.sorts: relabels and removes sort options', function (): void {
    makeProduct( 1000 );
    addFilter( 'ap.ecommerceStorefrontLivewire.catalog.sorts', static fn ( array $sorts ): array => [ 'name' => 'A to Z' ] );

    Livewire::test( Catalog::class )->assertSee( 'A to Z' )->assertDontSee( 'Price: high to low' );
} );

it( 'catalog.productCard: adds a wishlist button and a badge to every card', function (): void {
    makeProduct( 1000, [ 'name' => 'Kettle' ] );

    addFilter( 'ap.ecommerceStorefrontLivewire.catalog.productCard', static function ( array $card, Product $product ): array {
        $card['actions']['wishlist'] = new HtmlString( '<button data-wishlist="' . $product->id . '">♥</button>' );
        $card['badges'][]            = 'Staff pick';

        return $card;
    }, 10, 2 );

    Livewire::test( Catalog::class )
        ->assertSeeHtml( 'data-product-card-actions' )
        ->assertSeeHtml( 'data-wishlist="' . Product::query()->value( 'id' ) . '"' )
        ->assertSee( 'Staff pick' );
} );

it( 'product.sections: adds and removes product page sections', function (): void {
    $product = makeProduct( 1000 );

    addFilter( 'ap.ecommerceStorefrontLivewire.product.sections', static function ( array $sections ): array {
        unset( $sections['reviews'] );

        $sections['recent'] = [ 'component' => 'artisanpack-ecommerce-storefront-recently-viewed', 'position' => 1 ];

        return $sections;
    } );

    Livewire::test( Show::class, [ 'product' => $product ] )
        ->assertSeeHtml( 'data-related-placeholder' )
        ->assertDontSeeHtml( 'id="reviews"' );
} );

it( 'product.shippingReturns: fills the shipping and returns tab', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.product.shippingReturns', static fn ( string $html, Product $product ): string => '<p>Free returns on ' . e( $product->name ) . '.</p>', 10, 2 );

    Livewire::test( Show::class, [ 'product' => makeProduct( 1000, [ 'name' => 'Kettle' ] ) ] )->assertSee( 'Free returns on Kettle.' );
} );

it( 'recentlyViewed.productIds: lists the satellite\'s products', function (): void {
    $kettle = makeProduct( 1000, [ 'name' => 'Kettle' ] );

    addFilter( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds', static fn ( array $ids, int $limit, ?Customer $customer, ?Product $exclude ): array => [ $kettle->id ], 10, 4 );

    Livewire::withoutLazyLoading()->test( RecentlyViewed::class )->assertSee( 'Kettle' );
} );

it( 'fires product.viewed once per product view, not on updates or editor previews', function (): void {
    $product = makeProduct( 1000 );
    $views   = [];

    addAction( 'ap.ecommerce.product.viewed', static function ( Product $viewed, ?Customer $customer ) use ( &$views ): void {
        $views[] = [ $viewed->id, $customer ];
    }, 10, 2 );

    Livewire::test( Show::class, [ 'product' => $product ] )
        ->set( 'openSection', 'specifications' )
        ->call( '$refresh' );

    Livewire::test( Show::class, [ 'product' => $product, 'recordView' => false ] );

    expect( $views )->toBe( [ [ $product->id, null ] ] );
} );

it( 'cart.sections: adds sections below the cart', function (): void {
    checkoutCart();

    addFilter( 'ap.ecommerceStorefrontLivewire.cart.sections', static fn ( array $sections, ?Cart $cart ): array => [
        'recent' => [ 'component' => 'artisanpack-ecommerce-storefront-recently-viewed', 'params' => [ 'heading' => 'You looked at' ] ],
    ], 10, 2 );

    Livewire::test( CartPage::class )
        ->assertSeeHtml( 'data-cart-section="recent"' )
        ->assertDontSeeHtml( 'data-cart-section="cross-sells"' );
} );

it( 'checkout.steps: adds a step before review', function (): void {
    Livewire::component( 'age-check-step', AgeCheckStep::class );
    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', static function ( array $steps, Cart $cart ): array {
        $steps['age-check'] = [ 'label' => 'Date of birth', 'component' => 'age-check-step' ];

        return $steps;
    }, 10, 2 );

    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.layout', 'single_page' );
    checkoutCart();

    Livewire::test( Checkout::class )->assertSeeHtml( 'data-checkout-step="age-check"' )->assertSee( 'Date of birth' );
} );

it( 'checkout.beforePlaceOrder: runs before the order and can stop it', function (): void {
    checkoutShipping();
    $gateway = checkoutGateway();
    checkoutCart();

    $seen = [];

    addAction( 'ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder', static function ( Cart $cart, array $context ) use ( &$seen ): void {
        $seen[] = $cart->id;

        throw new CartOperationException( 'We can\'t ship to that address this week.' );
    }, 10, 2 );

    $component = checkoutAtReview( $gateway );
    $component->call( 'placeOrder', placeOrderToken( $component ) );

    expect( $seen )->toHaveCount( 1 )
        ->and( Order::query()->count() )->toBe( 0 );
} );

it( 'order.statusLabel: renames a status for shoppers', function (): void {
    [, $customer ] = shopper();
    placedOrder( [ 'system_status' => 'processing' ], $customer );

    addFilter( 'ap.ecommerceStorefrontLivewire.order.statusLabel', static fn ( string $label, Order $order ): string => 'Being packed', 10, 2 );

    Livewire::test( Orders::class )->assertSee( 'Being packed' );
} );

it( 'address.regions, postcodeExamples, postcodePatterns: describe a new country', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.address.regions', static fn ( array $regions ): array => [ 'XQ' => [ 'NO' => 'North' ] ] + $regions );
    addFilter( 'ap.ecommerceStorefrontLivewire.address.postcodeExamples', static fn ( array $examples ): array => [ 'XQ' => 'XQ-123' ] + $examples );
    addFilter( 'ap.ecommerceStorefrontLivewire.address.postcodePatterns', static fn ( array $patterns ): array => [ 'XQ' => '/^XQ-\d{3}$/' ] + $patterns );

    expect( AddressFormats::regions( 'XQ' ) )->toBe( [ [ 'id' => 'NO', 'name' => 'North' ] ] )
        ->and( AddressFormats::postcodeHint( 'XQ' ) )->toBe( 'For example: XQ-123' )
        ->and( AddressFormats::postcodePattern( 'XQ' ) )->toBe( '/^XQ-\d{3}$/' );
} );

it( 'header.actions: adds a header action', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.header.actions', static fn ( array $actions ): array => $actions + [ 'wishlist' => new HtmlString( '<a data-header-wishlist href="/wishlist">Wishlist</a>' ) ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->assertSee( 'data-header-wishlist', false );
} );

it( 'layout.viteEntries: changes the assets the layout loads', function (): void {
    $hot = tempnam( sys_get_temp_dir(), 'vite-hot' );
    file_put_contents( $hot, 'http://localhost:5173' );
    Vite::useHotFile( $hot );

    addFilter( 'ap.ecommerceStorefrontLivewire.layout.viteEntries', static fn (): array => [ 'resources/css/shop.css' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->assertSee( 'http://localhost:5173/resources/css/shop.css', false );

    @unlink( $hot );
} );

it( 'seo.meta and seo.schema: change a page\'s tags and structured data', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.seo.meta', static fn ( array $meta, ?string $page ): array => [ 'description' => 'Handmade in ' . $page ] + $meta, 10, 2 );
    addFilter( 'ap.ecommerceStorefrontLivewire.seo.schema', static fn ( array $schemas ): array => [ [ '@context' => 'https://schema.org', '@type' => 'Store', 'name' => 'Acme' ] ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertSee( '<meta name="description" content="Handmade in catalog">', false )
        ->assertSee( '"@type":"Store"', false );

    expect( app( StorefrontSeo::class )->schemas() )->toBe( [ [ '@context' => 'https://schema.org', '@type' => 'Store', 'name' => 'Acme' ] ] );
} );

it( 'blocks: removes a core block', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.blocks', static fn ( array $blocks ): array => array_values( array_diff( $blocks, [ ProductPriceBlock::class ] ) ) );

    expect( array_map( static fn ( object $block ): string => $block::class, StorefrontBlocks::blocks( app() ) ) )->not->toContain( ProductPriceBlock::class );
} );

it( 'templates and patterns: replace the defaults', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.templates', static fn (): array => [ 'cart' => [ 'title' => 'Basket', 'content' => '<!-- wp:artisanpack-commerce/cart-contents /-->' ] ] );
    addFilter( 'ap.ecommerceStorefrontLivewire.patterns', static fn (): array => [ 'acme/hero' => [ 'title' => 'Hero', 'content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ] ] );

    expect( array_keys( StorefrontTemplates::templates() ) )->toBe( [ 'cart' ] )
        ->and( array_keys( StorefrontTemplates::patterns() ) )->toBe( [ 'acme/hero' ] );
} );

it( 'ProductFormRegistry: renders a satellite\'s purchase form', function (): void {
    Livewire::component( 'engraving-form', AgeCheckStep::class );
    app( ProductFormRegistry::class )->register( 'simple', 'engraving-form' );

    Livewire::test( Show::class, [ 'product' => makeProduct( 1000 ) ] )->assertSeeHtml( 'data-age-check' );
} );

it( 'ProvidesStorefrontOptions: renders a product type\'s own fields without a registered form', function (): void {
    app( ProductTypeRegistry::class )->register( EngravedProductType::KEY, new EngravedProductType() );
    $product = Product::factory()->create( [ 'type' => EngravedProductType::KEY ] );
    ProductPrice::factory()->forPriceable( $product )->create( [ 'currency' => 'USD', 'price_amount' => 9900, 'compare_at_amount' => null ] );

    Livewire::test( Show::class, [ 'product' => $product ] )->assertSee( 'Engraving' );
} );

it( 'PaymentDriverRegistry: renders a gateway\'s payment UI', function (): void {
    checkoutShipping();
    checkoutGateway( 'acme', 'Acme Pay' );
    checkoutCart();

    $component = Livewire::test( Checkout::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->set( 'shipping', checkoutAddress() )
        ->call( 'saveAddress' );

    $component->set( 'shippingRate', (string) $component->get( 'rates' )[0]['id'] )->call( 'saveShipping' );

    expect( app( PaymentDriverRegistry::class )->component( 'fake-driver' ) )->toBe( 'fake-payment-driver' );

    $component->assertSeeHtml( 'data-fake-driver' )->assertSee( 'Acme Pay' );
} );

it( 'AccountMenuRegistry: adds a page to the account navigation', function (): void {
    Route::get( '/account/wishlist', static fn (): string => 'wishlist' )->name( 'wishlist.index' );
    app( 'router' )->getRoutes()->refreshNameLookups();
    shopper();

    app( AccountMenuRegistry::class )->register( 'wishlist', [ 'label' => 'Wishlist', 'route' => 'wishlist.index', 'icon' => 'o-heart', 'position' => 40 ] );

    $this->get( route( 'artisanpack.ecommerce.account.dashboard' ) )->assertSee( 'Wishlist' )->assertSee( '/account/wishlist', false );
} );
