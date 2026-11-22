<?php

declare( strict_types=1 );

/*
 * <x-artisanpack-ec-field>: library controls with their error and hint
 * linked by id (audit F3). Since livewire-ui-components 3.0 the control
 * prints and links both itself.
 */

it( 'links the hint to an input and leaves it valid without an error', function (): void {
    $this->withViewErrors( [] )
        ->blade( '<x-artisanpack-ec-field id="email" type="email" label="Email" hint="For your receipt." wire:model="email" required />' )
        ->assertSee( 'aria-describedby="email-hint"', false )
        ->assertSee( 'id="email-hint"', false )
        ->assertSee( 'For your receipt.' )
        ->assertSee( 'type="email"', false )
        ->assertSee( 'wire:model="email"', false )
        ->assertDontSee( 'aria-invalid', false )
        ->assertDontSee( 'data-part="error"', false );
} );

it( 'marks an input invalid and links its hint and error', function (): void {
    $this->withViewErrors( [ 'email' => 'Enter a valid email.' ] )
        ->blade( '<x-artisanpack-ec-field id="email" label="Email" hint="For your receipt." wire:model="email" />' )
        ->assertSeeInOrder( [ 'aria-invalid="true"', 'aria-describedby="email-hint email-error"' ], false )
        ->assertSee( 'id="email-error"', false )
        ->assertSee( 'Enter a valid email.' )
        ->assertSee( 'data-invalid', false );
} );

it( 'prints the error once', function (): void {
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
        ->assertSee( 'aria-describedby="email-error intro"', false );
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
        ->assertSee( '<option value="US"', false )
        ->assertSee( 'aria-describedby="country-error"', false )
        ->assertSee( 'aria-describedby="note-error"', false )
        ->assertSee( 'aria-describedby="password-error"', false )
        ->assertSee( 'Required to order.' )
        ->assertSee( 'Accept the terms.' );
} );

it( 'reads the error for a dotted model path and marks the wrapper for the error summary', function (): void {
    $this->withViewErrors( [ 'shipping.city' => 'Enter the city.' ] )
        ->blade( '<x-artisanpack-ec-field id="shipping.city" label="City" wire:model="shipping.city" />' )
        ->assertSee( 'data-field-key="shipping.city"', false )
        ->assertSee( 'Enter the city.' );
} );

it( 'refuses an unknown control', function (): void {
    $this->withViewErrors( [] )->blade( '<x-artisanpack-ec-field control="radio" id="x" label="X" />' );
} )->throws( Illuminate\View\ViewException::class, 'Unknown field control "radio".' );

it( 'labels a select and a textarea with a real label', function (): void {
    $this->withViewErrors( [] )
        ->blade( <<<'BLADE'
            <x-artisanpack-ec-field control="select" id="country" label="Country" :options="[]" wire:model="country" />
            <x-artisanpack-ec-field control="textarea" id="note" label="Order note" wire:model="note" />
            BLADE )
        ->assertSee( '<label data-scope="select" data-part="label" for="country">Country</label>', false )
        ->assertSee( 'for="note">Order note</label>', false );
} );

it( 'maps select options to the library shape with custom keys', function (): void {
    $this->withViewErrors( [] )
        ->blade( '<x-artisanpack-ec-field control="select" id="size" label="Size" option-value="code" option-label="title" :options="[ [ \'code\' => \'m\', \'title\' => \'Medium\' ], [ \'code\' => \'l\', \'title\' => \'Large\', \'disabled\' => true ], \'junk\' ]" wire:model="size" />' )
        ->assertSee( '<option value="m" >Medium</option>', false )
        ->assertSee( 'Large' )
        ->assertDontSee( 'junk' );
} );

it( 'marks an optional field after its label, escaped, and never a checkbox', function (): void {
    $this->withViewErrors( [] )
        ->blade( <<<'BLADE'
            <x-artisanpack-ec-field id="company" label="Company <b>" optional wire:model="company" />
            <x-artisanpack-ec-field control="select" id="region" label="Region" :options="[]" optional wire:model="region" />
            <x-artisanpack-ec-field control="checkbox" id="news" label="Send me news" optional wire:model="news" />
            BLADE )
        ->assertSee( 'for="company">Company &lt;b&gt; <span class="ec-field__optional">(optional)</span></label>', false )
        ->assertSee( 'for="region">Region <span class="ec-field__optional">(optional)</span></label>', false )
        ->assertSee( 'Send me news' );

    expect( substr_count( (string) $this->withViewErrors( [] )->blade( '<x-artisanpack-ec-field control="checkbox" id="news" label="Send me news" optional wire:model="news" />' ), '(optional)' ) )->toBe( 0 );
} );

it( 'leaves a required or unmarked field without the optional mark', function (): void {
    $this->withViewErrors( [] )
        ->blade( '<x-artisanpack-ec-field id="email" label="Email" wire:model="email" required />' )
        ->assertSee( 'data-part="required"', false )
        ->assertDontSee( '(optional)' );
} );

it( 'wraps the control in the full-width field block with no DaisyUI or Tailwind classes', function (): void {
    $this->withViewErrors( [] )
        ->blade( '<x-artisanpack-ec-field id="email" label="Email" wire:model="email" disabled />' )
        ->assertSee( 'class="ec-field"', false )
        ->assertSee( 'data-disabled', false )
        ->assertDontSee( 'w-full', false );
} );
