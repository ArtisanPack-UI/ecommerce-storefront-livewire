<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Registries\FraudProviderRegistry;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutPlacement;
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

beforeEach( function (): void {
    checkoutShipping( [ 'Standard' => 500 ] );

    $this->gateway = checkoutGateway();
    $this->cart    = checkoutCart( [ makeProduct( 2000, [ 'name' => 'Mug' ] ) ] );

    $this->placing = 0;
    addAction( 'ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder', function (): void {
        $this->placing++;
    } );
} );

it( 'shows the lines, addresses, method, totals, note, and "Place order" on review', function (): void {
    checkoutAtReview( $this->gateway )
        ->assertSeeHtml( 'data-checkout-review-form' )
        ->assertSeeHtml( 'data-checkout-review-lines' )
        ->assertSee( 'Mug' )
        ->assertSee( '1 Main Street' )
        ->assertSee( 'Standard' )
        ->assertSee( 'Fake card' )
        ->assertSeeHtml( 'data-cart-tax' )
        ->assertSeeHtml( 'data-checkout-order-note' )
        ->assertSeeHtml( 'data-checkout-place-order' )
        ->assertSee( 'You\'ll pay $25.00.' )
        ->assertDontSeeHtml( 'data-checkout-terms' );

    expect( placeOrderToken( checkoutAtReview( $this->gateway ) ) )->not->toBe( '' );
} );

it( 'places the order, and sends a guest to the signed confirmation', function (): void {
    $component = checkoutAtReview( $this->gateway )->set( 'orderNote', '  <b>Leave it</b> by the door  ' );

    $component->call( 'placeOrder', placeOrderToken( $component ) )->assertHasNoErrors();

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

it( 'places one order when "Place order" is sent twice with the same token', function (): void {
    $component = checkoutAtReview( $this->gateway );
    $token     = placeOrderToken( $component );

    $component->call( 'placeOrder', $token );
    $first = placedRedirect( $component );

    expect( Order::query()->count() )->toBe( 1 )
        ->and( $this->placing )->toBe( 1 )
        ->and( $this->gateway->captures )->toBe( 1 )
        ->and( $first )->not->toBe( '' );

    // The cart is now an order: a fresh checkout goes back to the cart.
    Livewire::test( Index::class )->assertRedirect( route( 'artisanpack.ecommerce.storefront.cart' ) );
} );

it( 'replays the first result for a repeat of the same token', function (): void {
    $component = checkoutAtReview( $this->gateway );
    $token     = placeOrderToken( $component );

    $component->call( 'placeOrder', $token )->call( 'placeOrder', $token );

    expect( Order::query()->count() )->toBe( 1 )->and( $this->placing )->toBe( 1 );
} );

it( 'refuses a token that wasn\'t minted for this checkout', function (): void {
    $component = checkoutAtReview( $this->gateway )->call( 'placeOrder', 'not-a-token' );

    expect( Order::query()->count() )->toBe( 0 )
        ->and( $this->placing )->toBe( 0 )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'This page has expired.' );
} );

it( 'checks the review form before placing the order', function ( Closure $arrange, string $field, string $message ): void {
    $component = checkoutAtReview( $this->gateway );

    $arrange( $component );

    $component->call( 'placeOrder', placeOrderToken( $component ) )
        ->assertHasErrors( $field )
        ->assertSee( $message )
        ->assertSeeHtml( 'data-checkout-errors' );

    expect( Order::query()->count() )->toBe( 0 );
} )->with( [
    'note too long'    => [ fn ( Testable $component ) => $component->set( 'orderNote', str_repeat( 'a', 2_001 ) ), 'orderNote', 'Keep your note to 2000 characters or fewer.' ],
    'terms unaccepted' => [ function ( Testable $component ): void {
        config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url', '/terms' );
    }, 'acceptTerms', 'Accept the terms and conditions to place your order.' ],
] );

it( 'shows the terms checkbox and places the order once they are accepted', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url', 'https://shop.test/terms' );

    $component = checkoutAtReview( $this->gateway )
        ->assertSeeHtml( 'data-checkout-terms' )
        ->assertSeeHtml( 'href="https://shop.test/terms"' );

    $component->set( 'acceptTerms', true )->call( 'placeOrder', placeOrderToken( $component ) )->assertHasNoErrors();

    expect( Order::query()->count() )->toBe( 1 );
} );

