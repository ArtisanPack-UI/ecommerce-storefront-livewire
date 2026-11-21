<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Coupon;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductRelation;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Models\PromotionAction;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\CartContentsBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\CheckoutStepsBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Index as CartPage;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index as Checkout;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutLayout;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Renders `$block` through a stand-in for visual-editor's preview route,
 * signed in as an editor.
 *
 * @param  array<string, mixed>  $attrs  Raw attributes.
 */
function previewCommerceBlock( StorefrontBlock $block, array $attrs = [] ): string
{
    Route::post( '_test/commerce-preview', static fn (): string => $block->render( $block->validateAttrs( (array) request()->input( 'attributes', [] ) ) ) )
        ->name( StorefrontBlock::PREVIEW_ROUTE );

    app( 'router' )->getRoutes()->refreshNameLookups();

    test()->actingAs( makeUser() );

    return (string) test()->postJson( '_test/commerce-preview', [ 'attributes' => $attrs ] )->assertOk()->getContent();
}

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.cart.sections' );
} );

it( 'renders the shopper\'s cart with the coupon field and cross-sells', function (): void {
    checkoutCart( [ makeProduct( 1900, [ 'name' => 'Kettle' ] ) ] );

    $html = app( CartContentsBlock::class )->render( app( CartContentsBlock::class )->validateAttrs( [] ) );

    expect( $html )->toContain( 'data-commerce-block="cart-contents"' )
        ->toContain( 'Kettle' )
        ->toContain( 'data-cart-coupon' )
        ->toContain( 'data-cart-section="cross-sells"' );
} );

it( 'hides the coupon field and the cross-sells when the block turns them off', function (): void {
    checkoutCart( [ makeProduct( 1900, [ 'name' => 'Kettle' ] ) ] );

    $html = app( CartContentsBlock::class )->render( app( CartContentsBlock::class )->validateAttrs( [ 'showCoupon' => false, 'showCrossSells' => 'false' ] ) );

    expect( $html )->toContain( 'Kettle' )
        ->not->toContain( 'data-cart-coupon' )
        ->not->toContain( 'data-cart-section="cross-sells"' );
} );

it( 'clamps the block\'s attributes and drops unknown ones', function (): void {
    expect( app( CartContentsBlock::class )->validateAttrs( [ 'showCoupon' => 'nope', 'showCrossSells' => [ 1 ], 'extra' => 'x' ] ) )
        ->toBe( [ 'showCrossSells' => true, 'showCoupon' => true ] )
        ->and( app( CheckoutStepsBlock::class )->validateAttrs( [ 'layout' => 'sideways' ] ) )
        ->toBe( [ 'layout' => CheckoutStepsBlock::STORE_LAYOUT ] );
} );

it( 'ignores coupon attempts when the coupon field is hidden', function (): void {
    $cart      = checkoutCart();
    $promotion = Promotion::factory()->coupon()->create();
    PromotionAction::factory()->create( [ 'promotion_id' => $promotion->id, 'type' => 'percent-off-cart', 'config' => [ 'percent' => 10 ] ] );
    Coupon::factory()->create( [ 'promotion_id' => $promotion->id, 'code' => 'SAVE10' ] );

    Livewire::test( CartPage::class, [ 'showCoupon' => false ] )
        ->set( 'couponCode', 'SAVE10' )
        ->call( 'applyCoupon' )
        ->assertHasNoErrors()
        ->assertSet( 'couponCode', 'SAVE10' );

    expect( (int) $cart->refresh()->discount_amount )->toBe( 0 );

    Livewire::test( CartPage::class )->set( 'couponCode', 'SAVE10' )->call( 'applyCoupon' )->assertSet( 'couponCode', '' );

    expect( (int) $cart->refresh()->discount_amount )->toBeGreaterThan( 0 );
} );

it( 'locks the block\'s cart settings', function (): void {
    Livewire::test( CartPage::class, [ 'showCoupon' => false ] )->set( 'showCoupon', true );
} )->throws( CannotUpdateLockedPropertyException::class );

