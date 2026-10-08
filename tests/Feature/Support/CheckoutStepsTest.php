<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutSteps;

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.checkout.steps' );
} );

/**
 * The step keys for a cart, in order.
 *
 * @return array<int, string>
 */
function checkoutStepKeys( bool $ships = true ): array
{
    return array_keys( CheckoutSteps::for( Cart::factory()->create(), $ships ) );
}

it( 'lists the core steps, without shipping for a cart that doesn\'t ship', function (): void {
    expect( checkoutStepKeys() )->toBe( [ 'contact', 'address', 'shipping', 'payment', 'review' ] )
        ->and( checkoutStepKeys( false ) )->toBe( [ 'contact', 'address', 'payment', 'review' ] )
        ->and( CheckoutSteps::for( Cart::factory()->create(), false )['address']['label'] )->toBe( 'Billing address' );
} );

it( 'places added steps before review, or before the step they name', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', static function ( array $steps, Cart $cart ): array {
        $steps['gift-note'] = [ 'label' => 'Gift note', 'component' => 'gift-note-step' ];
        $steps['age-check'] = [ 'label' => 'Date of birth', 'component' => 'age-check-step', 'before' => 'payment' ];
        $steps['insurance'] = [ 'label' => 'Insurance', 'component' => 'insurance-step', 'before' => 'shipping' ];

        return $steps;
    }, 10, 2 );

    expect( checkoutStepKeys() )->toBe( [ 'contact', 'address', 'insurance', 'shipping', 'age-check', 'payment', 'gift-note', 'review' ] )
        ->and( checkoutStepKeys( false ) )->toBe( [ 'contact', 'address', 'age-check', 'insurance', 'payment', 'gift-note', 'review' ] );
} );

it( 'keeps the core steps as they are and ignores bad entries', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', static function ( array $steps ): array {
        unset( $steps['payment'] );

        $steps['contact']['label'] = 'Hijacked';
        $steps['shipping']         = [ 'label' => 'Not shipping', 'component' => 'evil' ];
        $steps['No Caps']          = [ 'label' => 'Bad key', 'component' => 'x' ];
        $steps['no-label']         = [ 'label' => ' ', 'component' => 'x' ];
        $steps['no-component']     = [ 'label' => 'Label' ];
        $steps['not-an-array']     = 'nope';
        $steps['bad-before']       = [ 'label' => 'Bad before', 'component' => 'x', 'before' => 'contact' ];

        return $steps;
    } );

    $steps = CheckoutSteps::for( Cart::factory()->create(), false );

    expect( array_keys( $steps ) )->toBe( [ 'contact', 'address', 'payment', 'bad-before', 'review' ] )
        ->and( $steps['contact'] )->toBe( [ 'key' => 'contact', 'label' => 'Contact', 'component' => null ] )
        ->and( $steps['bad-before']['component'] )->toBe( 'x' );
} );

it( 'survives a filter that returns nonsense', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', static fn (): string => 'nonsense' );

    expect( checkoutStepKeys() )->toBe( [ 'contact', 'address', 'shipping', 'payment', 'review' ] );
} );
