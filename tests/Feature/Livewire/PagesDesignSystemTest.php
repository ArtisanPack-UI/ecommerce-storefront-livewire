<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductAttributeValue;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Index as CartIndex;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index as CheckoutIndex;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Search\Index as SearchIndex;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;

/**
 * The tag, search, product, cart and checkout pages on the design system
 * (#132–#136).
 */

/** Tailwind and DaisyUI classes these pages used before the rebuild. */
const OLD_PAGE_CLASSES = [
    'flex', 'flex-col', 'flex-wrap', 'grid', 'gap-2', 'gap-4', 'gap-6', 'gap-8', 'gap-10', 'text-3xl', 'text-2xl', 'text-xl', 'text-sm',
    'font-bold', 'font-semibold', 'lg:grid-cols-2', 'lg:grid-cols-3', 'lg:col-span-2', 'prose', 'max-w-none', 'table', 'table-sm', 'sr-only',
    'border-t', 'border-base-content/10', 'rounded-box', 'bg-base-200', 'badge', 'badge-sm', 'badge-primary', 'badge-ghost', 'link',
    'link-primary', 'link-hover', 'divide-y', 'text-base-content/70', 'text-base-content/60', 'w-full', 'lg:sticky', 'lg:hidden', 'hidden',
];

afterEach( function (): void {
    foreach ( [ 'ap.ecommerceStorefrontLivewire.product.highlights', 'ap.ecommerceStorefrontLivewire.product.soldOut' ] as $hook ) {
        removeAllFilters( $hook );
    }
} );

it( 'heads a tag page with the category card, without its image or chips (#132)', function (): void {
    $tag = ProductTag::factory()->create( [ 'name' => 'Summer', 'slug' => 'summer' ] );
    makeProduct( 2000, [ 'name' => 'Sun hat' ] )->tags()->attach( $tag->id );

    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.tag', [ 'tag' => 'summer' ] ) )->assertOk()->getContent();

    expect( $html )->toMatch( '#<div class="apui-card ec-category-hero" data-surface="default" data-tag-header>#' )
        ->toContain( '<p class="ec-category-hero__eyebrow">Tag</p>' )
        ->toContain( '<h1 class="ec-category-hero__title">Summer</h1>' )
        ->toContain( 'aria-label="Breadcrumb"' )
        ->not->toContain( 'class="ec-category-hero__media"' )
        ->not->toContain( 'data-subcategories' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_PAGE_CLASSES );
} );

it( 'heads the search page with the results title, the large field and a neutral button (#133)', function (): void {
    makeProduct( 2000, [ 'name' => 'Linen shirt' ] );

    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.search', [ 'q' => 'linen' ] ) )->assertOk()->getContent();

    expect( $html )->toContain( '<h1 class="ec-search-page__title" data-search-title>Results for “linen”</h1>' )
        ->toContain( 'aria-label="Breadcrumb"' )
        ->toMatch( '#class="apui-input ec-search-page__field"[^>]*data-size="lg"#' )
        ->toMatch( '#<button[^>]*type="submit"[^>]*data-variant="solid"[^>]*data-color="neutral"[^>]*data-size="lg"#' )
        ->toContain( 'role="search"' )
        ->toContain( 'action="' . route( 'artisanpack.ecommerce.storefront.search' ) . '"' )
        ->toContain( '1 result' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_PAGE_CLASSES );
} );

it( 'titles the search page "Search" with no results, and shows the tips in a card with "Browse all products"', function (): void {
    makeProduct( 2000, [ 'name' => 'Linen shirt' ] );

    Livewire::withQueryParams( [ 'q' => 'teapot' ] )
        ->test( SearchIndex::class, [ 'showTitle' => true ] )
        ->assertSeeHtml( '<h1 class="ec-search-page__title" data-search-title>Search</h1>' )
        ->assertSee( 'No results for “teapot”' )
        ->assertSee( 'We couldn\'t find a product matching your search.' )
        ->assertSeeHtml( 'data-empty-state-card' )
        ->assertSeeHtml( 'data-search-tips' )
        ->assertSeeHtml( 'data-search-browse' )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.storefront.catalog' ) . '"' );

    // Under a search template's own heading, the component has none.
    Livewire::withQueryParams( [ 'q' => 'linen' ] )->test( SearchIndex::class )->assertDontSeeHtml( 'data-search-title' );
} );

