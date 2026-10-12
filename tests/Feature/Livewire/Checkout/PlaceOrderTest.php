<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Registries\FraudProviderRegistry;
use ArtisanPackUI\Ecommerce\Services\PaymentReconciler;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;
use ArtisanPackUI\Ecommerce\ValueObjects\WebhookResult;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutMarketingConsent;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutPlacement;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Fixtures\Actions\FailingCreateCustomerAccount;
use Tests\Fixtures\Fraud\FakeFraudProvider;
use Tests\Fixtures\Livewire\FakePaymentDriver;
use Tests\Fixtures\User;

/**
 * The URL a component redirected to.
 */
function placedRedirect( Testable $component ): string
{
    return (string) ( $component->effects['redirect'] ?? '' );
}

/**
 * Places an order whose payment the bank asks to confirm again, so it
 * ends up placed but unpaid.
 */
function placeChallengedOrder( Tests\Fixtures\Gateways\FakeGateway $gateway ): Testable
{
    payAtCheckoutThen( $gateway, PaymentSession::STATUS_REQUIRES_ACTION );

    return payAtCheckout( checkoutAtPaymentStep( $gateway ), $gateway );
}

beforeEach( function (): void {
    checkoutShipping( [ 'Standard' => 500 ] );

    $this->gateway = checkoutGateway();
    $this->cart    = checkoutCart( [ makeProduct( 2000, [ 'name' => 'Mug' ] ) ] );

    $this->placing = 0;
    addAction( 'ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder', function (): void {
        $this->placing++;
    } );
} );

it( 'shows the lines, addresses, shipping, totals, note, and "Continue to payment" on review', function (): void {
    checkoutAtReview( $this->gateway )
        ->assertSeeHtml( 'data-checkout-review-form' )
        ->assertSeeHtml( 'data-checkout-review-lines' )
        ->assertSee( 'Mug' )
        ->assertSee( '1 Main Street' )
        ->assertSee( 'Standard' )
        ->assertSeeHtml( 'data-cart-tax' )
        ->assertSeeHtml( 'data-checkout-order-note' )
        ->assertSeeHtml( 'data-checkout-review-submit' )
        ->assertSee( 'Continue to payment' )
        ->assertSee( 'You\'ll pay $25.00.' )
        ->assertDontSeeHtml( 'data-checkout-place-order' )
        ->assertDontSeeHtml( 'data-checkout-terms' );

    expect( Order::query()->count() )->toBe( 0 );
} );

it( 'places the order when the payment is confirmed, with the note, terms, and account entered before paying', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url', 'https://shop.test/terms' );
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );

    $component = checkoutAtReview( $this->gateway, 'grace@example.test' )
        ->set( 'orderNote', 'Ring twice' )
        ->set( 'acceptTerms', true )
        ->set( 'createAccount', true )
        ->set( 'password', 'correct-horse-battery' )
        ->call( 'saveReview' )
        ->assertHasNoErrors()
        ->assertSet( 'step', 'payment' )
        ->assertSet( 'password', '' );

    expect( Order::query()->count() )->toBe( 0 )
        ->and( json_encode( session( Index::REVIEW_SESSION_KEY ) ) )->not->toContain( 'correct-horse-battery' );

    payAtCheckout( $component, $this->gateway )->assertHasNoErrors();

    $order = Order::query()->sole();
    $user  = User::query()->where( 'email', 'grace@example.test' )->sole();

    expect( $order )
        ->customer_note->toBe( 'Ring twice' )
        ->payment_status->toBe( 'paid' )
        ->and( placedRedirect( $component ) )->toBe( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) )
        ->and( password_verify( 'correct-horse-battery', $user->password ) )->toBeTrue()
        ->and( auth()->id() )->toBe( $user->id )
        ->and( $this->placing )->toBe( 1 )
        ->and( $this->gateway->captures )->toBe( 1 )
        ->and( session()->has( Index::REVIEW_SESSION_KEY ) )->toBeFalse();
} );

