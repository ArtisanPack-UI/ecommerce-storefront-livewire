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

it( 'renders one swatch group per variation attribute and blocks adding until a variant is matched', function (): void {
    Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->assertOk()
        ->assertSeeHtml( 'data-variation-group="' . $this->colour . '"' )
        ->assertSeeHtml( 'data-variation-group="' . $this->size . '"' )
        ->assertSeeHtml( 'background-color: #ff0000' )
        ->assertSeeHtml( 'type="radio"' )
        ->assertSee( 'Choose an option for Colour.' )
        ->assertSeeHtml( 'data-add-blocked' );
} );

it( 'disables a combination that does not exist after choosing blue, with a reason', function (): void {
    $html = Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['blue']->id )
        ->assertSee( 'Choose an option for Size.' )
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

it( 'refuses to add without a variant and names the missing choice', function (): void {
    Livewire::test( VariableForm::class, [ 'product' => $this->made['product'] ] )
        ->set( "selected.{$this->colour}", (string) $this->values['red']->id )
        ->call( 'addToCart' )
        ->assertHasErrors( [ 'variant' ] )
        ->assertSee( 'Choose an option for Size.' )
        ->assertNotDispatched( 'ecommerce-cart-updated' );
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