it( 'lays the product page out on the design system with breadcrumbs through its category (#134)', function (): void {
    CategoryTree::flush();
    $clothing = ProductCategory::factory()->create( [ 'name' => 'Clothing', 'slug' => 'clothing' ] );
    $shirts   = ProductCategory::factory()->create( [ 'name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $clothing->id ] );
    app()->forgetInstance( CategoryPaths::class );

    $product = makeProduct( 4800, [ 'name' => 'Linen shirt', 'slug' => 'linen-shirt', 'sku' => 'LIN-1', 'short_description' => '<p>Cool and crisp.</p>', 'description' => '<p>Woven in Portugal.</p>' ] );
    $product->categories()->attach( $shirts->id );

    $fabric = ProductAttribute::query()->create( [ 'product_id' => $product->id, 'key' => 'fabric', 'label' => 'Fabric', 'is_variation' => false ] );
    ProductAttributeValue::factory()->create( [ 'product_attribute_id' => $fabric->id, 'value' => 'linen', 'label' => 'Linen' ] );

    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt' ] ) )->assertOk()->getContent();

    expect( $html )->toContain( 'class="ec-product"' )
        ->toContain( 'class="ec-product__top"' )
        ->toContain( 'href="' . route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'clothing/shirts' ] ) . '" class="ec-product__category" data-product-category>Shirts</a>' )
        ->toMatch( '#<h1 id="ec-product-\d+-name" class="ec-product__name">Linen shirt</h1>#' )
        ->toContain( '<div class="apui-prose ec-product__short-description" data-product-short-description>' )
        ->toContain( '<p class="ec-product__sku" data-product-sku>SKU <code>LIN-1</code></p>' )
        ->toContain( 'class="ec-product__details"' )
        ->toMatch( '#<h2 id="ec-product-\d+-details" class="ec-product__section-title">Product details</h2>#' )
        ->toContain( '<div class="apui-table ec-product__specs" data-density="compact">' )
        ->toContain( '<th scope="row">Fabric</th>' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_PAGE_CLASSES );

    preg_match( '#<nav[^>]*aria-label="Breadcrumb".*?</nav>#s', $html, $breadcrumbs );

    expect( $breadcrumbs[0] )->toContain( '>Shop<' )->toContain( '>Clothing<' )->toContain( '>Shirts<' )->toContain( 'Linen shirt' );
} );

it( 'lists the product\'s delivery and returns highlights, from the free-shipping threshold and the filter', function (): void {
    $product = makeProduct( 2800, [ 'name' => 'Mug' ] );

    Livewire::test( 'artisanpack-ecommerce-storefront-product-show', [ 'product' => $product ] )->assertDontSeeHtml( 'data-product-highlights' );

    config( [ 'artisanpack.ecommerce-storefront-livewire.cart.free_shipping_threshold' => [ 'USD' => 7500 ] ] );
    addFilter( 'ap.ecommerceStorefrontLivewire.product.highlights', static fn ( array $highlights ): array => [
        ...$highlights,
        [ 'icon' => 'rotate-ccw', 'title' => '30-day returns', 'description' => 'Unused and in original packaging' ],
        [ 'icon' => '"><script>', 'title' => 'Gift wrap' ],
        [ 'icon' => 'x', 'title' => '' ],
    ] );

    $html = Livewire::test( 'artisanpack-ecommerce-storefront-product-show', [ 'product' => $product ] )->html();

    expect( $html )->toContain( 'class="apui-card ec-product__highlights" data-surface="default" data-product-highlights' )
        ->toContain( 'Free shipping over $75.00' )
        ->toContain( '30-day returns' )
        ->toContain( 'Unused and in original packaging' )
        ->toContain( 'Gift wrap' )
        ->not->toContain( '<script>' )
        ->and( substr_count( $html, 'class="ec-product__highlight"' ) )->toBe( 3 );
} );

it( 'says a sold-out product is sold out, and lets a satellite replace the notice', function (): void {
    withoutBackInStock();

    $product = makeProduct( 2800, [ 'name' => 'Mug' ] );

    Livewire::test( 'artisanpack-ecommerce-storefront-product-show', [ 'product' => $product ] )->assertDontSeeHtml( 'data-product-sold-out' );

    setStock( $product, 0 );

    Livewire::test( 'artisanpack-ecommerce-storefront-product-show', [ 'product' => $product->fresh() ] )
        ->assertSeeHtml( 'data-product-sold-out' )
        ->assertSee( 'This item is sold out' );

    addFilter( 'ap.ecommerceStorefrontLivewire.product.soldOut', static fn (): HtmlString => new HtmlString( '<form data-notify-me>Notify me</form>' ) );

    Livewire::test( 'artisanpack-ecommerce-storefront-product-show', [ 'product' => $product->fresh() ] )
        ->assertSeeHtml( '<form data-notify-me>Notify me</form>' )
        ->assertDontSeeHtml( 'data-product-sold-out' );

    removeAllFilters( 'ap.ecommerceStorefrontLivewire.product.soldOut' );
    addFilter( 'ap.ecommerceStorefrontLivewire.product.soldOut', static fn (): string => 'no.such.view' );

    Livewire::test( 'artisanpack-ecommerce-storefront-product-show', [ 'product' => $product->fresh() ] )->assertDontSeeHtml( 'data-product-sold-out' );
} );

