<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Currency\Switcher;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontNavigation;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * The standalone layout on the design system (#129).
 */
beforeEach( function (): void {
    CategoryTree::flush();
    app()->forgetInstance( CategoryPaths::class );

    $this->ceramics = ProductCategory::factory()->create( [ 'name' => 'Ceramics', 'slug' => 'ceramics', 'position' => 0 ] );
    $this->mugs     = ProductCategory::factory()->create( [ 'name' => 'Mugs', 'slug' => 'mugs', 'parent_id' => $this->ceramics->id ] );
    $this->linen    = ProductCategory::factory()->create( [ 'name' => 'Linen', 'slug' => 'linen', 'position' => 1 ] );
    $this->candles  = ProductCategory::factory()->create( [ 'name' => 'Candles', 'slug' => 'candles', 'position' => 2 ] );
    $this->glass    = ProductCategory::factory()->create( [ 'name' => 'Glassware', 'slug' => 'glassware', 'position' => 3 ] );

    config( [ 'app.name' => 'Field & Kiln' ] );

    Route::get( 'login', static fn (): string => 'login' )->name( 'login' );
    app( 'router' )->getRoutes()->refreshNameLookups();
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.layout.navigation' );
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.layout.footerLinks' );
} );

/**
 * The store navigation links in the header, label => current.
 *
 * @return array<string, bool>
 */
function headerLinks( string $html ): array
{
    preg_match( '#<nav data-scope="navbar" data-part="links" aria-label="Store">(.*?)</nav>#s', $html, $nav );
    preg_match_all( '#<a[^>]*data-part="link"([^>]*)>([^<]+)</a>#', $nav[1] ?? '', $links, PREG_SET_ORDER );

    $found = [];

    foreach ( $links as [ , $attributes, $label ] ) {
        $found[ trim( $label ) ] = str_contains( $attributes, 'aria-current="page"' );
    }

    return $found;
}

it( 'heads the page with the mark, the store name and "Shop all" plus the first three top-level categories', function (): void {
    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->assertOk()->getContent();

    expect( headerLinks( $html ) )->toBe( [ 'Shop all' => true, 'Ceramics' => false, 'Linen' => false, 'Candles' => false ] )
        ->and( $html )->toContain( 'class="apui-navbar ec-header"' )
        ->toContain( 'data-sticky' )
        ->toContain( '<span class="ec-brand__mark" aria-hidden="true">FK</span>' )
        ->toContain( '<span class="ec-brand__name">Field &amp; Kiln</span>' )
        ->not->toContain( '>Mugs</a>' );
} );

it( 'marks the top-level category of the category page as current', function (): void {
    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'ceramics/mugs' ] ) )->assertOk()->getContent();

    expect( headerLinks( $html ) )->toBe( [ 'Shop all' => false, 'Ceramics' => true, 'Linen' => false, 'Candles' => false ] );
} );

it( 'lists as many categories as storefront.nav_categories allows', function (): void {
    config( [ 'artisanpack.ecommerce-storefront-livewire.storefront.nav_categories' => 0 ] );

    expect( array_keys( headerLinks( (string) $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->getContent() ) ) )->toBe( [ 'Shop all' ] );

    config( [ 'artisanpack.ecommerce-storefront-livewire.storefront.nav_categories' => 10 ] );
    CategoryTree::flush();
    app()->forgetInstance( CategoryPaths::class );

    expect( array_keys( StorefrontNavigation::header() ) )->toHaveCount( 5 );
} );

it( 'lets hosts change the header navigation, dropping incomplete links', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.layout.navigation', static fn ( array $links ): array => [
        ...$links,
        [ 'label' => 'Journal', 'url' => 'https://shop.test/journal', 'current' => true ],
        [ 'label' => '', 'url' => 'https://shop.test/nothing' ],
        'nope',
    ] );

    $links = StorefrontNavigation::header();

    expect( end( $links ) )->toBe( [ 'label' => 'Journal', 'url' => 'https://shop.test/journal', 'current' => true ] )
        ->and( $links )->toHaveCount( 5 );
} );

it( 'opens the store navigation in a drawer from the menu button below 768px', function (): void {
    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->getContent();

    expect( $html )->toContain( 'data-storefront-menu-open' )
        ->toContain( 'aria-controls="ecommerce-storefront-menu-content"' )
        ->toMatch( '#id="ecommerce-storefront-menu-content"\s+role="dialog"#' )
        ->toContain( 'aria-label="Menu"' )
        ->toContain( 'id="ecommerce-storefront-menu"' )
        ->toMatch( '#class="ec-menu-list__link"\s+aria-current="page"\s*>Shop all</a>#' )
        ->toContain( '>Candles</a>' );
} );