it( 'ignores a terms link that isn\'t http(s) or on the site', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url', 'javascript:alert(1)' );

    checkoutAtReview( $this->gateway )->assertDontSeeHtml( 'data-checkout-terms' );
} );

it( 'won\'t place an order from a step before review', function (): void {
    $component = checkoutAtReview( $this->gateway );
    $token     = placeOrderToken( $component );

    $component->call( 'goTo', 'contact' )
        ->call( 'placeOrder', $token )
        ->assertHasErrors( 'review' );

    expect( Order::query()->count() )->toBe( 0 );
} );

it( 'creates an account for a guest who asks for one, and signs them in', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );

    $component = checkoutAtReview( $this->gateway, 'grace@example.test' )
        ->assertSeeHtml( 'data-checkout-account' )
        ->set( 'createAccount', true )
        ->assertSeeHtml( 'data-checkout-password' )
        ->set( 'password', 'correct-horse-battery' );

    $component->call( 'placeOrder', placeOrderToken( $component ) )->assertHasNoErrors();

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

it( 'refuses a weak password or an email that already has an account', function ( Closure $arrange, string $message ): void {
    config()->set( 'artisanpack.ecommerce.checkout.account_creation', true );

    $component = checkoutAtReview( $this->gateway, 'grace@example.test' )->set( 'createAccount', true );

    $arrange( $component );

    $component->call( 'placeOrder', placeOrderToken( $component ) )
        ->assertHasErrors( 'password' )
        ->assertSee( $message );

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

    $component = checkoutAtReview( $this->gateway, 'grace@example.test' )->set( 'createAccount', true )->set( 'password', 'correct-horse-battery' );

    $component->call( 'placeOrder', placeOrderToken( $component ) );

    expect( Order::query()->count() )->toBe( 1 )
        ->and( auth()->check() )->toBeFalse()
        ->and( placedRedirect( $component ) )->toContain( 'token=' );
} );

it( 'sends the shopper back to payment when the payment no longer matches', function (): void {
    $component = checkoutAtReview( $this->gateway );
    $token     = placeOrderToken( $component );

    Cart::query()->whereKey( $this->cart->id )->update( [ 'payment_reference' => 'fake_ps_other' ] );
    $this->gateway->sessions['fake_ps_other'] = $this->gateway->sessions['fake_ps_1'];

    $component->call( 'placeOrder', $token )->assertHasErrors();

    expect( Order::query()->count() )->toBe( 0 )
        ->and( $component->get( 'step' ) )->toBe( 'payment' )
        ->and( $component->get( 'confirmedPayment' ) )->toBeNull();
} );

it( 'shows a generic message when placing the order fails unexpectedly', function (): void {
    addAction( 'ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder', static function (): void {
        throw new RuntimeException( 'A listener broke.' );
    } );

    $component = checkoutAtReview( $this->gateway );

    $component->call( 'placeOrder', placeOrderToken( $component ) )
        ->assertHasErrors( 'review' )
        ->assertSee( 'We couldn\'t place your order. Try again in a moment.' )
        ->assertSet( 'step', 'review' );

    expect( Order::query()->count() )->toBe( 0 );
} );

it( 'shows the payment again when the bank needs it confirmed, then finishes the order', function (): void {
    $component = checkoutAtReview( $this->gateway );
    $token     = placeOrderToken( $component );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_REQUIRES_ACTION );

    $component->call( 'placeOrder', $token );

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

    $component = checkoutAtReview( $this->gateway );

    $component->call( 'placeOrder', placeOrderToken( $component ) )
        ->assertSet( 'step', 'payment' )
        ->assertSee( 'Your card was declined.' )
        ->assertSeeHtml( 'data-checkout-payment-driver="fake-driver"' );

    expect( Order::query()->sole()->payment_status )->not->toBe( 'paid' );
} );