it( 'lays the cart page out as a lines card beside the summary card (#135)', function (): void {
    checkoutCart( [ makeProduct( 2800, [ 'name' => 'Mug' ] ), makeProduct( 4800, [ 'name' => 'Bowl' ] ) ] );

    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.cart' ) )->assertOk()->getContent();

    expect( $html )->toContain( '<h1 class="ec-cart__title">Your cart</h1>' )
        ->toContain( '<p class="ec-cart__count" data-cart-item-count>2 items</p>' )
        ->toContain( 'class="apui-card ec-cart__lines" data-surface="default" data-cart-lines' )
        ->toContain( 'class="apui-card ec-cart-summary" data-surface="default"' )
        ->toContain( '<form wire:submit="applyCoupon" class="ec-input-group" novalidate>' )
        ->toMatch( '#<a\s+href="[^"]+/checkout"\s+class="apui-button ec-cart-summary__checkout" data-cart-checkout="data-cart-checkout"\s+data-variant="solid"\s+data-color="primary"\s+data-size="lg"#' )
        ->toContain( 'data-cart-continue' )
        // Checkout is the cart's one solid primary (the layout's skip link and drawer have their own).
        ->and( preg_match_all( '#data-variant="solid"\s+data-color="primary"#', Livewire::test( CartIndex::class )->html() ) )->toBe( 1 )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_PAGE_CLASSES );
} );

it( 'keeps the shipping estimate in a disclosure Livewire leaves open', function (): void {
    checkoutShipping();
    checkoutCart();

    Livewire::test( CartIndex::class )
        ->assertSeeHtml( 'class="ec-disclosure" wire:ignore.self' )
        ->assertDontSeeHtml( 'wire:ignore.self open' )
        ->set( 'estimateCountry', 'US' )
        ->set( 'estimatePostcode', '76104' )
        ->call( 'estimateShipping' )
        ->assertSeeHtml( 'data-cart-estimate-rates' )
        ->assertSeeHtml( 'data-variant="cards"' );
} );

it( 'puts the undo bar above the lines and the empty cart in a card', function (): void {
    $cart = checkoutCart();

    Livewire::test( CartIndex::class, [ 'showTitle' => true ] )
        ->call( 'removeLine', (int) $cart->items()->first()->id )
        ->assertSeeHtml( 'class="ec-cart__undo" data-cart-undo' )
        ->assertSeeHtml( 'data-cart-empty' )
        ->assertSeeHtml( 'data-empty-state-card' )
        ->assertDontSeeHtml( 'data-cart-item-count' );

    // Under the cart template's own heading, the component has none.
    Livewire::test( CartIndex::class )->assertDontSeeHtml( 'ec-cart__title' );
} );

it( 'frames the checkout in the slim secure header, with no store navigation or footer (#136)', function (): void {
    checkoutCart( [ makeProduct( 2800, [ 'name' => 'Mug' ] ) ], 2 );

    $html = (string) $this->get( route( 'artisanpack.ecommerce.storefront.checkout' ) )->assertOk()->getContent();

    expect( $html )->toContain( 'data-storefront-chrome="checkout"' )
        ->toContain( '<header class="ec-checkout-header" data-checkout-header>' )
        ->toContain( 'Secure checkout' )
        ->toContain( 'data-checkout-back-to-cart' )
        ->toContain( 'href="' . route( 'artisanpack.ecommerce.storefront.cart' ) . '"' )
        ->not->toContain( 'class="apui-navbar' )
        ->not->toContain( 'data-storefront-footer' )
        ->not->toContain( 'data-storefront-menu' )
        ->and( substr_count( $html, '<header' ) )->toBe( 1 )
        ->and( $html )->toContain( '<h1 class="ec-checkout__title">Checkout</h1>' )
        ->toContain( 'Step 1 of' )
        ->toContain( 'class="ec-checkout-summary__quantity" aria-hidden="true">2</span>' )
        ->toContain( 'class="apui-card ec-checkout-summary" data-surface="default"' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_PAGE_CLASSES );

    // The other storefront pages keep the full header and footer.
    $this->get( route( 'artisanpack.ecommerce.storefront.cart' ) )->assertSee( 'class="apui-navbar', false )->assertSee( 'data-storefront-footer', false );
} );

it( 'shows every step as a card: done with Edit, open, and to come', function (): void {
    checkoutCart();

    $html = Livewire::test( CheckoutIndex::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->html();

    expect( $html )->toMatch( '#class="ec-checkout-step"\s+data-state="complete"#' )
        ->toMatch( '#class="ec-checkout-step"\s+data-state="current"\s+aria-current="step"#' )
        ->toMatch( '#class="ec-checkout-step"\s+data-state="upcoming"#' )
        ->toContain( 'data-checkout-edit="contact"' )
        ->toContain( 'class="ec-checkout-step__summary" data-checkout-step-summary="contact"' )
        ->not->toContain( 'ec-checkout__title' );
} );

it( 'summarises the checkout\'s errors in the design-system ValidationSummary, with field labels', function (): void {
    checkoutCart();

    Livewire::test( CheckoutIndex::class )
        ->set( 'email', 'not-an-email' )
        ->call( 'saveContact' )
        ->assertSeeHtml( 'class="apui-validation-summary ec-error-summary"' )
        ->assertSeeHtml( 'data-checkout-errors' )
        ->assertSeeHtml( 'href="#email"' )
        ->assertSee( 'There is 1 problem to fix:' );
} );