it( 'places the order, and sends a guest to the signed confirmation', function (): void {
    $component = checkoutAtReview( $this->gateway )
        ->set( 'orderNote', '  <b>Leave it</b> by the door  ' )
        ->call( 'saveReview' );

    payAtCheckout( $component, $this->gateway )->assertHasNoErrors();

    $order = Order::query()->sole();
    $url   = placedRedirect( $component );

    parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );

    expect( $order )
        ->email->toBe( 'ada@example.test' )
        ->payment_status->toBe( 'paid' )
        ->customer_note->toBe( 'Leave it by the door' )
        ->total_amount->toBe( 2500 )
        ->and( $this->cart->refresh()->completed_order_id )->toBe( $order->id )
        ->and( $url )->toStartWith( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) )
        ->and( OrderViewToken::verify( (string) ( $query['token'] ?? '' ) )?->id )->toBe( $order->id )
        ->and( $this->placing )->toBe( 1 )
        ->and( $this->gateway->captures )->toBe( 1 )
        ->and( session()->has( CheckoutPlacement::SESSION_KEY ) )->toBeFalse();
} );

it( 'places a free order with "Place order" on the payment step', function (): void {
    $freebie = ArtisanPackUI\Ecommerce\Models\Product::factory()->digital()->create( [ 'name' => 'Free guide' ] );
    ArtisanPackUI\Ecommerce\Models\ProductPrice::factory()->forPriceable( $freebie )->create( [ 'currency' => 'USD', 'price_amount' => 0 ] );

    app( StorefrontCart::class )->refresh();
    Cart::query()->delete();
    checkoutCart( [ $freebie ] );

    $component = Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->set( 'billing', checkoutAddress() )
        ->call( 'saveAddress' )
        ->set( 'orderNote', 'Thanks' )
        ->call( 'saveReview' )
        ->assertSet( 'step', 'payment' );

    $component->call( 'placeOrder', placeOrderToken( $component ) )->assertHasNoErrors();

    expect( Order::query()->sole() )->payment_status->toBe( 'paid' )->customer_note->toBe( 'Thanks' )
        ->and( placedRedirect( $component ) )->toContain( 'token=' );
} );

it( 'won\'t place a paid order with "Place order" before the payment is confirmed', function (): void {
    $component = checkoutAtPaymentStep( $this->gateway );

    $component->call( 'placeOrder', placeOrderToken( $component ) )
        ->assertHasErrors( 'payment' )
        ->assertSee( 'Confirm your payment to place your order.' );

    expect( Order::query()->count() )->toBe( 0 );
} );

it( 'ignores its own cart update once the order is placed, so the shopper still reaches the confirmation', function (): void {
    $component = payAtCheckout( checkoutAtPaymentStep( $this->gateway ), $this->gateway )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated' )
        ->assertSet( 'orderPlaced', true );

    expect( placedRedirect( $component ) )->toStartWith( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => Order::query()->sole()->id ] ) );

    // The cart is now empty. Without the guard, this listener redirected
    // to it, which on Livewire 3 could beat the confirmation redirect.
    $component->dispatch( 'ecommerce-cart-updated' )
        ->assertNoRedirect()
        ->assertSet( 'unavailable', null );
} );

it( 'places one order when the payment is confirmed twice with the same token', function (): void {
    $component = checkoutAtPaymentStep( $this->gateway );
    $token     = placeOrderToken( $component );

    payAtCheckout( $component, $this->gateway, $token );
    $component->dispatch( 'payment-confirmed', reference: 'fake_ps_1', gateway: 'fake', token: $token );

    expect( Order::query()->count() )->toBe( 1 )
        ->and( $this->placing )->toBe( 1 )
        ->and( $this->gateway->captures )->toBe( 1 )
        ->and( placedRedirect( $component ) )->toStartWith( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => Order::query()->sole()->id ] ) );

    // The cart is now an order: a fresh checkout goes back to the cart.
    Livewire::test( Index::class )->assertRedirect( route( 'artisanpack.ecommerce.storefront.cart' ) );
} );

