<?php

declare( strict_types=1 );

/*
 * <x-artisanpack-ec-field>: library controls with their error and hint
 * linked by id (audit F3, livewire-ui-components#117).
 */

it( 'links the hint to an input and leaves it valid without an error', function (): void {
    $this->withViewErrors( [] )
        ->blade( '<x-artisanpack-ec-field id="email" type="email" label="Email" hint="For your receipt." wire:model="email" required />' )
        ->assertSee( 'aria-describedby="ec-field-email-hint"', false )
        ->assertSee( '<p id="ec-field-email-hint"', false )
        ->assertSee( 'For your receipt.' )
        ->assertSee( 'type="email"', false )
        ->assertSee( 'wire:model="email"', false )
        ->assertDontSee( 'aria-invalid', false )
        ->assertDontSee( 'data-field-error', false );
} );

it( 'marks an input invalid and links its error before its hint', function (): void {
    $this->withViewErrors( [ 'email' => 'Enter a valid email.' ] )
        ->blade( '<x-artisanpack-ec-field id="email" label="Email" hint="For your receipt." wire:model="email" />' )
        ->assertSee( 'aria-invalid="true" aria-describedby="ec-field-email-error ec-field-email-hint"', false )
        ->assertSee( '<p id="ec-field-email-error"', false )
        ->assertSee( 'Enter a valid email.' )
        ->assertSee( '!input-error', false );
} );

it( 'prints the error once, not also through the library', function (): void {
    $html = (string) $this->withViewErrors( [ 'email' => 'Enter a valid email.' ] )
        ->blade( '<x-artisanpack-ec-field id="email" label="Email" wire:model="email" />' );

    expect( substr_count( $html, 'Enter a valid email.' ) )->toBe( 1 );
} );

it( 'reads the error from error-field before wire:model', function (): void {
    $this->withViewErrors( [ 'couponCode' => 'That coupon code is not valid.' ] )
        ->blade( '<x-artisanpack-ec-field id="coupon" label="Coupon" wire:model="code" error-field="couponCode" />' )
        ->assertSee( 'aria-invalid="true"', false )
        ->assertSee( 'That coupon code is not valid.' );
} );

it( 'keeps an aria-describedby the caller passes', function (): void {
    $this->withViewErrors( [ 'email' => 'Required.' ] )
        ->blade( '<x-artisanpack-ec-field id="email" label="Email" wire:model="email" aria-describedby="intro" />' )
        ->assertSee( 'aria-describedby="intro ec-field-email-error"', false );
} );

it( 'wraps a select, a textarea, a password, and a checkbox', function (): void {
    $this->withViewErrors( [ 'country' => 'Choose a country.', 'note' => 'Too long.', 'password' => 'Too short.', 'terms' => 'Accept the terms.' ] )
        ->blade( <<<'BLADE'
            <x-artisanpack-ec-field control="select" id="country" label="Country" :options="[ [ 'id' => 'US', 'name' => 'United States' ] ]" placeholder="Select" placeholder-value="" wire:model="country" />
            <x-artisanpack-ec-field control="textarea" id="note" label="Note" wire:model="note" rows="3" />
            <x-artisanpack-ec-field control="password" id="password" label="Password" wire:model="password" />
            <x-artisanpack-ec-field control="checkbox" id="terms" label="I accept" hint="Required to order." wire:model="terms" />
            BLADE )
        ->assertSee( 'United States' )
        ->assertSee( 'aria-describedby="ec-field-country-error"', false )
        ->assertSee( '!select-error', false )
        ->assertSee( 'aria-describedby="ec-field-note-error"', false )
        ->assertSee( '!textarea-error', false )
        ->assertSee( 'aria-describedby="ec-field-password-error"', false )
        ->assertSee( 'aria-describedby="ec-field-terms-error"', false )
        ->assertSee( 'Required to order.' )
        ->assertDontSee( 'ec-field-terms-hint', false );
} );

it( 'makes ids safe from dotted model paths', function (): void {
    $this->withViewErrors( [ 'shipping.city' => 'Enter the city.' ] )
        ->blade( '<x-artisanpack-ec-field id="shipping.city" label="City" wire:model="shipping.city" />' )
        ->assertSee( 'id="ec-field-shipping-city-error"', false );
} );

it( 'refuses an unknown control', function (): void {
    $this->withViewErrors( [] )->blade( '<x-artisanpack-ec-field control="radio" id="x" label="X" />' );
} )->throws( Illuminate\View\ViewException::class, 'Unknown field control "radio".' );