it( 'makes "Skip to content" the first focusable element', function (): void {
    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->getContent();
    $body = substr( $html, (int) strpos( $html, '<body' ) );

    preg_match( '#<(a|button|input|select|textarea)\b[^>]*>#', $body, $first );

    expect( $first[0] )->toContain( 'data-skip-link' )
        ->toContain( 'href="#ecommerce-storefront-content"' )
        ->toContain( 'class="apui-button ec-skip-link"' );
} );

it( 'closes the page with the footer: brand, tagline, link columns, copyright, currency and theme', function (): void {
    config( [ 'artisanpack.ecommerce-storefront-livewire.storefront.tagline' => 'Stoneware, linen and candles made in small batches.' ] );

    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->getContent();

    preg_match( '#<footer class="ec-footer" data-storefront-footer>.*?</footer>#s', $html, $footer );

    expect( $footer )->not->toBeEmpty()
        ->and( substr_count( $footer[0], '<nav' ) )->toBe( 1 )
        ->and( $footer[0] )->toContain( '<nav class="ec-footer__nav" aria-label="Footer">' )
        ->toContain( 'aria-labelledby="ec-footer-account"' )
        ->toContain( 'Stoneware, linen and candles made in small batches.' )
        ->toContain( '<h2 id="ec-footer-shop" class="ec-footer__title">Shop</h2>' )
        ->toContain( '>All products</a>' )
        ->toContain( '>Ceramics</a>' )
        ->toContain( '<h2 id="ec-footer-help" class="ec-footer__title">Help</h2>' )
        ->toContain( 'href="' . route( 'artisanpack.ecommerce.storefront.lookup' ) . '" class="ec-footer__link">Find an order</a>' )
        ->toContain( '>Sign in</a>' )
        ->toContain( '>Orders</a>' )
        ->toContain( '>Addresses</a>' )
        ->toContain( '&copy; ' . now()->year . ' Field &amp; Kiln' )
        ->toContain( 'data-currency-placement="footer"' )
        ->toContain( 'apui-theme-toggle' );
} );

it( 'leaves the tagline out unless it is configured', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->assertDontSee( 'data-storefront-tagline', false );
} );

it( 'links a signed-in shopper\'s footer to their account rather than sign in', function (): void {
    $footer = StorefrontNavigation::footer();

    expect( array_column( $footer['account']['links'], 'label' ) )->toBe( [ 'Sign in', 'Orders', 'Addresses' ] );

    $this->actingAs( makeUser() );

    expect( array_column( StorefrontNavigation::footer()['account']['links'], 'label' ) )->toBe( [ 'Account', 'Orders', 'Addresses' ] );
} );

it( 'lets hosts add, change and remove footer columns', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.layout.footerLinks', static function ( array $columns ): array {
        $columns['help']['links'][] = [ 'label' => 'Shipping & returns', 'url' => 'https://shop.test/shipping' ];
        unset( $columns['account'] );
        $columns['empty'] = [ 'title' => 'Nothing', 'links' => [] ];
        $columns[0]       = [ 'title' => 'Numbered', 'links' => [ [ 'label' => 'x', 'url' => 'y' ] ] ];

        return $columns;
    } );

    $footer = StorefrontNavigation::footer();

    expect( array_keys( $footer ) )->toBe( [ 'shop', 'help' ] )
        ->and( array_column( $footer['help']['links'], 'label' ) )->toBe( [ 'Find an order', 'Shipping & returns' ] );
} );

it( 'keeps the header actions at the end of the bar with or without the search', function (): void {
    $css = file_get_contents( dirname( __DIR__, 3 ) . '/resources/css/storefront.css' );

    preg_match( '/\.ec-header \[data-scope="navbar"\]\[data-part="actions"\] \{([^}]+)\}/', $css, $actions );

    expect( $actions[1] )->not->toContain( 'margin-inline-start' )
        ->and( $css )->toContain( '.ec-header [data-scope="navbar"][data-part="search"] + [data-scope="navbar"][data-part="actions"] {' );
} );