it( 'refuses a token that wasn\'t minted for this checkout, then places the order with "Place order"', function (): void {
    $component = payAtCheckout( checkoutAtPaymentStep( $this->gateway ), $this->gateway, 'not-a-token' );

    expect( Order::query()->count() )->toBe( 0 )
        ->and( $this->placing )->toBe( 0 )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'This page has expired.' );

    $component->assertSeeHtml( 'data-checkout-payment-confirmed' )
        ->call( 'placeOrder', placeOrderToken( $component ) )
        ->assertSet( 'orderPlaced', true );

    expect( Order::query()->count() )->toBe( 1 )->and( $this->gateway->captures )->toBe( 1 );
} );

it( 'checks the review form before moving on to payment', function ( Closure $arrange, string $field, string $message ): void {
    $component = checkoutAtReview( $this->gateway );

    $arrange( $component );

    $component->call( 'saveReview' )
        ->assertHasErrors( $field )
        ->assertSee( $message )
        ->assertSeeHtml( 'data-checkout-errors' )
        ->assertSet( 'step', 'review' );

    expect( session()->has( Index::REVIEW_SESSION_KEY ) )->toBeFalse();
} )->with( [
    'note too long'    => [ fn ( Testable $component ) => $component->set( 'orderNote', str_repeat( 'a', 2_001 ) ), 'orderNote', 'Keep your note to 2000 characters or fewer.' ],
    'terms unaccepted' => [ function ( Testable $component ): void {
        config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url', '/terms' );
    }, 'acceptTerms', 'Accept the terms and conditions to place your order.' ],
] );

it( 'shows the terms checkbox and moves on once they are accepted', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url', 'https://shop.test/terms' );

    checkoutAtReview( $this->gateway )
        ->assertSeeHtml( 'data-checkout-terms' )
        ->assertSeeHtml( 'href="https://shop.test/terms"' )
        ->set( 'acceptTerms', true )
        ->call( 'saveReview' )
        ->assertHasNoErrors()
        ->assertSet( 'step', 'payment' );
} );

it( 'ignores a terms link that isn\'t http(s) or on the site', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url', 'javascript:alert(1)' );

    checkoutAtReview( $this->gateway )->assertDontSeeHtml( 'data-checkout-terms' );
} );

it( 'won\'t place an order from a step before payment', function (): void {
    $component = checkoutAtPaymentStep( $this->gateway );
    $token     = placeOrderToken( $component );

    $component->call( 'goTo', 'contact' );

    payAtCheckout( $component, $this->gateway, $token )->assertHasErrors( 'review' );

    expect( Order::query()->count() )->toBe( 0 );
} );

it( 'won\'t place an order the shopper didn\'t review', function (): void {
    $component = checkoutAtPaymentStep( $this->gateway );

    session()->forget( Index::REVIEW_SESSION_KEY );

    payAtCheckout( $component, $this->gateway )
        ->assertHasErrors( 'review' )
        ->assertSet( 'step', 'review' );

    expect( Order::query()->count() )->toBe( 0 )->and( $this->placing )->toBe( 0 );
} );

it( 'creates an account for a guest who asks for one, and signs them in', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );

    $component = checkoutAtReview( $this->gateway, 'grace@example.test' )
        ->assertSeeHtml( 'data-checkout-account' )
        ->set( 'createAccount', true )
        ->assertSeeHtml( 'data-checkout-password' )
        ->set( 'password', 'correct-horse-battery' )
        ->call( 'saveReview' );

    payAtCheckout( $component, $this->gateway )->assertHasNoErrors();

    $user     = User::query()->where( 'email', 'grace@example.test' )->sole();
    $customer = Customer::forUser( $user );
    $order    = Order::query()->sole();

    expect( auth()->id() )->toBe( $user->id )
        ->and( $user->name )->toBe( 'Ada Lovelace' )
        ->and( password_verify( 'correct-horse-battery', $user->password ) )->toBeTrue()
        ->and( $order->customer_id )->toBe( $customer->id )
        ->and( $order->is_claimed )->toBeTrue()
        ->and( placedRedirect( $component ) )->toBe( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) );
} );

