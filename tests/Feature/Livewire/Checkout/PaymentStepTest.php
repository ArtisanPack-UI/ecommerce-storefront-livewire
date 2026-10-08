<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Checkout\CheckoutState;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\Services\CheckoutService;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Payment\RedirectDriver;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Fixtures\Gateways\FakeGateway;
use Tests\Fixtures\Livewire\FakePaymentDriver;

/**
 * The checkout, at the payment step.
 */
function checkoutAtPayment(): Testable
{
    $component = Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->set( 'shipping', checkoutAddress() )
        ->call( 'saveAddress' );

    return $component->set( 'shippingRate', (string) $component->get( 'rates' )[0]['id'] )
        ->call( 'saveShipping' )
        ->assertSet( 'step', 'payment' );
}

beforeEach( function (): void {
    checkoutShipping( [ 'Standard' => 500 ] );
} );

it( 'chooses the only gateway, sets up its payment, and mounts its driver', function (): void {
    $gateway = checkoutGateway();
    $cart    = checkoutCart( [ makeProduct( 2000 ) ] );

    $component = checkoutAtPayment()
        ->assertSet( 'gateway', 'fake' )
        ->assertSeeHtml( 'data-checkout-gateway-single' )
        ->assertSee( 'Pay with Fake card' )
        ->assertSeeHtml( 'data-checkout-payment-driver="fake-driver"' )
        ->assertSeeLivewire( FakePaymentDriver::class )
        ->assertSeeHtml( 'data-fake-driver="fake_ps_1"' )
        ->assertSee( 'fake_ps_1_secret' );

    expect( $component->get( 'payment' ) )->toMatchArray( [ 'gateway' => 'fake', 'label' => 'Fake card', 'reference' => 'fake_ps_1', 'component' => 'fake-payment-driver' ] )
        ->and( $component->get( 'payment' )['config'] )->toMatchArray( [ 'driver' => 'fake-driver', 'gateway' => 'fake', 'client_secret' => 'fake_ps_1_secret' ] )
        ->and( $gateway->contexts[0]['return_url'] )->toBe( route( 'artisanpack.ecommerce.storefront.checkout.return' ) )
        ->and( $cart->refresh() )
        ->payment_gateway_key->toBe( 'fake' )
        ->payment_reference->toBe( 'fake_ps_1' )
        ->checkout_state->toBe( CheckoutState::PAYMENT_PENDING );
} );

it( 'reuses the payment session when the shopper comes back', function (): void {
    $gateway = checkoutGateway();
    checkoutCart();

    checkoutAtPayment()->assertSet( 'payment.reference', 'fake_ps_1' );

    Livewire::test( Index::class )
        ->assertSet( 'step', 'payment' )
        ->assertSet( 'payment.reference', 'fake_ps_1' );

    expect( $gateway->sessions )->toHaveCount( 1 );
} );

it( 'lets the shopper choose between gateways', function (): void {
    checkoutGateway( 'fake', 'Fake card' );
    checkoutGateway( 'other', 'Other wallet' );
    $cart = checkoutCart();

    checkoutAtPayment()
        ->assertSet( 'gateway', '' )
        ->assertSet( 'payment', null )
        ->assertSeeHtml( 'data-checkout-gateways' )
        ->assertSee( 'Fake card' )
        ->assertSee( 'Other wallet' )
        ->assertDontSeeHtml( 'data-checkout-payment-driver' )
        ->set( 'gateway', 'other' )
        ->assertSet( 'payment.reference', 'other_ps_1' )
        ->assertSeeLivewire( FakePaymentDriver::class )
        ->set( 'gateway', 'fake' )
        ->assertSet( 'payment.reference', 'fake_ps_1' );

    expect( $cart->refresh() )->payment_gateway_key->toBe( 'fake' )->payment_reference->toBe( 'fake_ps_1' );
} );

it( 'refuses a gateway that isn\'t on offer', function (): void {
    checkoutGateway( 'fake' );
    checkoutGateway( 'other' );
    checkoutCart();

    checkoutAtPayment()
        ->set( 'gateway', 'nope' )
        ->assertHasErrors( 'gateway' )
        ->assertSee( 'Choose a payment method.' )
        ->assertSet( 'payment', null );
} );

