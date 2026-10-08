<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Checkout\CheckoutState;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Services\CheckoutService;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\AgeCheckStep;

beforeEach( function (): void {
    Route::get( '/login', static fn (): string => 'login' )->name( 'login' );
    Route::get( '/register', static fn (): string => 'register' )->name( 'register' );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.checkout.steps' );
    removeAllFilters( 'ap.ecommerce.checkout.canTransitionTo' );
} );

it( 'sends an empty cart back to the cart page', function (): void {
    Livewire::test( Index::class )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.cart' ) );

    expect( session( ToastPayload::SESSION_KEY )['toast']['title'] ?? null )->toBe( 'Your cart is empty' );
} );

it( 'sends a cart with lines that can\'t be bought back to the cart page', function (): void {
    $mug = makeProduct( 1000, [ 'name' => 'Mug' ] );
    checkoutCart( [ $mug ] );
    $mug->update( [ 'status' => 'draft' ] );

    Livewire::test( Index::class )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.cart' ) );

    expect( session( ToastPayload::SESSION_KEY )['toast'] )
        ->title->toBe( e( 'Some items can\'t be bought' ) )
        ->description->toBe( 'Remove the item marked in your cart to check out.' );
} );

it( 'shows the reason in place when there is no cart page to go back to', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.storefront.routes_enabled', false );
    app( 'router' )->setRoutes( new Illuminate\Routing\RouteCollection() );

    Livewire::test( Index::class )
        ->assertNoRedirect()
        ->assertSeeHtml( 'data-checkout-unavailable' )
        ->assertSee( 'Your cart is empty' );
} );

it( 'starts checkout, holding the stock, on the contact step', function (): void {
    $mug = makeProduct( 1000, [ 'name' => 'Mug' ] );
    setStock( $mug, 5 );
    $cart = checkoutCart( [ $mug ], 2 );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSet( 'step', 'contact' )
        ->assertSeeHtml( 'data-checkout-step="contact"' )
        ->assertSeeHtml( 'data-checkout-step-state="current"' )
        ->assertSeeHtml( 'data-checkout-contact-form' )
        ->assertSee( 'Order summary' )
        ->assertSee( 'Mug' )
        ->assertDontSeeHtml( 'data-checkout-adjustments' );

    expect( $cart->refresh() )
        ->checkout_state->toBe( CheckoutState::ADDRESSING )
        ->checkout_started_at->not->toBeNull()
        ->and( $mug->inventoryItems()->first()->quantity_reserved )->toBe( 2 );
} );

it( 'reports the lines it couldn\'t hold in full before the first step', function (): void {
    $mug  = makeProduct( 1000, [ 'name' => 'Mug' ] );
    $vase = makeProduct( 3000, [ 'name' => 'Vase' ] );
    setStock( $mug, 5 );
    setStock( $vase, 5 );
    $cart = checkoutCart( [ $mug, $vase ], 3 );

    $mug->inventoryItems()->update( [ 'quantity_on_hand' => 2 ] );

    Livewire::test( Index::class )
        ->assertNoRedirect()
        ->assertSeeHtml( 'data-checkout-adjustments' )
        ->assertSee( 'Only 2 of Mug are in stock, so we\'ve changed the quantity in your cart.' )
        ->assertDontSee( 'Vase is out of stock' )
        ->assertDispatched( 'ecommerce-cart-updated', count: 5 );

    expect( $cart->items()->orderBy( 'id' )->pluck( 'quantity' )->all() )->toBe( [ 2, 3 ] );
} );

it( 'sends the shopper back to the cart when nothing could be held', function (): void {
    $mug = makeProduct( 1000, [ 'name' => 'Mug' ] );
    setStock( $mug, 5 );
    checkoutCart( [ $mug ] );
    $mug->inventoryItems()->update( [ 'quantity_on_hand' => 0 ] );

    // An out-of-stock line is "can't be bought" before start() runs.
    Livewire::test( Index::class )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.cart' ) );
} );

it( 'lets a guest check out, or sign in for faster checkout', function (): void {
    checkoutCart();

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-checkout-sign-in-link' )
        ->assertSeeHtml( 'href="' . route( 'login' ) . '"' )
        ->assertDontSeeHtml( 'data-checkout-account-needed' );

    expect( session( 'url.intended' ) )->toBe( route( 'artisanpack.ecommerce.storefront.checkout' ) );
} );

it( 'tells a guest they need an account before paying when the store requires one', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.guest_checkout', CheckoutService::GUESTS_REQUIRE_ACCOUNT );
    checkoutCart();

    Livewire::test( Index::class )
        ->assertSet( 'signInRequired', false )
        ->assertSeeHtml( 'data-checkout-account-needed' )
        ->assertSeeHtml( 'href="' . route( 'register' ) . '"' )
        ->assertDontSeeHtml( 'data-checkout-sign-in-link' );
} );