it( 'keeps the reviewed password when the shopper reopens review without retyping it', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );

    $component = checkoutAtReview( $this->gateway, 'grace@example.test' )
        ->set( 'createAccount', true )
        ->set( 'password', 'correct-horse-battery' )
        ->call( 'saveReview' )
        ->call( 'goTo', 'review' )
        ->assertSet( 'createAccount', true )
        ->call( 'saveReview' )
        ->assertHasNoErrors()
        ->assertSet( 'step', 'payment' );

    payAtCheckout( $component, $this->gateway );

    expect( password_verify( 'correct-horse-battery', User::query()->where( 'email', 'grace@example.test' )->sole()->password ) )->toBeTrue();
} );

it( 'refuses a weak password or an email that already has an account', function ( Closure $arrange, string $message ): void {
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );

    $component = checkoutAtReview( $this->gateway, 'grace@example.test' )->set( 'createAccount', true );

    $arrange( $component );

    $component->call( 'saveReview' )
        ->assertHasErrors( 'password' )
        ->assertSee( $message )
        ->assertSet( 'step', 'review' );

    expect( Order::query()->count() )->toBe( 0 );
} )->with( [
    'no password'    => [ fn ( Testable $component ) => $component->set( 'password', '' ), 'Choose a password for your account.' ],
    'short password' => [ fn ( Testable $component ) => $component->set( 'password', 'short' ), 'password' ],
    'email taken'    => [ function ( Testable $component ): void {
        makeUser( [ 'email' => 'Grace@Example.test' ] );
        $component->set( 'password', 'correct-horse-battery' );
    }, 'There\'s already an account for grace@example.test.' ],
] );

it( 'doesn\'t offer an account when the store doesn\'t, or to a signed-in shopper', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', false );

    checkoutAtReview( $this->gateway )->assertDontSeeHtml( 'data-checkout-account' );
} );

it( 'still places the order when the account can\'t be created', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );
    config()->set( 'artisanpack.ecommerce-storefront-livewire.auth.create_account_action', FailingCreateCustomerAccount::class );

    $component = checkoutAtReview( $this->gateway, 'grace@example.test' )->set( 'createAccount', true )->set( 'password', 'correct-horse-battery' )->call( 'saveReview' );

    payAtCheckout( $component, $this->gateway );

    expect( Order::query()->count() )->toBe( 1 )
        ->and( auth()->check() )->toBeFalse()
        ->and( placedRedirect( $component ) )->toContain( 'token=' );
} );

it( 'creates the account with the password checked at review, not one sent later', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );

    $component = checkoutAtReview( $this->gateway, 'grace@example.test' )
        ->set( 'createAccount', true )
        ->set( 'password', 'correct-horse-battery' )
        ->call( 'saveReview' );

    payAtCheckoutThen( $this->gateway, PaymentSession::STATUS_REQUIRES_ACTION );
    payAtCheckout( $component, $this->gateway );

    $component->assertSet( 'placement.order', Order::query()->sole()->id )
        ->set( 'password', 'a' );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_AUTHORIZED );
    $component->dispatch( 'payment-confirmed', reference: 'fake_ps_1', gateway: 'fake' );

    expect( password_verify( 'correct-horse-battery', User::query()->where( 'email', 'grace@example.test' )->sole()->password ) )->toBeTrue();
} );

it( 'sends the shopper back to payment when the payment no longer matches', function (): void {
    $component = checkoutAtPaymentStep( $this->gateway );

    addAction( 'ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder', function (): void {
        Cart::query()->whereKey( $this->cart->id )->update( [ 'payment_reference' => 'fake_ps_other' ] );
        $this->gateway->sessions['fake_ps_other'] = $this->gateway->sessions['fake_ps_1'];
    } );

    payAtCheckout( $component, $this->gateway )->assertHasErrors();

    expect( Order::query()->count() )->toBe( 0 )
        ->and( $component->get( 'step' ) )->toBe( 'payment' )
        ->and( $component->get( 'confirmedPayment' ) )->toBeNull();
} );

