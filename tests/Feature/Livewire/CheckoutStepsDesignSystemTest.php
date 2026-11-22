<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * The checkout's contact, address, shipping, review and payment steps on
 * the design system (#137–#141).
 */

/** Tailwind and DaisyUI classes the steps used before the rebuild. */
const OLD_STEP_CLASSES = [
    'flex', 'flex-col', 'grid', 'gap-1', 'gap-2', 'gap-3', 'gap-4', 'gap-6', 'text-sm', 'text-lg', 'font-semibold', 'font-bold', 'block',
    'sm:grid-cols-2', 'sm:w-auto', 'w-full', 'items-start', 'justify-between', 'link', 'link-primary', 'sr-only', 'text-base-content/70',
    'rounded-box', 'border', 'border-primary', 'p-5', 'mt-2',
];

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.checkout.paymentMethod' );
} );

it( 'prompts a guest to sign in on the sunken surface, then asks for the email with its icon and hint (#137)', function (): void {
    Route::get( '/login', static fn (): string => 'login' )->name( 'login' );
    checkoutCart();

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toContain( '<div class="ec-checkout-callout" data-checkout-sign-in-link>' )
        ->toContain( 'Have an account? Sign in for faster checkout.' )
        ->toMatch( '#<a[^>]*href="' . preg_quote( route( 'login' ), '#' ) . '"[^>]*data-variant="outline"[^>]*data-color="neutral"#' )
        ->toContain( 'We&#039;ll send your receipt and shipping updates here.' )
        ->toMatch( '#data-field="email">\s*<div class="ec-field"#' )
        ->toContain( 'data-part="icon"' )
        ->toContain( 'Email me news and offers' )
        ->toContain( 'class="ec-checkout-form" data-checkout-contact-form' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_STEP_CLASSES );
} );

it( 'heads the address form "Ship to", with billing the same by default (#138)', function (): void {
    checkoutShipping();
    checkoutCart();

    $html = Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->assertSet( 'step', 'address' )
        ->html();

    expect( $html )->toContain( 'class="ec-checkout-form ec-checkout-form--loose" data-checkout-address-form' )
        ->toMatch( '#data-address-form="shipping"#' )
        ->toContain( 'Ship to' )
        ->toContain( 'Billing address is the same as shipping' )
        ->not->toContain( 'data-address-form="billing"' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_STEP_CLASSES );
} );

it( 'offers saved addresses as radio cards', function (): void {
    checkoutShipping();
    $user = makeUser( [ 'email' => 'ada@example.test' ] );
    $this->actingAs( $user );
    checkoutCart();
    CustomerAddress::factory()->defaultShipping()->defaultBilling()->create( [ 'customer_id' => Customer::forUser( $user )->id, 'label' => 'Home' ] + checkoutAddress() );

    Livewire::test( Index::class )
        ->assertSet( 'step', 'address' )
        ->assertSeeHtml( 'class="apui-radio-group ec-checkout-choices"' )
        ->assertSeeHtml( 'data-variant="cards"' )
        ->assertSee( 'Home' )
        ->assertSee( 'Use a new address' )
        ->set( 'billingSameAsShipping', false )
        ->assertSeeHtml( 'data-checkout-saved="billing"' );
} );

it( 'says where the order ships, and lists the rates as priced radio cards (#139)', function (): void {
    checkoutShipping( [ 'Standard' => [ 600, '3–5 business days' ], 'Studio pickup' => 0 ] );
    checkoutCart();

    $component = Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->set( 'shipping', checkoutAddress() )
        ->call( 'saveAddress' )
        ->assertSet( 'step', 'shipping' );

    $html = $component->html();

    expect( $html )->toContain( 'Shipping to <span class="ec-checkout-ship-to__address">1 Main Street, Springfield, IL 62701</span>' )
        ->toMatch( '#<button[^>]*wire:click="goTo\( \'address\' \)" data-checkout-change-address="data-checkout-change-address"\s+data-variant="link"#' )
        ->toContain( 'class="apui-radio-group ec-checkout-choices ec-checkout-choices--priced"' )
        ->toContain( 'data-part="item-description" id="ec-checkout-shipping-rate-0-description">3–5 business days</span>' )
        ->toContain( 'data-part="item-meta">$6.00</span>' )
        ->toContain( 'data-part="item-meta">Free</span>' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_STEP_CLASSES );

    $component->call( 'goTo', 'address' )->assertSet( 'step', 'address' );
} );

it( 'keeps the review to the note with its count, the account, the terms with their link, and "Continue to payment" (#140)', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url', 'https://shop.test/terms' );
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );
    checkoutShipping();
    checkoutCart();

    $component = checkoutAtReview( checkoutGateway() );

    expect( $component->html() )->toContain( 'Order note <span class="ec-field__optional">(optional)</span>' )
        ->toContain( 'placeholder="Anything the store should know about your order"' )
        ->toContain( 'maxlength="' . Index::NOTE_MAX_LENGTH . '"' )
        ->toContain( '0 / ' . Index::NOTE_MAX_LENGTH . '</span>' )
        ->toContain( 'class="ec-checkout-callout ec-checkout-callout--stacked" data-checkout-account' )
        ->not->toContain( 'data-checkout-password' )
        ->toMatch( '#I accept the <a href="https://shop.test/terms" class="apui-link" target="_blank" rel="noopener" data-checkout-terms-link>terms and conditions<span class="apui-sr-only"> \(opens in a new tab\)</span></a>#' )
        ->toContain( 'Continue to payment' )
        ->not->toContain( 'data-checkout-review-lines' )
        ->and( classTokens( $component->html() ) )->not->toContain( ...OLD_STEP_CLASSES );

    $component->set( 'createAccount', true )->assertSeeHtml( 'class="ec-checkout-callout__nested" data-field="password"' );
} );