it( 'previews a sample cart of the newest products without creating a cart', function (): void {
    makeProduct( 1200, [ 'name' => 'Old mug' ] );
    $this->travel( 1 )->minutes();
    makeProduct( 1900, [ 'name' => 'Teapot' ] );
    $this->travel( 1 )->minutes();
    makeProduct( 2500, [ 'name' => 'Kettle' ] );

    $html = previewCommerceBlock( app( CartContentsBlock::class ), [ 'showCoupon' => false ] );

    expect( $html )->toContain( 'data-cart-preview' )
        ->toContain( 'Sample cart' )
        ->toContain( 'Kettle' )
        ->toContain( 'Teapot' )
        ->not->toContain( 'Old mug' )
        ->toContain( '$44.00' )
        ->not->toContain( 'data-cart-preview-coupon' )
        ->toContain( 'data-cart-preview-cross-sells' )
        ->and( Cart::query()->count() )->toBe( 0 );
} );

it( 'previews the cart page\'s layout on the design system', function (): void {
    makeProduct( 1900, [ 'name' => 'Teapot' ] );
    $this->travel( 1 )->minutes();
    makeProduct( 2500, [ 'name' => 'Kettle' ] );

    $html = previewCommerceBlock( app( CartContentsBlock::class ) );

    preg_match( '#<button[^>]*ec-cart-preview__checkout[^>]*>#s', $html, $checkout );

    expect( $checkout[0] ?? '' )->toContain( 'disabled' )
        ->and( $html )->toMatch( '#class="ec-block-notice ec-block-notice--sample" data-commerce-sample>\s*<span[^>]*apui-badge[^>]*data-color="info"#s' )
        ->toContain( 'apui-card ec-cart-preview__lines' )
        ->and( substr_count( $html, 'data-cart-preview-line' ) )->toBe( 2 )
        ->and( $html )->toContain( 'apui-card ec-cart-preview__summary' )
        ->toMatch( '#data-part="title"[^>]*>Order summary</h3>#' )
        ->toContain( 'Calculated at checkout' )
        ->toContain( '$44.00' )
        ->toContain( 'data-cart-preview-coupon' )
        ->toMatch( '#<p class="ec-block-notice[^"]*ec-cart-preview__cross-sells" data-cart-preview-cross-sells>#' )
        ->and( classTokens( $html ) )->not->toContain( 'flex', 'grid', 'lg:grid-cols-3', 'badge', 'badge-neutral', 'rounded-box', 'border-dashed', 'w-full', 'text-xl', 'divide-y' );
} );

it( 'asks for a product when the store has none to preview', function (): void {
    expect( previewCommerceBlock( app( CartContentsBlock::class ) ) )
        ->toContain( 'data-commerce-block-notice' )
        ->toContain( 'Add a product to the store to preview the cart.' );
} );

it( 'renders nothing on the site when the engine fails', function (): void {
    $this->mock( StorefrontCart::class, static function ( $mock ): void {
        $mock->shouldReceive( 'current' )->andThrow( new RuntimeException( 'Cart storage is down.' ) );
        $mock->shouldReceive( 'currency' )->andReturn( 'USD' );
    } );

    expect( app( CartContentsBlock::class )->render( [ 'showCrossSells' => true, 'showCoupon' => true ] ) )->toBe( '' );
} );

it( 'adds sections below the cart through cart.sections', function (): void {
    $cart = checkoutCart( [ makeProduct( 1900, [ 'name' => 'Kettle' ] ) ] );

    addFilter( 'ap.ecommerceStorefrontLivewire.cart.sections', static function ( array $sections, ?Cart $forCart ) use ( $cart ): array {
        expect( $forCart?->is( $cart ) )->toBeTrue();

        unset( $sections['cross-sells'] );

        return $sections + [ 'gift-note' => [ 'component' => 'artisanpack-ecommerce-storefront-related-products', 'params' => [ 'type' => ProductRelation::CROSS_SELL ], 'position' => 5 ] ];
    }, 10, 2 );

    Livewire::withoutLazyLoading()->test( CartPage::class )
        ->assertSeeHtml( 'data-cart-section="gift-note"' )
        ->assertDontSeeHtml( 'data-cart-section="cross-sells"' );
} );

it( 'renders the checkout in the store\'s layout by default', function (): void {
    checkoutShipping();
    checkoutGateway();
    checkoutCart();

    expect( app( CheckoutStepsBlock::class )->render( app( CheckoutStepsBlock::class )->validateAttrs( [] ) ) )
        ->toContain( 'data-commerce-block="checkout-steps"' )
        ->toContain( 'data-checkout-layout="multi_step"' );
} );