it( 'shows a generic message when placing the order fails unexpectedly', function (): void {
    addAction( 'ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder', static function (): void {
        throw new RuntimeException( 'A listener broke.' );
    } );

    payAtCheckout( checkoutAtPaymentStep( $this->gateway ), $this->gateway )
        ->assertHasErrors( 'payment' )
        ->assertSee( 'We couldn\'t place your order. Try again in a moment.' )
        ->assertSet( 'step', 'payment' );

    expect( Order::query()->count() )->toBe( 0 );
} );

it( 'shows the payment again when the bank needs it confirmed, then finishes the order', function (): void {
    $component = placeChallengedOrder( $this->gateway );

    $order = Order::query()->sole();

    $component->assertSet( 'placement.order', $order->id )
        ->assertSet( 'step', 'payment' )
        ->assertSeeHtml( 'data-checkout-placement="' . $order->id . '"' )
        ->assertSee( 'Your bank needs you to confirm this payment.' )
        ->assertSeeLivewire( FakePaymentDriver::class )
        ->assertSeeHtml( 'data-fake-driver="fake_ps_1"' );

    expect( $order->payment_status )->not->toBe( 'paid' )
        ->and( session( CheckoutPlacement::SESSION_KEY ) )->toBe( [ 'cart' => $this->cart->id, 'order' => $order->id ] );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_AUTHORIZED );

    $component->dispatch( 'payment-confirmed', reference: 'fake_ps_1', gateway: 'fake' );

    expect( $order->refresh()->payment_status )->toBe( 'paid' )
        ->and( placedRedirect( $component ) )->toStartWith( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) )
        ->and( Order::query()->count() )->toBe( 1 )
        ->and( session()->has( CheckoutPlacement::SESSION_KEY ) )->toBeFalse();
} );

it( 'reopens the payment with the gateway\'s message when the payment is declined', function (): void {
    $this->gateway->captureOutcome = 'declined';

    payAtCheckout( checkoutAtPaymentStep( $this->gateway ), $this->gateway )
        ->assertSet( 'step', 'payment' )
        ->assertSee( 'Your card was declined.' )
        ->assertSeeHtml( 'data-checkout-payment-driver="fake-driver"' );

    expect( Order::query()->sole()->payment_status )->not->toBe( 'paid' );
} );

it( 'picks the unpaid order up again after a reload', function (): void {
    placeChallengedOrder( $this->gateway );

    $order = Order::query()->sole();

    Livewire::test( Index::class )
        ->assertSet( 'placement.order', $order->id )
        ->assertSee( 'Finish paying for order ' . $order->order_number )
        ->assertSee( 'its payment isn\'t finished' )
        ->assertSeeHtml( 'data-fake-driver="fake_ps_1"' );
} );

it( 'doesn\'t send an unpaid order without a payment session to the confirmation', function (): void {
    placeChallengedOrder( $this->gateway );

    $order = Order::query()->sole();
    $order->forceFill( [ 'payment_reference' => null ] )->save();

    Livewire::test( Index::class )
        ->assertNoRedirect()
        ->assertSet( 'placement.order', $order->id )
        ->assertSeeHtml( 'data-checkout-placement-unavailable' );

    expect( $order->refresh()->payment_status )->not->toBe( 'paid' );
} );

it( 'finishes the order when the shopper returns from the bank', function (): void {
    placeChallengedOrder( $this->gateway );

    $order = Order::query()->sole();

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_AUTHORIZED );

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return', [ 'payment_intent' => 'fake_ps_1' ] ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout' ) );

    Livewire::test( Index::class )->assertRedirectContains( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) );

    expect( $order->refresh()->payment_status )->toBe( 'paid' );
} );

it( 'sends a returning shopper whose order is already paid to the confirmation', function (): void {
    placeChallengedOrder( $this->gateway );

    $order = Order::query()->sole();
    $order->forceFill( [ 'payment_status' => 'paid' ] )->save();

    $response = $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return' ) );

    expect( (string) $response->headers->get( 'Location' ) )->toStartWith( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) )
        ->and( session()->has( CheckoutPlacement::SESSION_KEY ) )->toBeFalse();
} );