it( 'drops header and footer links with an unsafe URL', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.layout.navigation', static fn ( array $links ): array => [
        ...$links,
        [ 'label' => 'Script', 'url' => 'javascript:alert(1)' ],
        [ 'label' => 'Data', 'url' => 'data:text/html,<script>alert(1)</script>' ],
        [ 'label' => 'Relative', 'url' => '/journal' ],
    ] );
    addFilter( 'ap.ecommerceStorefrontLivewire.layout.footerLinks', static function ( array $columns ): array {
        $columns['help']['links'][] = [ 'label' => 'Sneaky', 'url' => ' JavaScript:alert(1)' ];

        return $columns;
    } );

    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->assertOk()->getContent();

    expect( array_column( StorefrontNavigation::header(), 'label' ) )->not->toContain( 'Script', 'Data' )->toContain( 'Relative' )
        ->and( array_column( StorefrontNavigation::footer()['help']['links'], 'label' ) )->toBe( [ 'Find an order' ] )
        ->and( $html )->not->toContain( 'javascript:' )->not->toContain( 'JavaScript:' )->not->toContain( 'data:text/html' );
} );

it( 'makes the store mark from the first two words', function ( string $name, string $mark ): void {
    expect( StorefrontNavigation::mark( $name ) )->toBe( $mark );
} )->with( [
    'two words'    => [ 'Field & Kiln', 'FK' ],
    'one word'     => [ 'Laravel', 'L' ],
    'three words'  => [ 'the little shop', 'TL' ],
    'accented'     => [ 'école ouverte', 'ÉO' ],
    'no letters'   => [ '& — !', '' ],
] );

it( 'gives each currency switcher on a page its own menu id', function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'USD', 'EUR' ] );

    Livewire::test( Switcher::class )->assertSeeHtml( 'id="ecommerce-currency-switcher"' )->assertSeeHtml( 'data-currency-placement="header"' );
    Livewire::test( Switcher::class, [ 'placement' => 'footer' ] )->assertSeeHtml( 'id="ecommerce-currency-switcher-footer"' );
    Livewire::test( Switcher::class, [ 'placement' => '"><script>' ] )->assertSeeHtml( 'id="ecommerce-currency-switcher-script-' )->assertDontSeeHtml( '<script>' );
} );

it( 'keeps placements that differ only in stripped characters apart', function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'USD', 'EUR' ] );

    $ids = static function ( string $placement ): array {
        preg_match_all( '/\sid="(ecommerce-currency-switcher[^"]*)"/', Livewire::test( Switcher::class, [ 'placement' => $placement ] )->html(), $matches );

        return $matches[1];
    };

    $dotted = $ids( 'footer.left' );
    $plain  = $ids( 'footerleft' );

    expect( $plain )->toContain( 'ecommerce-currency-switcher-footerleft', 'ecommerce-currency-switcher-footerleft-trigger', 'ecommerce-currency-switcher-footerleft-content' )
        ->and( $dotted[0] )->toMatch( '/^ecommerce-currency-switcher-footerleft-[0-9a-f]{8}$/' )
        ->and( array_intersect( $dotted, $plain ) )->toBe( [] );
} );

it( 'keeps a focus outline in forced-colors mode on the layout\'s own controls', function ( string $selector ): void {
    $css = file_get_contents( dirname( __DIR__, 3 ) . '/resources/css/storefront.css' );

    preg_match( '/' . preg_quote( $selector, '/' ) . ':focus-visible \{([^}]+)\}/', $css, $rule );

    expect( $rule[1] )->toContain( 'outline: var(--apui-focus-ring-width) solid transparent;' )->toContain( 'box-shadow:' );
} )->with( [ '.ec-brand', '.ec-menu-list__link', '.ec-footer__link', '.ec-chip' ] );

it( 'builds the catalog page with no Tailwind or DaisyUI classes of its own', function (): void {
    makeProduct( 2800, [ 'name' => 'Oxbow mug' ] )->categories()->attach( $this->mugs->id );

    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->assertOk()->getContent();

    expect( classTokens( $html ) )->not->toContain(
        'flex', 'flex-col', 'min-h-screen', 'font-sans', 'antialiased', 'sr-only', 'focus:not-sr-only', 'max-w-screen-2xl', 'mx-auto', 'p-5',
        'lg:px-10', 'border-t', 'border-base-content/10', 'link', 'link-hover', 'truncate', 'text-lg', 'font-bold', 'hidden', 'sm:block',
        'text-3xl', 'gap-6', 'grid', 'radio', 'radio-sm', 'checkbox-sm', 'h-5', 'w-5', 'sm:not-sr-only', 'rounded-full', 'text-sm',
    );
} );
