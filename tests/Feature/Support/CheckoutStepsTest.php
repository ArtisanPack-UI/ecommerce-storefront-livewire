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
    expect( checkoutStepKeys() )->toBe( [ 'contact', 'address', 'shipping', 'review', 'payment' ] )
        ->and( checkoutStepKeys( false ) )->toBe( [ 'contact', 'address', 'review', 'payment' ] )
        ->and( CheckoutSteps::for( Cart::factory()->create(), false )['address']['label'] )->toBe( 'Billing address' );
} );

it( 'places added steps before review, or before the step they name', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', static function ( array $steps, Cart $cart ): array {
        $steps['gift-note'] = [ 'label' => 'Gift note', 'component' => 'gift-note-step' ];
        $steps['age-check'] = [ 'label' => 'Date of birth', 'component' => 'age-check-step', 'before' => 'payment' ];
        $steps['insurance'] = [ 'label' => 'Insurance', 'component' => 'insurance-step', 'before' => 'shipping' ];

        return $steps;
    }, 10, 2 );

    expect( checkoutStepKeys() )->toBe( [ 'contact', 'address', 'insurance', 'shipping', 'gift-note', 'review', 'age-check', 'payment' ] )
        ->and( checkoutStepKeys( false ) )->toBe( [ 'contact', 'address', 'gift-note', 'insurance', 'review', 'age-check', 'payment' ] );
} );

it( 'keeps a callable "complete" on an added step', function (): void {
    $complete = static fn ( Cart $cart ): bool => true;

    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', static function ( array $steps ) use ( $complete ): array {
        $steps['age-check'] = [ 'label' => 'Date of birth', 'component' => 'age-check-step', 'complete' => $complete ];
        $steps['bad-check'] = [ 'label' => 'Bad', 'component' => 'bad-step', 'complete' => 'nope' ];

        return $steps;
    } );

    $steps = CheckoutSteps::for( Cart::factory()->create(), true );

    expect( $steps['age-check']['complete'] )->toBe( $complete )
        ->and( $steps )->not->toHaveKey( 'bad-check' )
        ->and( $steps['review'] )->not->toHaveKey( 'complete' );
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

    expect( array_keys( $steps ) )->toBe( [ 'contact', 'address', 'bad-before', 'review', 'payment' ] )
        ->and( $steps['contact'] )->toBe( [ 'key' => 'contact', 'label' => 'Contact', 'component' => null ] )
        ->and( $steps['bad-before']['component'] )->toBe( 'x' );
} );

it( 'survives a filter that returns nonsense', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', static fn (): string => 'nonsense' );

    expect( checkoutStepKeys() )->toBe( [ 'contact', 'address', 'shipping', 'review', 'payment' ] );
} );
