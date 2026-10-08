<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Checkout\CheckoutState;
use ArtisanPackUI\Ecommerce\Models\ShippingMethod;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The checkout, past the contact and address steps.
 *
 * @param  array<string, string>  $address  Address overrides.
 */
function checkoutAtShipping( array $address = [] ): Testable
{
    return Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->set( 'shipping', checkoutAddress( $address ) )
        ->call( 'saveAddress' );
}

/**
 * The quoted rate with `$label`.
 */
function quotedRate( Testable $component, string $label ): string
{
    return (string) collect( $component->get( 'rates' ) )->firstWhere( 'label', $label )['id'];
}

it( 'lists the rates with their price and delivery estimate', function (): void {
    checkoutShipping( [ 'Standard' => [ 500, '3–5 business days' ], 'Express' => 1500 ] );
    checkoutCart();

    checkoutAtShipping()
        ->assertSet( 'step', 'shipping' )
        ->assertSeeHtml( 'data-checkout-rates' )
        ->assertSee( 'Standard' )
        ->assertSee( '$5.00 · 3–5 business days' )
        ->assertSee( 'Express' )
        ->assertSee( '$15.00' )
        ->assertSet( 'shippingRate', '' )
        ->assertSeeHtml( 'data-cart-shipping="checkout"' )
        ->assertSee( 'Calculated at the shipping step' );
} );

it( 'applies the chosen rate straight away and updates the totals', function (): void {
    checkoutShipping( [ 'Standard' => 500, 'Express' => 1500 ] );
    $cart = checkoutCart( [ makeProduct( 2000, [ 'name' => 'Mug' ] ) ] );

    $component = checkoutAtShipping();

    $component->set( 'shippingRate', quotedRate( $component, 'Express' ) )
        ->assertHasNoErrors()
        ->assertSet( 'announcement', 'Shipping updated. Total: $35.00.' )
        ->assertDispatched( 'ecommerce-cart-updated' )
        ->assertSeeHtml( 'data-cart-shipping="chosen"' )
        ->assertSee( '$35.00' )
        ->assertSet( 'step', 'shipping' );

    expect( $cart->refresh() )
        ->shipping_amount->toBe( 1500 )
        ->total_amount->toBe( 3500 )
        ->checkout_state->toBe( CheckoutState::PAYMENT_SELECTION );
} );

it( 'moves on to payment with the chosen rate', function (): void {
    checkoutShipping( [ 'Standard' => 500, 'Express' => 1500 ] );
    checkoutCart();

    $component = checkoutAtShipping();

    $component->set( 'shippingRate', quotedRate( $component, 'Standard' ) )
        ->call( 'saveShipping' )
        ->assertHasNoErrors()
        ->assertSet( 'step', 'payment' )
        ->assertSeeHtml( 'data-checkout-step-summary="shipping"' )
        ->assertSee( 'Standard' );
} );

it( 'asks for a rate before moving on', function (): void {
    checkoutShipping();
    checkoutCart();

    checkoutAtShipping()
        ->call( 'saveShipping' )
        ->assertHasErrors( 'shippingRate' )
        ->assertSee( 'Choose a shipping option.' )
        ->assertSeeHtml( 'data-checkout-errors' )
        ->assertSet( 'step', 'shipping' );
} );

it( 'only applies a rate that was quoted', function (): void {
    checkoutShipping();
    $cart = checkoutCart();

    checkoutAtShipping()
        ->set( 'shippingRate', '999:flat-rate' )
        ->call( 'saveShipping' )
        ->assertHasErrors( 'shippingRate' );

    expect( $cart->refresh()->shipping_amount )->toBe( 0 );
} );