it( 'asks a guest to sign in first when guest checkout is off', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.guest_checkout', CheckoutService::GUESTS_DISABLED );
    $cart = checkoutCart();

    Livewire::test( Index::class )
        ->assertSet( 'signInRequired', true )
        ->assertSeeHtml( 'data-checkout-sign-in-required' )
        ->assertSee( 'Sign in to check out' )
        ->assertSeeHtml( 'href="' . route( 'login' ) . '"' )
        ->assertSeeHtml( 'href="' . route( 'register' ) . '"' )
        ->assertDontSeeHtml( 'data-checkout-steps' );

    expect( $cart->refresh()->checkout_started_at )->toBeNull()
        ->and( session( 'url.intended' ) )->toBe( route( 'artisanpack.ecommerce.storefront.checkout' ) );
} );

it( 'lets a signed-in shopper check out when guest checkout is off', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.guest_checkout', CheckoutService::GUESTS_DISABLED );
    $user = makeUser( [ 'email' => 'ada@example.test' ] );
    $this->actingAs( $user );
    checkoutCart();

    // The account's email is already on its cart, so contact is done.
    Livewire::test( Index::class )
        ->assertSet( 'signInRequired', false )
        ->assertSet( 'email', 'ada@example.test' )
        ->assertSet( 'step', 'address' )
        ->assertDontSeeHtml( 'data-checkout-sign-in-link' )
        ->assertSeeHtml( 'data-checkout-address-form' );
} );

it( 'saves the email to the cart and moves on to the address', function (): void {
    $cart = checkoutCart();

    Livewire::test( Index::class )
        ->assertSet( 'marketingConsent', false )
        ->set( 'email', '  Ada@Example.test ' )
        ->call( 'saveContact' )
        ->assertHasNoErrors()
        ->assertSet( 'step', 'address' )
        ->assertSet( 'announcement', 'Step 2 of 5: Address' )
        ->assertSeeHtml( 'data-checkout-step-summary="contact"' )
        ->assertSee( 'ada@example.test' )
        ->assertSeeHtml( 'data-checkout-edit="contact"' );

    expect( $cart->refresh() )
        ->email->toBe( 'ada@example.test' )
        ->meta->not->toHaveKey( Index::MARKETING_CONSENT_META_KEY );
} );

it( 'keeps the shopper\'s marketing consent on the cart', function (): void {
    $cart = checkoutCart();

    Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->set( 'marketingConsent', true )
        ->call( 'saveContact' );

    expect( $cart->refresh()->meta[ Index::MARKETING_CONSENT_META_KEY ] )->toBeTrue();

    Livewire::test( Index::class )
        ->assertSet( 'marketingConsent', true )
        ->assertSet( 'email', 'ada@example.test' );
} );

it( 'validates the email', function ( string $email, string $message ): void {
    checkoutCart();

    Livewire::test( Index::class )
        ->set( 'email', $email )
        ->call( 'saveContact' )
        ->assertHasErrors( 'email' )
        ->assertSee( $message )
        ->assertSeeHtml( 'data-checkout-errors' )
        ->assertSeeHtml( 'data-checkout-error="email"' )
        ->assertSet( 'step', 'contact' )
        ->assertSet( 'errorSummary', 1 );
} )->with( [
    'missing'  => [ '', 'Enter your email address.' ],
    'invalid'  => [ 'not-an-email', 'Enter a valid email address.' ],
    'too long' => [ str_repeat( 'a', 250 ) . '@example.test', 'Enter a valid email address.' ],
] );

it( 'shows the engine\'s refusal of the email', function (): void {
    checkoutCart();

    Livewire::test( Index::class )
        ->set( 'email', 'ada@exa mple.test' )
        ->call( 'saveContact' )
        ->assertHasErrors( 'email' )
        ->assertSet( 'step', 'contact' );
} );

it( 'opens the furthest step the cart has reached', function (): void {
    checkoutShipping();
    $cart = checkoutCart();

    app( CheckoutService::class )->setEmail( $cart, 'ada@example.test' );
    app( CheckoutService::class )->setAddress( $cart, ArtisanPackUI\Ecommerce\ValueObjects\Address::fromArray( checkoutAddress() ) );

    Livewire::test( Index::class )
        ->assertSet( 'step', 'shipping' )
        ->assertSeeHtml( 'data-checkout-shipping-form' );
} );

it( 'keeps a step from the query string only when the shopper can reach it', function (): void {
    $cart = checkoutCart();

    Livewire::withQueryParams( [ 'step' => 'payment' ] )->test( Index::class )
        ->assertSet( 'step', 'contact' );

    app( CheckoutService::class )->setEmail( $cart, 'ada@example.test' );

    Livewire::withQueryParams( [ 'step' => 'contact' ] )->test( Index::class )
        ->assertSet( 'step', 'contact' );

    Livewire::withQueryParams( [ 'step' => 'nonsense' ] )->test( Index::class )
        ->assertSet( 'step', 'address' );
} );

