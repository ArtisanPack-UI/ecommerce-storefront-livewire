<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Services\CheckoutService;
use ArtisanPackUI\Ecommerce\ValueObjects\Address;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload;
use Livewire\Livewire;
use Tests\Fixtures\Gateways\FakeClientGateway;

/**
 * A signed-in shopper's cart with a payment session (`fake_ps_1`) waiting
 * to be confirmed.
 *
 * @return array{cart: Cart, gateway: FakeClientGateway}
 */
function cartAwaitingPayment(): array
{
    test()->actingAs( makeUser( [ 'email' => 'ada@example.test' ] ) );

    checkoutShipping();
    $gateway  = checkoutGateway();
    $cart     = checkoutCart( [ makeProduct( 2000 ) ] );
    $checkout = app( CheckoutService::class );

    $checkout->setAddress( $cart, Address::fromArray( checkoutAddress() ) );
    $checkout->setShippingMethod( $cart, $checkout->shippingRates( $cart )->first()->id() );
    $checkout->setPaymentGateway( $cart, 'fake' );
    $checkout->createPaymentSession( $cart );

    return [ 'cart' => $cart->refresh(), 'gateway' => $gateway ];
}

it( 'serves the checkout privately: not cached and not indexed', function (): void {
    $this->actingAs( makeUser() );
    checkoutCart();

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout' ) )
        ->assertOk()
        ->assertHeader( 'X-Robots-Tag', 'noindex, nofollow' )
        ->assertSee( 'data-ecommerce-checkout', false )
        ->assertSee( 'data-ecommerce-storefront-global', false )
        ->assertDontSee( 'data-screen-pending', false )
        ->assertSee( 'data-checkout-steps', false );

    expect( $this->get( route( 'artisanpack.ecommerce.storefront.checkout' ) )->headers->get( 'Cache-Control' ) )->toContain( 'no-store' );
} );

it( 'sends a guest with an empty cart from the checkout page to the cart', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.checkout' ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.cart' ) );
} );

it( 'sends a return with no payment to resume back to the checkout', function (): void {
    $response = $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return' ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout' ) )
        ->assertHeader( 'X-Robots-Tag', 'noindex, nofollow' );

    expect( $response->headers->get( 'Cache-Control' ) )->toContain( 'no-store' );
} );

it( 'places the order when the provider confirmed the payment of a reviewed checkout', function (): void {
    [ 'cart' => $cart, 'gateway' => $gateway ] = cartAwaitingPayment();
    $gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_SUCCEEDED );

    session()->put( Index::REVIEW_SESSION_KEY, [ 'cart' => $cart->id, 'at' => now()->getTimestamp(), 'note' => 'From the bank', 'terms' => false, 'account' => false, 'password' => null ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return', [ 'payment_intent' => 'fake_ps_1', 'redirect_status' => 'succeeded' ] ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout', [ 'step' => 'payment' ] ) )
        ->assertSessionHas( Index::CONFIRMED_PAYMENT_SESSION_KEY, [ 'reference' => 'fake_ps_1', 'total' => 2500 ] );

    $component = Livewire::withQueryParams( [ 'step' => 'payment' ] )->test( Index::class );
    $order     = Order::query()->sole();

    $component->assertRedirect( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) );

    expect( $order )->payment_status->toBe( 'paid' )->customer_note->toBe( 'From the bank' )
        ->and( session()->has( Index::CONFIRMED_PAYMENT_SESSION_KEY ) )->toBeFalse();
} );

it( 'keeps a confirmed payment for a checkout that still needs reviewing', function (): void {
    [ 'gateway' => $gateway ] = cartAwaitingPayment();
    $gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_SUCCEEDED );

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return', [ 'payment_intent' => 'fake_ps_1' ] ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout', [ 'step' => 'payment' ] ) );

    Livewire::withQueryParams( [ 'step' => 'payment' ] )->test( Index::class )
        ->assertSet( 'confirmedPayment', [ 'reference' => 'fake_ps_1', 'total' => 2500 ] )
        ->assertSet( 'step', 'review' )
        ->call( 'saveReview' )
        ->assertSet( 'step', 'payment' )
        ->assertSeeHtml( 'data-checkout-payment-confirmed' );

    expect( Order::query()->count() )->toBe( 0 );
} );

