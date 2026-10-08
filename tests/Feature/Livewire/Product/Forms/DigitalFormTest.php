<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\DigitalForm;
use Livewire\Livewire;

/**
 * A priced digital product.
 *
 * @param  array<string, mixed>  $meta  Product meta.
 */
function makeDigital( array $meta = [] ): Product
{
    $product = Product::factory()->digital()->create( [ 'name' => 'Field guide', 'meta' => $meta ] );
    ProductPrice::factory()->forPriceable( $product )->create( [ 'currency' => 'USD', 'price_amount' => 1500, 'compare_at_amount' => null ] );

    return $product;
}

it( 'says nothing is shipped and lists the downloads', function (): void {
    $product = makeDigital();
    DigitalFile::factory()->create( [ 'product_id' => $product->id, 'label' => 'Guide (PDF)' ] );
    DigitalFile::factory()->create( [ 'product_id' => $product->id, 'label' => 'Old edition', 'archived_at' => now() ] );

    Livewire::test( DigitalForm::class, [ 'product' => $product ] )
        ->assertOk()
        ->assertSee( 'Delivered digitally — nothing is shipped.' )
        ->assertSee( 'Includes: Guide (PDF)' )
        ->assertDontSee( 'Old edition' )
        ->assertSee( 'Download up to 5 times within 30 days of purchase.' );
} );

it( 'uses the product\'s own download limits and licence notes', function (): void {
    Livewire::test( DigitalForm::class, [ 'product' => makeDigital( [ 'digital' => [ 'download_limit' => 0, 'download_expiry_days' => 0 ], 'licensing' => [ 'enabled' => true, 'activations_limit' => 3 ] ] ) ] )
        ->assertSee( 'Download as often as you like.' )
        ->assertSee( 'Includes a licence key for 3 activations per copy.' );
} );

it( 'adds the product', function (): void {
    $product = makeDigital();

    Livewire::test( DigitalForm::class, [ 'product' => $product ] )
        ->call( 'addToCart' )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated', count: 1 );

    expect( Cart::query()->sole()->items()->sole()->product_id )->toBe( $product->id );
} );

it( 'requires a licence type when the product offers several, and puts it on the line', function (): void {
    $product = makeDigital( [ 'licensing' => [ 'types' => [ 'personal', 'team' ] ] ] );

    $component = Livewire::test( DigitalForm::class, [ 'product' => $product ] )
        ->assertSee( 'Licence' )
        ->call( 'addToCart' )
        ->assertHasErrors( 'licenseType' )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    $component->set( 'licenseType', 'team' )->call( 'addToCart' )->assertHasNoErrors();

    expect( Cart::query()->sole()->items()->sole()->options )->toMatchArray( [ 'license_type' => 'team' ] );
} );

it( 'preselects the only licence type and shows the engine\'s refusal of an unknown one', function (): void {
    $product = makeDigital( [ 'licensing' => [ 'types' => [ 'personal' ] ] ] );

    Livewire::test( DigitalForm::class, [ 'product' => $product ] )
        ->assertSet( 'licenseType', 'personal' )
        ->set( 'licenseType', 'pirate' )
        ->call( 'addToCart' )
        ->assertHasErrors( 'licenseType' );
} );