it( 'overrides the store\'s checkout layout for the block', function (): void {
    checkoutShipping();
    checkoutGateway();
    checkoutCart();

    expect( app( CheckoutStepsBlock::class )->render( [ 'layout' => CheckoutLayout::SINGLE_PAGE ] ) )->toContain( 'data-checkout-layout="single_page"' );

    Livewire::test( Checkout::class, [ 'layoutOverride' => 'sideways' ] )->assertSet( 'layout', CheckoutLayout::MULTI_STEP );
    Livewire::test( Checkout::class, [ 'layoutOverride' => CheckoutLayout::SINGLE_PAGE ] )->assertSet( 'layout', CheckoutLayout::SINGLE_PAGE );
} );

it( 'previews the checkout steps in the chosen layout without a cart', function ( string $layout, string $marker ): void {
    $html = previewCommerceBlock( app( CheckoutStepsBlock::class ), [ 'layout' => $layout ] );

    expect( $html )->toContain( $marker )
        ->toContain( 'data-checkout-preview-step="contact"' )
        ->toContain( 'data-checkout-preview-step="review"' )
        ->and( Cart::query()->count() )->toBe( 0 );
} )->with( [
    'store setting' => [ CheckoutStepsBlock::STORE_LAYOUT, 'data-checkout-preview="multi_step"' ],
    'single page'   => [ CheckoutLayout::SINGLE_PAGE, 'data-checkout-preview="single_page"' ],
] );

it( 'previews the multi-step checkout as Steps over the first step\'s Card', function (): void {
    $html = previewCommerceBlock( app( CheckoutStepsBlock::class ), [ 'layout' => CheckoutLayout::MULTI_STEP ] );

    preg_match( '#<li[^>]*data-checkout-preview-step="contact"[^>]*>#', $html, $contact );

    expect( $html )->toMatch( '#<ol[^>]*apui-steps[^>]*aria-label="Checkout steps"#s' )
        ->and( $contact[0] ?? '' )->toContain( 'data-state="current"' )
        ->toContain( 'aria-current="step"' )
        ->and( $html )->toContain( 'apui-card ec-checkout-preview__current' )
        ->toMatch( '#data-part="title"[^>]*>Contact</h3>#' )
        ->and( substr_count( $html, 'apui-skeleton' ) )->toBe( 2 )
        ->and( $html )->toMatch( '#apui-badge.*?data-part="label">Multi-step<#s' )
        ->and( classTokens( $html ) )->not->toContain( 'flex', 'flex-wrap', 'font-semibold', 'badge', 'rounded-box', 'opacity-60', 'text-base-content/60' );
} );

it( 'previews the single-page checkout as one Card per step, the first one open', function (): void {
    $html = previewCommerceBlock( app( CheckoutStepsBlock::class ), [ 'layout' => CheckoutLayout::SINGLE_PAGE ] );

    preg_match_all( '#<li data-checkout-preview-step="([a-z_-]+)"[^>]*>\s*<div([^>]*)>#s', $html, $sections );

    expect( $sections[1] )->toContain( 'contact', 'review' )
        ->and( $sections[2][0] ?? '' )->toContain( 'data-surface="default"' )->not->toContain( 'data-upcoming' )
        ->and( $sections[2][1] ?? '' )->toContain( 'data-surface="sunken"' )->toContain( 'data-upcoming' )
        ->and( $html )->toMatch( '#<li data-checkout-preview-step="contact"\s+aria-current="step"\s*>#' )
        ->toMatch( '#class="ec-checkout-preview__marker" aria-hidden="true">1</span>#' )
        ->toMatch( '#apui-badge.*?data-part="label">Single page<#s' )
        ->and( classTokens( $html ) )->not->toContain( 'flex', 'font-semibold', 'badge', 'rounded-box', 'opacity-60', 'p-4' );
} );

it( 'sends a shopper with an empty cart from the checkout block to the cart', function (): void {
    Livewire::test( Checkout::class, [ 'layoutOverride' => CheckoutLayout::SINGLE_PAGE ] )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.cart' ) );

    expect( Product::query()->count() )->toBe( 0 );
} );