it( 'limits how often the return route asks the provider', function (): void {
    [ 'gateway' => $gateway ] = cartAwaitingPayment();
    config()->set( 'artisanpack.ecommerce.rate_limits.cart.mutate.per_cart', 1 );
    config()->set( 'artisanpack.ecommerce.rate_limits.cart.mutate.per_ip', 1 );

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return' ) );
    $before = $gateway->retrievals;

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return' ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout', [ 'step' => 'payment' ] ) );

    expect( $gateway->retrievals )->toBe( $before )
        ->and( session( ToastPayload::SESSION_KEY )['toast']['description'] )->toBe( 'Too many attempts. Try again in a moment.' );
} );

it( 'sends the shopper back to payment when the payment didn\'t go through', function ( string $status, string $description ): void {
    [ 'gateway' => $gateway ] = cartAwaitingPayment();
    $gateway->setStatus( 'fake_ps_1', $status );

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return', [ 'reference' => 'fake_ps_1' ] ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout', [ 'step' => 'payment' ] ) )
        ->assertSessionMissing( Index::CONFIRMED_PAYMENT_SESSION_KEY );

    expect( session( ToastPayload::SESSION_KEY )['toast'] )
        ->title->toBe( e( 'Your payment wasn\'t completed.' ) )
        ->description->toBe( e( $description ) );
} )->with( [
    'needs 3-D Secure' => [ PaymentSession::STATUS_REQUIRES_ACTION, 'Your bank needs you to confirm this payment. Try again.' ],
    'declined'         => [ PaymentSession::STATUS_REQUIRES_PAYMENT_METHOD, 'Try again or use another payment method.' ],
    'cancelled'        => [ PaymentSession::STATUS_CANCELED, 'Try again or use another payment method.' ],
] );

it( 'sends the shopper back to payment when the confirmed payment was for an older total', function (): void {
    [ 'gateway' => $gateway ] = cartAwaitingPayment();

    $gateway->sessions['fake_ps_1'] = new PaymentSession( 'fake', 'fake_ps_1', new Money\Money( 100, new Money\Currency( 'USD' ) ), status: PaymentSession::STATUS_SUCCEEDED );

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return', [ 'payment_intent' => 'fake_ps_1' ] ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout', [ 'step' => 'payment' ] ) )
        ->assertSessionMissing( Index::CONFIRMED_PAYMENT_SESSION_KEY );

    expect( session( ToastPayload::SESSION_KEY )['toast']['title'] )->toBe( 'Your order total has changed.' );
} );

it( 'refuses a return for another payment', function (): void {
    [ 'gateway' => $gateway ] = cartAwaitingPayment();
    $gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_SUCCEEDED );

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return', [ 'payment_intent' => 'pi_someone_else' ] ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout', [ 'step' => 'payment' ] ) )
        ->assertSessionMissing( Index::CONFIRMED_PAYMENT_SESSION_KEY );

    expect( session( ToastPayload::SESSION_KEY )['toast']['title'] )->toBe( e( 'That payment doesn\'t belong to this checkout.' ) );
} );

it( 'says so when the provider can\'t be reached', function (): void {
    [ 'gateway' => $gateway ] = cartAwaitingPayment();
    $gateway->failRetrieval   = true;

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return' ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout', [ 'step' => 'payment' ] ) );

    expect( session( ToastPayload::SESSION_KEY )['toast']['title'] )->toBe( e( 'We couldn\'t check your payment.' ) );
} );

it( 'ignores a returned payment the cart has since replaced', function (): void {
    [ 'cart' => $cart ] = cartAwaitingPayment();

    session()->put( Index::CONFIRMED_PAYMENT_SESSION_KEY, [ 'reference' => 'fake_ps_0', 'total' => (int) $cart->total_amount ] );

    Livewire::withQueryParams( [ 'step' => 'payment' ] )->test( Index::class )
        ->assertSet( 'confirmedPayment', null )
        ->assertSet( 'step', 'review' );
} );