it( 'places the order when the shopper comes back from a redirect payment', function (): void {
    $component = checkoutAtReview( $this->gateway )->set( 'orderNote', 'Redirected' )->call( 'saveReview' );

    expect( $component->get( 'payment.reference' ) )->toBe( 'fake_ps_1' );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_SUCCEEDED );

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return', [ 'payment_intent' => 'fake_ps_1' ] ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout', [ 'step' => 'payment' ] ) );

    $checkout = Livewire::withQueryParams( [ 'step' => 'payment' ] )->test( Index::class );

    $checkout->assertRedirectContains( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => Order::query()->sole()->id ] ) );

    expect( Order::query()->sole() )->payment_status->toBe( 'paid' )->customer_note->toBe( 'Redirected' )
        ->and( $this->placing )->toBe( 1 );
} );

it( 'sends the shopper to the confirmation when the webhook placed the order first', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );

    $component = checkoutAtReview( $this->gateway, 'grace@example.test' )
        ->set( 'createAccount', true )
        ->set( 'password', 'correct-horse-battery' )
        ->call( 'saveReview' );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_SUCCEEDED );

    expect( app( PaymentReconciler::class )->reconcile( 'fake', 'fake_ps_1', WebhookResult::OUTCOME_SUCCEEDED ) )->toBe( PaymentReconciler::FINALIZED );

    $order = Order::query()->sole();

    app( StorefrontCart::class )->refresh();

    $component->dispatch( 'payment-confirmed', reference: 'fake_ps_1', gateway: 'fake', token: placeOrderToken( $component ) )
        ->assertSet( 'orderPlaced', true );

    expect( placedRedirect( $component ) )->toBe( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) )
        ->and( $order->refresh()->payment_status )->toBe( 'paid' )
        ->and( Order::query()->count() )->toBe( 1 )
        ->and( $this->placing )->toBe( 0 )
        ->and( User::query()->where( 'email', 'grace@example.test' )->exists() )->toBeTrue();
} );

it( 'sends a shopper who reloads after the webhook placed the order to the confirmation', function (): void {
    checkoutAtPaymentStep( $this->gateway );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_SUCCEEDED );
    app( PaymentReconciler::class )->reconcile( 'fake', 'fake_ps_1', WebhookResult::OUTCOME_SUCCEEDED );

    app( StorefrontCart::class )->refresh();

    Livewire::test( Index::class )
        ->assertRedirectContains( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => Order::query()->sole()->id ] ) );
} );

it( 'sends a shopper whose payment is held for review to the confirmation', function (): void {
    app( FraudProviderRegistry::class )->register( 'fake-fraud', new FakeFraudProvider( 'challenge' ) );
    config()->set( 'artisanpack.ecommerce.fraud.provider', 'fake-fraud' );

    $component = payAtCheckout( checkoutAtPaymentStep( $this->gateway ), $this->gateway );

    $order = Order::query()->sole();

    $component->assertDontSeeHtml( 'data-checkout-placement' )
        ->assertSet( 'placement', null );

    expect( placedRedirect( $component ) )->toStartWith( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) )
        ->and( $order->payment_status )->toBe( 'pending' )
        ->and( session()->has( CheckoutPlacement::SESSION_KEY ) )->toBeFalse()
        ->and( session( ToastPayload::SESSION_KEY )['toast']['description'] ?? null )->toBe( e( 'We\'re reviewing your order and will email you shortly.' ) );

    // Coming back to checkout doesn't ask for the payment again.
    Livewire::test( Index::class )->assertDontSeeHtml( 'data-checkout-placement' );
} );

