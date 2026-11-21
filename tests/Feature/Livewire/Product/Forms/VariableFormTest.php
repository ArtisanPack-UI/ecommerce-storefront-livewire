<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\VariableForm;
use Livewire\Livewire;

beforeEach( function (): void {
    // Blue has no XL; red XL is sold out.
    $this->made = makeVariableProduct( [
        'red/m'   => [ 'price' => 2000, 'stock' => 5 ],
        'red/l'   => [ 'price' => 2000, 'stock' => 5 ],
        'red/xl'  => [ 'price' => 2200, 'stock' => 0 ],
        'blue/m'  => [ 'price' => 2100, 'stock' => 5 ],
        'blue/l'  => [ 'price' => 2100, 'stock' => 5 ],
    ] );

    $this->values = $this->made['values'];
    $this->colour = $this->made['groups']['colour']->id;
    $this->size   = $this->made['groups']['size']->id;
} );

/**
 * The rendered option input for a value.
 */
function optionInput( string $html, int $valueId ): string
{
    preg_match( '/<input[^>]*value="' . $valueId . '"[^>]*>/', $html, $match );

    return $match[0] ?? '';
}

it( 'renders one required swatch group per variation attribute and lets the shopper press add before choosing', function (): void {
    $html = Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->assertOk()
        ->assertSeeHtml( 'class="ec-purchase-form" data-purchase-form="variable"' )
        ->assertSeeHtml( 'data-variation-group="' . $this->colour . '"' )
        ->assertSeeHtml( 'data-variation-group="' . $this->size . '"' )
        ->assertSeeHtml( 'background-color: #ff0000' )
        ->assertSeeHtml( 'type="radio"' )
        ->assertSeeHtml( 'class="ec-swatches__required" aria-hidden="true">*</span>' )
        ->assertDontSeeHtml( 'data-add-blocked' )
        ->html();

    expect( optionInput( $html, $this->values['red']->id ) )->toContain( 'required' )
        ->and( $html )->not->toContain( 'bg-base' )
        ->not->toContain( 'text-base-content' )
        ->not->toContain( '--color-error' );
} );

it( 'disables a combination that does not exist after choosing blue, with a reason', function (): void {
    $html = Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['blue']->id )
        ->html();

    expect( optionInput( $html, $this->values['xl']->id ) )->toContain( 'disabled' )
        ->and( optionInput( $html, $this->values['l']->id ) )->not->toContain( 'disabled' )
        ->and( $html )->toContain( 'Not available with your other choices' );
} );

it( 'says a sold-out combination is out of stock', function (): void {
    $html = Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['red']->id )
        ->html();

    expect( optionInput( $html, $this->values['xl']->id ) )->toContain( 'disabled' )
        ->and( $html )->toContain( 'Out of stock' );
} );

it( 'matches a variant, puts it in the URL, tells the page, and adds it', function (): void {
    $blueL = $this->made['variants']['blue/l'];

    $component = Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['blue']->id )
        ->set( "selected.{$this->size}", (string) $this->values['l']->id )
        ->assertSet( 'variant', $blueL->id )
        ->assertDispatched( 'ecommerce-product-variant-selected', productId: $this->made['product']->id, variantId: $blueL->id )
        ->assertDontSeeHtml( 'data-add-blocked' )
        ->call( 'addToCart' )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated', count: 1 );

    expect( Cart::query()->sole()->items()->sole()->product_variant_id )->toBe( $blueL->id );
} );

it( 'chooses values through the choose action the swatches call', function (): void {
    $component = Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] );

    expect( $component->html() )->toContain( '$wire.choose( ' . $this->colour . ', $event.target.value )' )
        ->not->toContain( 'wire:model.live="selected.' );

    $component->call( 'choose', $this->colour, (string) $this->values['red']->id )
        ->call( 'choose', $this->colour, (string) $this->values['blue']->id )
        ->call( 'choose', $this->size, (string) $this->values['l']->id )
        ->assertSet( 'selected', [ $this->colour => $this->values['blue']->id, $this->size => $this->values['l']->id ] )
        ->assertSet( 'variant', $this->made['variants']['blue/l']->id )
        ->call( 'choose', 999, '1' )
        ->assertSet( 'variant', $this->made['variants']['blue/l']->id );
} );