it( 'hides a gateway whose payment UI can\'t be shown', function (): void {
    checkoutGateway( 'fake', 'Fake card' );
    checkoutGateway( 'mystery', 'Mystery pay', 'unknown-driver' );
    checkoutCart();

    checkoutAtPayment()
        ->set( 'gateway', 'mystery' )
        ->assertHasErrors( 'gateway' )
        ->assertSee( 'This payment method isn\'t available.' )
        ->assertSet( 'gateway', '' )
        ->assertSet( 'payment', null )
        ->assertSet( 'hiddenGateways', [ 'mystery' ] )
        ->assertDontSee( 'Mystery pay' )
        ->assertSee( 'Pay with Fake card' );
} );

it( 'says so when no gateway can take the payment', function (): void {
    checkoutGateway( 'mystery', 'Mystery pay', 'unknown-driver' );
    checkoutCart();

    checkoutAtPayment()
        ->assertSet( 'hiddenGateways', [ 'mystery' ] )
        ->assertSeeHtml( 'data-checkout-no-gateways' )
        ->assertSee( 'This payment method isn\'t available.' );
} );

it( 'says so when the store has no gateways', function (): void {
    checkoutCart();

    checkoutAtPayment()
        ->assertSeeHtml( 'data-checkout-no-gateways' )
        ->assertSee( 'No payment methods are available' );
} );

it( 'renders a redirect gateway with the redirect driver', function (): void {
    app( PaymentGatewayRegistry::class )->register( 'hosted', new FakeGateway( 'hosted', 'Hosted pay', 'https://pay.example.test/session/1' ) );
    checkoutCart();

    checkoutAtPayment()
        ->assertSet( 'payment.component', 'artisanpack-ecommerce-storefront-payment-redirect' )
        ->assertSeeLivewire( RedirectDriver::class )
        ->assertSee( 'Continue to Hosted pay' );
} );

it( 'moves on to review once the driver confirms the payment', function (): void {
    $gateway = checkoutGateway();
    $cart    = checkoutCart( [ makeProduct( 2000 ) ] );

    $component = checkoutAtPayment();
    $gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_AUTHORIZED );

    $component
        ->dispatch( 'payment-confirmed', reference: 'fake_ps_1', gateway: 'fake' )
        ->assertHasNoErrors()
        ->assertSet( 'step', 'review' )
        ->assertSet( 'confirmedPayment', [ 'reference' => 'fake_ps_1', 'total' => 2500 ] )
        ->assertSet( 'announcement', 'Step 5 of 5: Review order' )
        ->assertSeeHtml( 'data-checkout-review' )
        ->assertSeeHtml( 'data-checkout-step-summary="payment"' )
        ->assertSee( 'Fake card' );

    expect( $cart->refresh()->checkout_state )->toBe( CheckoutState::PAYMENT_PENDING );
} );

it( 'refuses a confirmation the gateway doesn\'t back up', function ( bool $providerDown ): void {
    $gateway = checkoutGateway();
    checkoutCart();

    $component              = checkoutAtPayment();
    $gateway->failRetrieval = $providerDown;

    $component->dispatch( 'payment-confirmed', reference: 'fake_ps_1' )
        ->assertHasErrors( 'gateway' )
        ->assertSee( 'Your payment couldn\'t be confirmed. Try again.' )
        ->assertSet( 'confirmedPayment', null )
        ->assertSet( 'step', 'payment' );
} )->with( [ 'not paid' => [ false ], 'provider down' => [ true ] ] );

it( 'refuses a confirmed payment made for an older total', function (): void {
    $gateway = checkoutGateway();
    checkoutCart( [ makeProduct( 2000 ) ] );

    $component = checkoutAtPayment();

    $gateway->sessions['fake_ps_1'] = new PaymentSession( 'fake', 'fake_ps_1', new Money\Money( 100, new Money\Currency( 'USD' ) ), status: PaymentSession::STATUS_AUTHORIZED );

    $component->dispatch( 'payment-confirmed', reference: 'fake_ps_1' )
        ->assertHasErrors( 'gateway' )
        ->assertSet( 'confirmedPayment', null )
        ->assertSet( 'step', 'payment' );
} );

it( 'sets the payment up again when the cart changes on the payment step', function (): void {
    $gateway = checkoutGateway();
    $cart    = checkoutCart( [ makeProduct( 2000 ) ] );

    $component = checkoutAtPayment()->assertSet( 'payment.reference', 'fake_ps_1' );

    app( StorefrontCartService::class )->updateItem( $cart, $cart->items()->sole(), 2 );

    $component->dispatch( 'ecommerce-cart-updated', count: 2 );

    $reference = (string) $component->get( 'payment.reference' );

    expect( $reference )->not->toBe( 'fake_ps_1' )
        ->and( (int) $gateway->sessions[ $reference ]->amount->getAmount() )->toBe( 4500 )
        ->and( $cart->refresh()->payment_reference )->toBe( $reference );
} );

