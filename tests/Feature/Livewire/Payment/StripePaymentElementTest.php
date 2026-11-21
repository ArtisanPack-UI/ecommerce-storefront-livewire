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

it( 'lays the form out in a Card with the reassurance line and a full-width pay button', function (): void {
    $html = Livewire::test( StripePaymentElement::class, stripeDriverProps() )->html();

    preg_match( '#<button[^>]*data-stripe-pay[^>]*>#s', $html, $button );

    expect( $html )->toContain( 'apui-card ec-stripe__card' )
        ->toContain( 'class="ec-stripe__reassurance"' )
        ->toContain( 'Your card details go straight to Stripe. This store never sees or stores them.' )
        ->toMatch( '#ec-stripe__reassurance">\s*<span[^>]*apui-icon[^>]*aria-hidden="true"#' )
        ->and( $button[0] ?? '' )->toContain( 'ec-stripe__pay' )
        ->toContain( 'disabled' )
        ->toContain( 'data-color="primary"' );
} );

it( 'loads with field skeletons under the glass Loading overlay', function (): void {
    $html = Livewire::test( StripePaymentElement::class, stripeDriverProps() )->html();

    preg_match( '#<div class="ec-stripe__loading" data-stripe-loading>(.*?)<div class="ec-stripe__element"#s', $html, $loading );

    expect( $loading[1] ?? '' )->not->toBe( '' )
        ->and( substr_count( $loading[1], 'apui-skeleton' ) )->toBe( 3 )
        ->and( $loading[1] )->toContain( 'class="ec-stripe__skeletons" aria-hidden="true"' )
        ->toMatch( '#<span[^>]*apui-loading[^>]*role="status"[^>]*data-overlay#s' )
        ->toContain( 'Loading the payment form…' )
        ->and( $html )->toContain( 'class="ec-stripe__field" data-loading' );
} );

it( 'builds Stripe\'s appearance from the design-system tokens, not DaisyUI', function (): void {
    $script = implode( "\n", (array) ( Livewire::test( StripePaymentElement::class, stripeDriverProps() )->effects['scripts'] ?? [] ) );

    expect( $script )->toContain( '--apui-color-primary' )
        ->toContain( '--apui-surface-default' )
        ->toContain( '--apui-text-default' )
        ->toContain( '--apui-color-error-text' )
        ->toContain( '--apui-radius-field' )
        ->toContain( '--apui-border-default' )
        ->toContain( '--apui-focus-ring-color' )
        ->toContain( "'.Input:focus'" )
        ->toContain( '0 0 0 ${ spread }px ${ ring }' )
        ->toContain( "px solid transparent`" )
        ->not->toContain( "outline: 'none'" )
        ->toContain( '$wire.__instance?.addCleanup?.( teardown )' )
        ->not->toContain( 'calc(${' )
        ->toContain( 'elements.update( { appearance: appearance() } )' )
        ->not->toContain( '--color-primary' )
        ->not->toContain( '--color-base-100' )
        ->not->toContain( '--radius-field\'' );
} );

it( 'leaves no DaisyUI or Tailwind classes', function ( array $config ): void {
    expect( classTokens( Livewire::test( StripePaymentElement::class, stripeDriverProps( $config ) )->html() ) )
        ->not->toContain( 'flex', 'flex-col', 'gap-3', 'text-sm', 'text-xs', 'text-base-content/70', 'empty:hidden' );
} )->with( [ 'available' => [ [] ], 'unavailable' => [ [ 'publishable_key' => '' ] ] ] );

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

    expect( array_keys( $component->instance()->all() ) )->toEqualCanonicalizing( [ 'config', 'gateway', 'gatewayLabel', 'reference', 'token', 'pageUrl' ] );
} );

it( 'keeps its props out of the browser\'s reach', function ( string $property ): void {
    Livewire::test( StripePaymentElement::class, stripeDriverProps() )->set( $property, 'pi_evil' );
} )->with( [ 'reference', 'config.client_secret', 'gateway', 'pageUrl', 'token' ] )->throws( CannotUpdateLockedPropertyException::class );

it( 'returns to the page it was mounted on when the return route is off, even after an update', function (): void {
    stripeLikeGateway();

    $component = Livewire::test( StripePaymentElement::class, stripeDriverProps() );
    $mountedOn = (string) $component->get( 'pageUrl' );

    // Only the return route goes; Livewire's update route has to stay.
    $routes = new Illuminate\Routing\RouteCollection();

    foreach ( app( 'router' )->getRoutes() as $route ) {
        if ( 'artisanpack.ecommerce.storefront.checkout.return' !== $route->getName() ) {
            $routes->add( $route );
        }
    }

    app( 'router' )->setRoutes( $routes );

    $component->call( '$refresh' );

    expect( $mountedOn )->not->toBe( '' )
        ->and( $mountedOn )->not->toEndWith( '/livewire/update' )
        ->and( $component->instance()->render()->getData()['stripe']['returnUrl'] )->toBe( $mountedOn );
} );