it( 'resolves the variant when Livewire replaces the whole selection at once', function (): void {
    // Livewire 4 can send `selected` whole (no key) instead of
    // `selected.{id}`, for example while the selection is still empty.
    Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( 'selected', [ $this->colour => (string) $this->values['blue']->id, $this->size => (string) $this->values['l']->id ] )
        ->assertHasNoErrors()
        ->assertSet( 'variant', $this->made['variants']['blue/l']->id )
        ->set( 'selected', [ $this->colour => 'not-a-value', 999 => '1' ] )
        ->assertSet( 'selected', [] )
        ->assertSet( 'variant', null );
} );

it( 'clears a later choice an earlier one rules out, and announces it', function (): void {
    Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->size}", (string) $this->values['xl']->id )
        ->set( "selected.{$this->colour}", (string) $this->values['blue']->id )
        ->assertSet( "selected.{$this->size}", null )
        ->assertSet( 'variant', null )
        ->assertSet( 'selectionNotice', 'Size cleared: not available with Blue.' )
        ->assertSee( 'Size cleared: not available with Blue.' );
} );

it( 'opens on the variant in the query string', function (): void {
    $redM = $this->made['variants']['red/m'];

    Livewire::withQueryParams( [ 'variant' => $redM->id ] )
        ->test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->assertSet( 'variant', $redM->id )
        ->assertSet( "selected.{$this->colour}", $this->values['red']->id )
        ->assertSet( "selected.{$this->size}", $this->values['m']->id );
} );

it( 'ignores a variant in the query string from another product', function (): void {
    $other = makeVariableProduct( [ 'red/m' => [ 'price' => 1 ] ], [], 'OTHER' );

    Livewire::withQueryParams( [ 'variant' => $other['variants']['red/m']->id ] )
        ->test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->assertSet( 'variant', null )
        ->assertSet( 'selected', [] );
} );

it( 'refuses to add without a variant and shows a field error on the missing choice', function (): void {
    $component = Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['red']->id )
        ->call( 'addToCart' )
        ->assertHasErrors( [ "selected.{$this->size}" ] )
        ->assertHasNoErrors( [ 'variant', "selected.{$this->colour}" ] )
        ->assertSee( 'Choose a size to add this to your cart.' )
        ->assertSeeHtml( 'data-swatches-error' )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    // Focus moves to the group to fix.
    expect( collect( $component->effects['xjs'] ?? [] )->pluck( 'expression' )->implode( ' ' ) )
        ->toContain( '[data-variation-group=\\"' . $this->size . '\\"]' );

    expect( Cart::query()->first()?->items()->count() ?? 0 )->toBe( 0 );
} );

it( 'clears the missing-choice error once the shopper chooses', function (): void {
    Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->call( 'addToCart' )
        ->assertHasErrors( [ "selected.{$this->colour}" ] )
        ->call( 'choose', $this->colour, (string) $this->values['red']->id )
        ->assertHasNoErrors()
        ->assertDontSeeHtml( 'data-swatches-error' );
} );

it( 'keeps disabling add for a sold-out variant', function (): void {
    Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['red']->id )
        ->set( "selected.{$this->size}", (string) $this->values['xl']->id )
        ->assertSeeHtml( 'data-add-blocked' );
} );

it( 'uses a select for a group with more values than variation_select_after', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.product.variation_select_after', 2 );

    $form = 'ec-product-' . $this->made['product']->id;

    $component = Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->assertSeeHtml( '<select' )
        ->assertSeeHtml( 'id="' . $form . '-attribute-' . $this->size . '"' )
        ->assertSee( 'Select a size' )
        ->assertSeeHtml( 'data-variation-group="' . $this->colour . '"' );

    expect( $component->html() )->toContain( '$wire.choose( ' . $this->size . ', $event.target.value )' );

    $component->call( 'choose', $this->colour, (string) $this->values['blue']->id )
        ->assertSee( 'XL (Not available with your other choices)' )
        ->call( 'addToCart' )
        ->assertSee( 'Choose a size to add this to your cart.' )
        ->assertSeeHtml( 'id="' . $form . '-attribute-' . $this->size . '-error"' )
        ->assertSeeHtml( 'aria-invalid="true"' );
} );