it( 'sends a shopper returning to a held order to the confirmation', function (): void {
    app( FraudProviderRegistry::class )->register( 'fake-fraud', new FakeFraudProvider( 'challenge' ) );
    config()->set( 'artisanpack.ecommerce.fraud.provider', 'fake-fraud' );

    payAtCheckout( checkoutAtPaymentStep( $this->gateway ), $this->gateway );

    $order = Order::query()->sole();

    CheckoutPlacement::remember( $this->cart->refresh(), $order );

    Livewire::test( Index::class )
        ->assertRedirectContains( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) )
        ->assertDontSeeHtml( 'data-checkout-placement' );

    CheckoutPlacement::remember( $this->cart->refresh(), $order );

    expect( (string) $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return' ) )->headers->get( 'Location' ) )
        ->toStartWith( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) );
} );

it( 'shows a generic message when fraud screening blocks the payment', function (): void {
    app( FraudProviderRegistry::class )->register( 'fake-fraud', new FakeFraudProvider( 'block' ) );
    config()->set( 'artisanpack.ecommerce.fraud.provider', 'fake-fraud' );

    payAtCheckout( checkoutAtPaymentStep( $this->gateway ), $this->gateway )
        ->assertSet( 'unavailable', 'We couldn\'t process your order. Please contact the store for help.' )
        ->assertDontSee( 'card_testing' );

    expect( session()->has( CheckoutPlacement::SESSION_KEY ) )->toBeFalse()
        ->and( $this->gateway->captures )->toBe( 0 );
} );

it( 'says the order is placed when there is no confirmation page', function (): void {
    $component = checkoutAtPaymentStep( $this->gateway );

    app( 'router' )->getRoutes()->getByName( 'artisanpack.ecommerce.storefront.confirmation' )->name( 'renamed-confirmation' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    payAtCheckout( $component, $this->gateway );
    $order = Order::query()->sole();

    $component->assertSet( 'placedOrder', $order->order_number )
        ->assertSeeHtml( 'data-checkout-placed' )
        ->assertSee( 'Thank you for your order' );
} );

it( 'checks out a new cart instead of an unpaid order the shopper left', function (): void {
    placeChallengedOrder( $this->gateway );

    expect( session()->has( CheckoutPlacement::SESSION_KEY ) )->toBeTrue();

    app( StorefrontCart::class )->refresh();
    checkoutCart( [ makeProduct( 900, [ 'name' => 'Teapot' ] ) ] );

    Livewire::test( Index::class )
        ->assertSet( 'placement', null )
        ->assertSet( 'step', 'contact' )
        ->assertSee( 'Teapot' );
} );

it( 'counts placing an order against the finalize limit', function (): void {
    $limits                                   = config( 'artisanpack.ecommerce.rate_limits' );
    $limits['checkout.finalize']['per_ip']    = 1;
    $limits['checkout.finalize']['per_cart']  = 1;
    config()->set( 'artisanpack.ecommerce.rate_limits', $limits );

    $component = payAtCheckout( checkoutAtPaymentStep( $this->gateway ), $this->gateway, 'not-a-token' );

    $component->call( 'placeOrder', placeOrderToken( $component ) );

    expect( Order::query()->count() )->toBe( 0 )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Too many attempts' );
} );

it( 'records marketing consent with the order and on the customer', function (): void {
    $component = Livewire::test( Index::class )
        ->set( 'email', 'grace@example.test' )
        ->set( 'marketingConsent', true )
        ->call( 'saveContact' )
        ->set( 'shipping', checkoutAddress() )
        ->call( 'saveAddress' );

    $component->set( 'shippingRate', (string) $component->get( 'rates' )[0]['id'] )->call( 'saveShipping' )->call( 'saveReview' );

    payAtCheckout( $component, $this->gateway );

    $order    = Order::query()->sole();
    $customer = Customer::query()->where( 'email', 'grace@example.test' )->sole();

    expect( $order->meta[ CheckoutMarketingConsent::ORDER_META_KEY ] )
        ->toMatchArray( [ 'given' => true, 'source' => 'storefront-checkout', 'customer_id' => $customer->id, 'customer_created' => true ] )
        ->and( $order->meta[ CheckoutMarketingConsent::ORDER_META_KEY ]['at'] )->toBeString()
        ->and( $customer->accepts_marketing )->toBeTrue()
        ->and( $customer->accepts_marketing_at )->not->toBeNull();
} );

it( 'records nothing when the shopper didn\'t opt in', function (): void {
    payAtCheckout( checkoutAtPaymentStep( $this->gateway, 'grace@example.test' ), $this->gateway );

    expect( Order::query()->sole()->meta )->not->toHaveKey( CheckoutMarketingConsent::ORDER_META_KEY )
        ->and( Customer::query()->where( 'email', 'grace@example.test' )->exists() )->toBeFalse();
} );

it( 'records marketing consent for an order the webhook placed', function (): void {
    $component = Livewire::test( Index::class )
        ->set( 'email', 'grace@example.test' )
        ->set( 'marketingConsent', true )
        ->call( 'saveContact' )
        ->set( 'shipping', checkoutAddress() )
        ->call( 'saveAddress' );

    $component->set( 'shippingRate', (string) $component->get( 'rates' )[0]['id'] )->call( 'saveShipping' )->call( 'saveReview' );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_SUCCEEDED );
    app( PaymentReconciler::class )->reconcile( 'fake', 'fake_ps_1', WebhookResult::OUTCOME_SUCCEEDED );

    expect( Order::query()->sole()->meta[ CheckoutMarketingConsent::ORDER_META_KEY ]['given'] ?? null )->toBeTrue()
        ->and( Customer::query()->where( 'email', 'grace@example.test' )->sole()->accepts_marketing )->toBeTrue();
} );