it( 'picks the unpaid order up again after a reload', function (): void {
    $component = checkoutAtReview( $this->gateway );
    $token     = placeOrderToken( $component );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_REQUIRES_ACTION );
    $component->call( 'placeOrder', $token );

    $order = Order::query()->sole();

    Livewire::test( Index::class )
        ->assertSet( 'placement.order', $order->id )
        ->assertSee( 'Finish paying for order ' . $order->order_number )
        ->assertSee( 'its payment isn\'t finished' )
        ->assertSeeHtml( 'data-fake-driver="fake_ps_1"' );
} );

it( 'finishes the order when the shopper returns from the bank', function (): void {
    $component = checkoutAtReview( $this->gateway );
    $token     = placeOrderToken( $component );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_REQUIRES_ACTION );
    $component->call( 'placeOrder', $token );

    $order = Order::query()->sole();

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_AUTHORIZED );

    $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return', [ 'payment_intent' => 'fake_ps_1' ] ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.checkout' ) );

    Livewire::test( Index::class )->assertRedirectContains( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) );

    expect( $order->refresh()->payment_status )->toBe( 'paid' );
} );

it( 'sends a returning shopper whose order is already paid to the confirmation', function (): void {
    $component = checkoutAtReview( $this->gateway );
    $token     = placeOrderToken( $component );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_REQUIRES_ACTION );
    $component->call( 'placeOrder', $token );

    $order = Order::query()->sole();
    $order->forceFill( [ 'payment_status' => 'paid' ] )->save();

    $response = $this->get( route( 'artisanpack.ecommerce.storefront.checkout.return' ) );

    expect( (string) $response->headers->get( 'Location' ) )->toStartWith( route( 'artisanpack.ecommerce.storefront.confirmation', [ 'order' => $order->id ] ) )
        ->and( session()->has( CheckoutPlacement::SESSION_KEY ) )->toBeFalse();
} );

it( 'shows a generic message when fraud screening blocks the payment', function (): void {
    app( FraudProviderRegistry::class )->register( 'fake-fraud', new FakeFraudProvider( 'block' ) );
    config()->set( 'artisanpack.ecommerce.fraud.provider', 'fake-fraud' );

    $component = checkoutAtReview( $this->gateway );

    $component->call( 'placeOrder', placeOrderToken( $component ) )
        ->assertSet( 'unavailable', 'We couldn\'t process your order. Please contact the store for help.' )
        ->assertDontSee( 'card_testing' );

    expect( session()->has( CheckoutPlacement::SESSION_KEY ) )->toBeFalse()
        ->and( $this->gateway->captures )->toBe( 0 );
} );

it( 'says the order is placed when there is no confirmation page', function (): void {
    $component = checkoutAtReview( $this->gateway );
    $token     = placeOrderToken( $component );

    app( 'router' )->getRoutes()->getByName( 'artisanpack.ecommerce.storefront.confirmation' )->name( 'renamed-confirmation' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    $component->call( 'placeOrder', $token );
    $order = Order::query()->sole();

    $component->assertSet( 'placedOrder', $order->order_number )
        ->assertSeeHtml( 'data-checkout-placed' )
        ->assertSee( 'Thank you for your order' );
} );

it( 'checks out a new cart instead of an unpaid order the shopper left', function (): void {
    $component = checkoutAtReview( $this->gateway );
    $token     = placeOrderToken( $component );

    $this->gateway->setStatus( 'fake_ps_1', PaymentSession::STATUS_REQUIRES_ACTION );
    $component->call( 'placeOrder', $token );

    expect( session()->has( CheckoutPlacement::SESSION_KEY ) )->toBeTrue();

    app( ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart::class )->refresh();
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

    $component = checkoutAtReview( $this->gateway );

    $component->call( 'placeOrder', 'not-a-token' )->call( 'placeOrder', placeOrderToken( $component ) );

    expect( Order::query()->count() )->toBe( 0 )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Too many attempts' );
} );
