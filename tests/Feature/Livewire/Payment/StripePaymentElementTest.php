<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Payment\StripePaymentElement;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Money\Currency;
use Money\Money;
use Tests\Fixtures\Gateways\FakeClientGateway;

/**
 * A Stripe-like gateway (driver `stripe-payment-element`) with one session,
 * `pi_123`, in `$status`.
 */
function stripeLikeGateway( string $status = PaymentSession::STATUS_REQUIRES_PAYMENT_METHOD ): FakeClientGateway
{
    $gateway = new FakeClientGateway( 'stripe', 'Card', 'stripe-payment-element' );

    $gateway->sessions['pi_123'] = new PaymentSession( 'stripe', 'pi_123', new Money( 2500, new Currency( 'USD' ) ), 'pi_123_secret_abc', status: $status );

    app( PaymentGatewayRegistry::class )->register( 'stripe', $gateway );

    return $gateway;
}

/**
 * The driver's props.
 *
 * @param  array<string, mixed>  $config  Config overrides.
 *
 * @return array<string, mixed>
 */
function stripeDriverProps( array $config = [] ): array
{
    return [
        'config'       => $config + [
            'driver'          => 'stripe-payment-element',
            'flow'            => 'embedded',
            'publishable_key' => 'pk_test_123',
            'client_secret'   => 'pi_123_secret_abc',
            'redirect_url'    => null,
            'options'         => [ 'locale' => 'fr-FR', 'appearance' => [ 'variables' => [ 'colorPrimary' => '#ff0000' ] ] ],
            'gateway'         => 'stripe',
        ],
        'gateway'      => 'stripe',
        'gatewayLabel' => 'Card',
        'reference'    => 'pi_123',
    ];
}

it( 'renders the Payment Element mount point, the pay button, and its script', function (): void {
    $component = Livewire::test( StripePaymentElement::class, stripeDriverProps() )
        ->assertSeeHtml( 'data-payment-driver="stripe-payment-element"' )
        ->assertSeeHtml( 'data-stripe-element' )
        ->assertSeeHtml( 'data-stripe-pay' )
        ->assertSeeHtml( 'role="alert"' )
        ->assertSee( 'Pay now' )
        ->assertSee( 'Loading the payment form…' )
        ->assertDontSeeHtml( 'data-payment-unavailable' );

    $script = implode( "\n", (array) ( $component->effects['scripts'] ?? [] ) );

    expect( $script )->toContain( 'js.stripe.com' )
        ->toContain( 'stripe.confirmPayment' )
        ->toContain( "redirect: 'if_required'" )
        ->toContain( '$wire.confirm( paymentIntent?.id' )
        ->and( $component->viewData( 'stripe' ) )->toMatchArray( [
            'src'            => 'https://js.stripe.com/v3',
            'publishableKey' => 'pk_test_123',
            'clientSecret'   => 'pi_123_secret_abc',
            'locale'         => 'fr-FR',
            'appearance'     => [ 'variables' => [ 'colorPrimary' => '#ff0000' ] ],
            'returnUrl'      => route( 'artisanpack.ecommerce.storefront.checkout.return' ),
        ] );
} );

it( 'says the method isn\'t available without a publishable key or client secret', function ( array $config ): void {
    $component = Livewire::test( StripePaymentElement::class, stripeDriverProps( $config ) )
        ->assertSeeHtml( 'data-payment-unavailable' )
        ->assertDontSeeHtml( 'data-stripe-element' );

    expect( $component->effects['scripts'] ?? [] )->toBe( [] );
} )->with( [
    'no key'    => [ [ 'publishable_key' => '' ] ],
    'no secret' => [ [ 'client_secret' => null ] ],
] );

it( 'reports a PaymentIntent Stripe confirmed', function ( string $status ): void {
    stripeLikeGateway( $status );

    Livewire::test( StripePaymentElement::class, stripeDriverProps() )
        ->call( 'confirm', 'pi_123' )
        ->assertDispatched( 'payment-confirmed', reference: 'pi_123', gateway: 'stripe' )
        ->assertNotDispatched( 'payment-failed' );
} )->with( [
    'succeeded'  => [ PaymentSession::STATUS_SUCCEEDED ],
    'authorized' => [ PaymentSession::STATUS_AUTHORIZED ],
    'processing' => [ PaymentSession::STATUS_PROCESSING ],
] );

it( 'reports a payment Stripe didn\'t confirm', function ( string $status, string $message ): void {
    stripeLikeGateway( $status );

    Livewire::test( StripePaymentElement::class, stripeDriverProps() )
        ->call( 'confirm', 'pi_123' )
        ->assertDispatched( 'payment-failed', message: $message, gateway: 'stripe' )
        ->assertNotDispatched( 'payment-confirmed' );
} )->with( [
    '3-D Secure pending' => [ PaymentSession::STATUS_REQUIRES_ACTION, 'Your bank needs you to confirm this payment. Try again.' ],
    'not paid'           => [ PaymentSession::STATUS_REQUIRES_PAYMENT_METHOD, 'Your payment couldn\'t be completed. Try again or use another payment method.' ],
    'cancelled'          => [ PaymentSession::STATUS_CANCELED, 'Your payment couldn\'t be completed. Try again or use another payment method.' ],
] );

it( 'refuses a PaymentIntent that isn\'t this session', function ( ?string $id ): void {
    stripeLikeGateway( PaymentSession::STATUS_SUCCEEDED );

    Livewire::test( StripePaymentElement::class, stripeDriverProps() )
        ->call( 'confirm', $id )
        ->assertDispatched( 'payment-failed', message: 'Your payment couldn\'t be confirmed. Try again.' )
        ->assertNotDispatched( 'payment-confirmed' );
} )->with( [ 'missing' => [ null ], 'another' => [ 'pi_999' ] ] );

it( 'says so when Stripe can\'t be reached to check the payment', function (): void {
    stripeLikeGateway( PaymentSession::STATUS_SUCCEEDED )->failRetrieval = true;

    Livewire::test( StripePaymentElement::class, stripeDriverProps() )
        ->call( 'confirm', 'pi_123' )
        ->assertDispatched( 'payment-failed', message: 'We couldn\'t check your payment with Stripe. Try again.' );
} );

it( 'passes on a decline from Stripe as plain text', function (): void {
    Livewire::test( StripePaymentElement::class, stripeDriverProps() )
        ->call( 'fail', 'Your card was <b>declined</b>.' )
        ->assertDispatched( 'payment-failed', message: 'Your card was declined.', gateway: 'stripe' );

    Livewire::test( StripePaymentElement::class, stripeDriverProps() )
        ->call( 'fail', '' )
        ->assertDispatched( 'payment-failed', message: 'Your payment couldn\'t be completed. Try again or use another payment method.' );
} );

it( 'holds no card data, only the session it was given', function (): void {
    $component = Livewire::test( StripePaymentElement::class, stripeDriverProps() );

    expect( array_keys( $component->instance()->all() ) )->toEqualCanonicalizing( [ 'config', 'gateway', 'gatewayLabel', 'reference' ] );
} );

it( 'keeps its props out of the browser\'s reach', function ( string $property ): void {
    Livewire::test( StripePaymentElement::class, stripeDriverProps() )->set( $property, 'pi_evil' );
} )->with( [ 'reference', 'config.client_secret', 'gateway' ] )->throws( CannotUpdateLockedPropertyException::class );
