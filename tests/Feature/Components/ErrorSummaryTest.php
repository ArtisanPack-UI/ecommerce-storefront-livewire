<?php

declare( strict_types=1 );

/*
 * partials/error-summary (#85): the design-system ValidationSummary, keyed
 * on the form's error counter, each error linking to its field.
 */

it( 'renders nothing without errors', function (): void {
    expect( trim( (string) $this->withViewErrors( [] )->blade( "@include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => 'ec-form', 'errorSummary' => 0 ] )" ) ) )->toBe( '' );
} );

it( 'is a focused alert counting the things to fix, each linked to its field', function (): void {
    $this->withViewErrors( [ 'email' => [ 'Enter your email.', 'Second message.' ], 'shipping.city' => 'Enter the city.' ] )
        ->blade( "@include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => 'ec-form', 'errorSummary' => 3 ] )" )
        ->assertSee( 'apui-validation-summary', false )
        ->assertSee( 'ec-error-summary', false )
        ->assertSee( 'role="alert"', false )
        ->assertSee( 'tabindex="-1"', false )
        ->assertSee( 'wire:key="ec-form-errors-3"', false )
        ->assertSee( 'id="ec-form-errors"', false )
        ->assertSee( 'data-error-summary', false )
        ->assertSee( 'Fix 2 things to continue' )
        ->assertSeeInOrder( [ 'href="#email"', 'Enter your email.', 'href="#shipping.city"', 'Enter the city.' ], false )
        ->assertDontSee( 'Second message.' )
        ->assertDontSee( 'list-disc', false );
} );

it( 'says "thing" for one error, leaves out the excepted keys, and adds the caller class', function (): void {
    $this->withViewErrors( [ 'review' => 'Already reviewed.', 'rating' => 'Choose a rating.' ] )
        ->blade( "@include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => 'ec-review', 'errorSummary' => 1, 'summaryExcept' => [ 'review' ], 'summaryClass' => 'ec-span-2' ] )" )
        ->assertSee( 'Fix 1 thing to continue' )
        ->assertSee( 'ec-span-2', false )
        ->assertSee( 'Choose a rating.' )
        ->assertDontSee( 'Already reviewed.' );
} );

it( 'renders nothing when every error is shown elsewhere', function (): void {
    expect( trim( (string) $this->withViewErrors( [ 'review' => 'Already reviewed.' ] )->blade( "@include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => 'ec-review', 'errorSummary' => 1, 'summaryExcept' => [ 'review' ] ] )" ) ) )->toBe( '' );
} );

it( 'falls back to the field wrappers when the library finds no control', function (): void {
    $this->withViewErrors( [ 'couponCode' => 'Not valid.' ] )
        ->blade( "@include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => 'ec-coupon', 'errorSummary' => 1 ] )" )
        ->assertSee( '$event.defaultPrevented', false )
        ->assertSee( '[data-field-key], [data-field]', false );
} );