it( 'leaves the preferences of a customer with an account alone for a guest checkout', function (): void {
    $owner = makeUser( [ 'email' => 'grace@example.test' ] );
    Customer::factory()->create( [ 'email' => 'grace@example.test', 'user_id' => $owner->id, 'accepts_marketing' => false ] );

    $component = Livewire::test( Index::class )
        ->set( 'email', 'grace@example.test' )
        ->set( 'marketingConsent', true )
        ->call( 'saveContact' )
        ->set( 'shipping', checkoutAddress() )
        ->call( 'saveAddress' );

    $component->set( 'shippingRate', (string) $component->get( 'rates' )[0]['id'] )->call( 'saveShipping' )->call( 'saveReview' );

    payAtCheckout( $component, $this->gateway );

    expect( Customer::query()->where( 'email', 'grace@example.test' )->sole()->accepts_marketing )->toBeFalse()
        ->and( Order::query()->sole()->meta[ CheckoutMarketingConsent::ORDER_META_KEY ]['customer_id'] )->toBeNull();
} );

it( 'attaches the order to the account when the guest also opted in to marketing', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );

    $component = Livewire::test( Index::class )
        ->set( 'email', 'grace@example.test' )
        ->set( 'marketingConsent', true )
        ->call( 'saveContact' )
        ->set( 'shipping', checkoutAddress() )
        ->call( 'saveAddress' );

    $component->set( 'shippingRate', (string) $component->get( 'rates' )[0]['id'] )
        ->call( 'saveShipping' )
        ->set( 'createAccount', true )
        ->set( 'password', 'correct-horse-battery' )
        ->call( 'saveReview' );

    payAtCheckout( $component, $this->gateway );

    $customer = Customer::forUser( User::query()->where( 'email', 'grace@example.test' )->sole() );

    expect( $customer )->not->toBeNull()
        ->and( $customer->accepts_marketing )->toBeTrue()
        ->and( Order::query()->sole()->customer_id )->toBe( $customer->id );
} );

it( 'finishes a placed order from the payment return even after the shopper started a new cart', function (): void {
    placeChallengedOrder( $this->gateway );

    $order = Order::query()->sole();

    app( StorefrontCart::class )->refresh();
    checkoutCart( [ makeProduct( 900, [ 'name' => 'Teapot' ] ) ] );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_AUTHORIZED );

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return', [ 'payment_intent' => 'fake_ps_1' ] ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout' ) )
        ->assertSessionHas( Index::CONFIRMED_PAYMENT_SESSION_KEY, [ 'reference' => 'fake_ps_1', 'total' => (int) $order->total_amount ] );
} );