it( 'ignores a confirmation for another payment', function (): void {
    checkoutGateway();
    checkoutCart();

    checkoutAtPayment()
        ->dispatch( 'payment-confirmed', reference: 'someone_elses' )
        ->assertHasErrors( 'gateway' )
        ->assertSee( 'That payment doesn\'t belong to this checkout. Try again.' )
        ->assertSet( 'confirmedPayment', null )
        ->assertSet( 'step', 'payment' );
} );

it( 'keeps the shopper on payment when the driver reports a failure', function (): void {
    checkoutGateway();
    checkoutCart();

    checkoutAtPayment()
        ->dispatch( 'payment-failed', message: 'Your card was declined.' )
        ->assertSet( 'step', 'payment' )
        ->assertSet( 'confirmedPayment', null )
        ->call( 'goTo', 'review' )
        ->assertSet( 'step', 'payment' );
} );

it( 'asks for the payment again when the total changes after it was confirmed', function (): void {
    $gateway = checkoutGateway();
    $cart    = checkoutCart( [ makeProduct( 2000 ) ] );

    $component = checkoutAtPayment();
    $gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_AUTHORIZED );

    $component
        ->dispatch( 'payment-confirmed', reference: 'fake_ps_1' )
        ->assertSet( 'step', 'review' );

    app( StorefrontCartService::class )->updateItem( $cart, $cart->items()->sole(), 2 );

    $component->dispatch( 'ecommerce-cart-updated', count: 2 )
        ->assertSet( 'step', fn ( string $step ): bool => 'review' !== $step );
} );

it( 'asks a guest to sign in before paying when the store requires an account', function (): void {
    checkoutGateway();
    config()->set( 'artisanpack.ecommerce.checkout.guest_checkout', CheckoutService::GUESTS_REQUIRE_ACCOUNT );
    $cart = checkoutCart();

    checkoutAtPayment()
        ->assertSeeHtml( 'data-checkout-payment-account-needed' )
        ->assertSet( 'payment', null )
        ->assertDontSeeLivewire( FakePaymentDriver::class );

    expect( $cart->refresh()->payment_reference )->toBeNull();
} );

it( 'needs no payment for a free order', function (): void {
    checkoutGateway();
    $freebie = Product::factory()->digital()->create( [ 'name' => 'Free guide' ] );
    ProductPrice::factory()->forPriceable( $freebie )->create( [ 'currency' => 'USD', 'price_amount' => 0 ] );
    checkoutCart( [ $freebie ] );

    Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->set( 'billing', checkoutAddress() )
        ->call( 'saveAddress' )
        ->assertSet( 'step', 'payment' )
        ->assertSeeHtml( 'data-checkout-payment-free' )
        ->assertSet( 'payment', null )
        ->call( 'continueWithoutPayment' )
        ->assertSet( 'step', 'review' )
        ->assertSee( 'No payment needed' );
} );

it( 'shows the engine\'s refusal to set up the payment', function (): void {
    checkoutGateway();
    checkoutCart();

    addFilter( 'ap.ecommerce.checkout.canTransitionTo', static fn ( bool $allowed, ArtisanPackUI\Ecommerce\Models\Cart $cart, string $to ): bool => CheckoutState::PAYMENT_PENDING !== $to, 10, 3 );

    checkoutAtPayment()
        ->assertHasErrors( 'gateway' )
        ->assertSee( 'This checkout step isn\'t available for your cart.' )
        ->assertSet( 'payment', null )
        ->assertDontSeeLivewire( FakePaymentDriver::class );

    removeAllFilters( 'ap.ecommerce.checkout.canTransitionTo' );
} );

it( 'says so when the provider can\'t set up the payment', function (): void {
    $gateway               = checkoutGateway();
    $gateway->failCreation = true;
    checkoutCart();

    checkoutAtPayment()
        ->assertHasErrors( 'gateway' )
        ->assertSee( 'Something went wrong. Try again in a moment.' )
        ->assertSet( 'payment', null );
} );

it( 'confirms through a satellite driver end to end', function (): void {
    $gateway = checkoutGateway();
    checkoutCart();

    checkoutAtPayment();

    $gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_AUTHORIZED );

    Livewire::test( FakePaymentDriver::class, [ 'config' => [], 'gateway' => 'fake', 'gatewayLabel' => 'Fake card', 'reference' => 'fake_ps_1' ] )
        ->call( 'confirm' )
        ->assertDispatched( 'payment-confirmed', reference: 'fake_ps_1', gateway: 'fake' );
} );