it( 'only opens steps the shopper has reached', function (): void {
    $cart = checkoutCart();

    app( CheckoutService::class )->setEmail( $cart, 'ada@example.test' );

    Livewire::test( Index::class )
        ->assertSet( 'step', 'address' )
        ->call( 'goTo', 'payment' )
        ->assertSet( 'step', 'address' )
        ->call( 'goTo', 'contact' )
        ->assertSet( 'step', 'contact' )
        ->assertSet( 'focusStep', true )
        ->assertSet( 'announcement', 'Step 1 of 5: Contact' );
} );

it( 'shows a blocked transition with the engine\'s message', function (): void {
    checkoutShipping();
    checkoutCart();

    addFilter( 'ap.ecommerce.checkout.canTransitionTo', static fn ( bool $allowed, Cart $cart, string $to ): bool => CheckoutState::SHIPPING_SELECTION !== $to, 10, 3 );

    Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->set( 'shipping', checkoutAddress() )
        ->call( 'saveAddress' )
        ->assertHasErrors( 'address' )
        ->assertSee( 'This checkout step isn\'t available for your cart.' )
        ->assertSet( 'step', 'address' );
} );

it( 'lets a satellite add a step before review', function (): void {
    Livewire::component( 'age-check-step', AgeCheckStep::class );

    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', static function ( array $steps ): array {
        $steps['age-check'] = [ 'label' => 'Date of birth', 'component' => 'age-check-step' ];

        return $steps;
    } );

    checkoutCart();

    $component = Livewire::test( Index::class )
        ->assertSeeHtml( 'data-checkout-step="age-check"' )
        ->assertSee( 'Date of birth' );

    $html = $component->html();

    expect( strpos( $html, 'data-checkout-step="payment"' ) )->toBeLessThan( strpos( $html, 'data-checkout-step="age-check"' ) )
        ->and( strpos( $html, 'data-checkout-step="age-check"' ) )->toBeLessThan( strpos( $html, 'data-checkout-step="review"' ) );
} );

it( 'mounts a satellite step and moves on when it reports done', function (): void {
    Livewire::component( 'age-check-step', AgeCheckStep::class );

    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', static function ( array $steps ): array {
        $steps['age-check'] = [ 'label' => 'Date of birth', 'component' => 'age-check-step', 'before' => 'address' ];

        return $steps;
    } );

    checkoutCart();

    Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->assertSet( 'step', 'age-check' )
        ->assertSeeLivewire( AgeCheckStep::class )
        ->call( 'goTo', 'address' )
        ->assertSet( 'step', 'age-check' )
        ->dispatch( 'ecommerce-checkout-step-completed', step: 'unknown' )
        ->assertSet( 'completedSteps', [] )
        ->dispatch( 'ecommerce-checkout-step-completed', step: 'age-check' )
        ->assertSet( 'completedSteps', [ 'age-check' ] )
        ->assertSet( 'step', 'address' );
} );

it( 'follows cart changes made in the drawer', function (): void {
    $mug  = makeProduct( 1000, [ 'name' => 'Mug' ] );
    $cart = checkoutCart( [ $mug ] );

    $component = Livewire::test( Index::class )->assertSee( '$10.00' );

    app( StorefrontCartService::class )->updateItem( $cart, $cart->items()->sole(), 3 );

    $component->dispatch( 'ecommerce-cart-updated', count: 3 )
        ->assertSee( '$30.00' );

    app( StorefrontCartService::class )->removeItem( $cart, $cart->items()->sole() );

    $component->dispatch( 'ecommerce-cart-updated', count: 0 )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.cart' ) );
} );

it( 'treats the cart of a signed-in shopper as theirs', function (): void {
    $user = makeUser();
    $this->actingAs( $user );
    $cart = checkoutCart();

    expect( $cart->customer_id )->toBe( Customer::forUser( $user )?->id );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'data-checkout-sign-in-link' );

    expect( session( 'url.intended' ) )->toBeNull();
} );

it( 'keeps its state out of the browser\'s reach', function ( string $property, mixed $value ): void {
    checkoutCart();

    Livewire::test( Index::class )->set( $property, $value );
} )->with( [
    'rates'            => [ 'rates', [ [ 'id' => 'free', 'label' => 'Free', 'amount' => 0, 'currency' => 'USD', 'estimate' => null ] ] ],
    'payment'          => [ 'payment', [ 'component' => 'evil' ] ],
    'confirmed'        => [ 'confirmedPayment', [ 'reference' => 'x', 'total' => 0 ] ],
    'completed steps'  => [ 'completedSteps', [ 'age-check' ] ],
    'sign in required' => [ 'signInRequired', false ],
] )->throws( CannotUpdateLockedPropertyException::class );

it( 'escapes product names in the order summary', function (): void {
    checkoutCart( [ makeProduct( 1000, [ 'name' => '<script>alert(1)</script>' ] ) ] );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( '<script>alert(1)</script>' )
        ->assertSeeHtml( '&lt;script&gt;alert(1)&lt;/script&gt;' );
} );