it( 'keeps an attribute name capitalized in German', function ( string $locale ): void {
    app()->setLocale( $locale );

    Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->call( 'addToCart' )
        ->assertSee( 'Wähle eine Option für Colour, um den Artikel in den Warenkorb zu legen.' );
} )->with( [ 'de', 'de_AT', 'de-CH' ] );

it( 'announces a refused add in an alert and links the error to the missing group', function (): void {
    $form = 'ec-product-' . $this->made['product']->id;

    $component = Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['red']->id )
        ->call( 'addToCart' )
        ->assertSee( 'Not added to your cart: Choose a size to add this to your cart.' )
        ->assertSeeHtml( 'role="alert" wire:key="' . $form . '-add-error-1"' )
        ->assertSeeHtml( '<p id="' . $form . '-attribute-' . $this->size . '-error"' )
        ->assertSeeHtml( 'aria-describedby="' . $form . '-attribute-' . $this->size . '-error"' );

    // A second refusal gets a new alert, so it is announced again.
    $component->call( 'addToCart' )->assertSeeHtml( 'wire:key="' . $form . '-add-error-2"' );
} );

it( 'drops a value that belongs to another attribute or product', function (): void {
    Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['l']->id )
        ->assertSet( 'selected', [] )
        ->set( 'selected.999999', '1' )
        ->assertSet( 'selected', [] );
} );

it( 'shows the engine\'s reason when the variant sells out before adding', function (): void {
    $component = Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['red']->id )
        ->set( "selected.{$this->size}", (string) $this->values['m']->id )
        ->set( 'quantity', 9 )
        ->call( 'addToCart' )
        ->assertHasErrors( 'quantity' );

    expect( Cart::query()->first()?->items()->count() ?? 0 )->toBe( 0 );
} );

it( 'preselects a group with a single option', function (): void {
    $made = makeVariableProduct( [ 'red/m' => [ 'price' => 1000 ] ], [], 'SINGLE' );
    $made['values']['blue']->delete();
    $made['values']['l']->delete();
    $made['values']['xl']->delete();

    Livewire::test( VariableForm::class, [ 'product' => $made['product'] ] )
        ->assertSet( 'variant', $made['variants']['red/m']->id );
} );

it( 'follows a variant set from browser history or the client', function (): void {
    $blueM = $this->made['variants']['blue/m'];

    Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['red']->id )
        ->set( "selected.{$this->size}", (string) $this->values['l']->id )
        ->set( 'variant', $blueM->id )
        ->assertSet( "selected.{$this->colour}", $this->values['blue']->id )
        ->assertSet( "selected.{$this->size}", $this->values['m']->id )
        ->assertDispatched( 'ecommerce-product-variant-selected', productId: $this->made['product']->id, variantId: $blueM->id )
        ->set( 'variant', 999999 )
        ->assertSet( 'variant', null )
        ->assertSet( 'selected', [] );
} );

it( 'ignores a malformed variant in the query string', function ( mixed $variant ): void {
    Livewire::withQueryParams( [ 'variant' => $variant ] )
        ->test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->assertOk()
        ->assertSet( 'variant', null )
        ->assertSet( 'selected', [] )
        ->assertDontSeeHtml( 'data-add-blocked' );
} )->with( [
    'text'     => 'abc',
    'array'    => [ [ '1' ] ],
    'negative' => '-1',
    'decimal'  => '1.5',
    'empty'    => '',
] );

it( 'opens on a variant id sent as a numeric string', function (): void {
    $redM = $this->made['variants']['red/m'];

    Livewire::withQueryParams( [ 'variant' => (string) $redM->id ] )
        ->test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->assertSet( 'variant', $redM->id );
} );

it( 'clears a malformed variant set from the client', function (): void {
    Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( 'variant', $this->made['variants']['blue/m']->id )
        ->set( 'variant', 'abc' )
        ->assertSet( 'variant', null )
        ->assertSet( 'selected', [] );
} );