it( 'shows the engine\'s refusal when a rate is withdrawn, and re-quotes', function (): void {
    checkoutShipping( [ 'Standard' => 500, 'Express' => 1500 ] );
    checkoutCart();

    $component = checkoutAtShipping();
    $express   = quotedRate( $component, 'Express' );

    ShippingMethod::query()->where( 'label', 'Express' )->delete();

    $component->set( 'shippingRate', $express )
        ->call( 'saveShipping' )
        ->assertHasErrors( 'shippingRate' )
        ->assertSet( 'step', 'shipping' )
        ->assertSet( 'rates', fn ( array $rates ): bool => [ 'Standard' ] === array_column( $rates, 'label' ) );
} );

it( 'says when no rates reach the address, with a way back to it', function (): void {
    checkoutShipping( [ 'Standard' => 500 ], [ 'CA' ] );
    checkoutCart();

    checkoutAtShipping()
        ->assertSet( 'step', 'shipping' )
        ->assertSet( 'rates', [] )
        ->assertSeeHtml( 'data-checkout-no-rates' )
        ->assertSee( 'We can\'t ship to this address' )
        ->assertDontSeeHtml( 'data-checkout-shipping-submit' )
        ->call( 'goTo', 'address' )
        ->assertSet( 'step', 'address' );
} );

it( 're-quotes when the address changes and clears a choice that no longer applies', function (): void {
    checkoutShipping( [ 'US standard' => 500 ], [ 'US' ] );
    checkoutShipping( [ 'Canada post' => 1200 ], [ 'CA' ] );
    $cart = checkoutCart();

    $component = checkoutAtShipping();
    $component->set( 'shippingRate', quotedRate( $component, 'US standard' ) )->call( 'saveShipping' )->assertSet( 'step', 'payment' );

    $component->call( 'goTo', 'address' )
        ->set( 'shipping', checkoutAddress( [ 'country_code' => 'CA', 'region_code' => 'ON', 'postal_code' => 'K1A 0B1' ] ) )
        ->call( 'saveAddress' )
        ->assertSet( 'step', 'shipping' )
        ->assertSet( 'shippingRate', '' )
        ->assertSeeHtml( 'data-checkout-shipping-notice' )
        ->assertSee( 'Your shipping choice isn\'t available for this address. Choose a shipping option.' )
        ->assertSet( 'rates', fn ( array $rates ): bool => [ 'Canada post' ] === array_column( $rates, 'label' ) );

    expect( $cart->refresh()->checkout_state )->toBe( CheckoutState::SHIPPING_SELECTION );
} );

it( 'keeps a rate that is still offered after the address changes, for the shopper to confirm', function (): void {
    checkoutShipping( [ 'Standard' => 500 ] );
    checkoutCart();

    $component = checkoutAtShipping();
    $standard  = quotedRate( $component, 'Standard' );
    $component->set( 'shippingRate', $standard )->call( 'saveShipping' );

    $component->call( 'goTo', 'address' )
        ->set( 'shipping', checkoutAddress( [ 'address1' => '2 Other Street' ] ) )
        ->call( 'saveAddress' )
        ->assertSet( 'step', 'shipping' )
        ->assertSet( 'shippingRate', $standard )
        ->assertSet( 'shippingNotice', null )
        ->assertDontSeeHtml( 'data-checkout-shipping-notice' );
} );

it( 're-quotes when the lines change in the cart drawer', function (): void {
    checkoutShipping( [ 'Standard' => 500 ] );
    $mug  = makeProduct( 1000, [ 'name' => 'Mug' ] );
    $cart = checkoutCart( [ $mug ] );

    $component = checkoutAtShipping();
    $component->set( 'shippingRate', quotedRate( $component, 'Standard' ) );

    app( StorefrontCartService::class )->addItem( $cart, makeProduct( 3000, [ 'name' => 'Vase' ] )->id, null, 1 );

    $component->dispatch( 'ecommerce-cart-updated', count: 2 )
        ->assertSee( 'Vase' )
        ->assertSet( 'step', 'shipping' )
        ->assertSet( 'rates', fn ( array $rates ): bool => [ 'Standard' ] === array_column( $rates, 'label' ) );
} );