it( 'summarises the finished review: terms accepted, an account to create, a note', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url', 'https://shop.test/terms' );
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );
    checkoutShipping();
    checkoutCart();

    $html = checkoutAtReview( checkoutGateway() )
        ->set( 'orderNote', 'Ring twice' )
        ->set( 'acceptTerms', true )
        ->set( 'createAccount', true )
        ->set( 'password', 'correct-horse-battery' )
        ->call( 'saveReview' )
        ->assertSet( 'step', 'payment' )
        ->html();

    expect( $html )->toContain( 'data-checkout-step-summary="review"' )
        ->toContain( '<p class="ec-step-summary__text">Terms accepted · Account will be created · Note added</p>' )
        ->not->toContain( 'correct-horse-battery' );
} );

it( 'says "Done" for a review with nothing to settle', function (): void {
    checkoutShipping();
    checkoutCart();

    checkoutAtReview( checkoutGateway() )
        ->call( 'saveReview' )
        ->assertSeeHtml( 'data-checkout-step-summary="review"' )
        ->assertSeeHtml( '<p class="ec-step-summary__text">Done</p>' );
} );

it( 'lists the gateways as radio cards, and opens the chosen one around its driver (#141)', function (): void {
    checkoutShipping();
    $gateway = checkoutGateway( 'fake', 'Fake card' );
    checkoutGateway( 'other', 'Other wallet' );
    checkoutCart();

    $component = checkoutAtReview( $gateway )->call( 'saveReview' )->assertSet( 'step', 'payment' );

    expect( $component->html() )->toMatch( '#<fieldset\s+class="apui-radio-group ec-payment-methods"\s+id="ec-checkout-gateway"\s+data-orientation="vertical"\s+data-variant="cards"#' )
        ->toContain( '<legend data-scope="radio-group" data-part="label">Payment method</legend>' )
        ->toContain( 'name="ec-checkout-gateway"' )
        ->toContain( 'value="other"' )
        ->not->toContain( 'data-selected' )
        ->not->toContain( 'data-checkout-payment-driver' )
        ->and( classTokens( $component->html() ) )->not->toContain( ...OLD_STEP_CLASSES );

    $html = $component->set( 'gateway', 'other' )->html();

    expect( $html )->toMatch( '#data-selected\s+data-checkout-gateway="other"[\s\S]*?class="ec-payment-method__panel"[\s\S]*?data-fake-driver="other_ps_1"#' )
        ->toContain( 'Your order is placed as soon as your payment is confirmed.' )
        ->and( substr_count( $html, 'data-selected' ) )->toBe( 1 );
} );

it( 'shows a gateway\'s description and icon from the paymentMethod filter', function (): void {
    checkoutShipping();
    $gateway = checkoutGateway( 'fake', 'Fake card' );
    checkoutGateway( 'other', 'Other wallet' );
    checkoutCart();

    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.paymentMethod', static fn ( array $details, string $key ): array => 'other' === $key
        ? [ 'description' => 'You\'ll continue to Other wallet to pay, then come back here.', 'icon' => 'external-link' ]
        : [ 'description' => '  ', 'icon' => 'credit-card' ] );

    $html = checkoutAtReview( $gateway )->call( 'saveReview' )->html();

    expect( $html )->toContain( 'id="ec-checkout-gateway-1-description">You&#039;ll continue to Other wallet to pay, then come back here.</span>' )
        ->toContain( 'aria-describedby="ec-checkout-gateway-1-description"' )
        ->not->toContain( 'id="ec-checkout-gateway-0-description"' )
        ->and( substr_count( $html, 'ec-payment-method__icon' ) )->toBe( 2 );
} );

it( 'shows one gateway as its chosen card, without a radio', function (): void {
    checkoutShipping();
    checkoutCart();

    $html = checkoutAtReview( checkoutGateway() )->call( 'saveReview' )->assertSet( 'gateway', 'fake' )->html();

    expect( $html )->toContain( '<div class="ec-payment-method__header" data-checkout-gateway-single>' )
        ->toContain( 'Pay with Fake card' )
        ->toMatch( '#data-selected\s+data-checkout-gateway="fake"#' )
        ->toContain( 'data-checkout-payment-driver="fake-driver"' )
        ->not->toContain( 'type="radio"' )
        ->not->toContain( 'data-checkout-gateways' );
} );

it( 'places a free order with "Place order"', function (): void {
    checkoutGateway();
    $freebie = Product::factory()->digital()->create( [ 'name' => 'Free guide' ] );
    ProductPrice::factory()->forPriceable( $freebie )->create( [ 'currency' => 'USD', 'price_amount' => 0 ] );
    checkoutCart( [ $freebie ] );

    Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->assertSeeHtml( 'class="ec-checkout-form__note" data-checkout-digital-only' )
        ->set( 'billing', checkoutAddress() )
        ->call( 'saveAddress' )
        ->call( 'saveReview' )
        ->assertSet( 'step', 'payment' )
        ->assertSeeHtml( 'class="ec-checkout-form__note" data-checkout-payment-free' )
        ->assertSeeHtml( 'data-checkout-place-order' )
        ->assertSee( 'Place order' )
        ->assertDontSee( 'Place order ·' );
} );

it( 'keeps the old step views\' strings in every catalogue, for hosts that published them', function ( string $locale ): void {
    $catalogue = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/lang/' . $locale . '.json' ), true );

    expect( $catalogue )->toHaveKeys( [
        'Sign in for faster checkout',
        'We\'ll send your order confirmation here.',
        'Order note (optional)',
        'I accept the terms and conditions',
        'Read the terms and conditions',
        'You\'ll pay :total.',
    ] );
} )->with( [ 'en', 'de', 'es', 'fr' ] );
