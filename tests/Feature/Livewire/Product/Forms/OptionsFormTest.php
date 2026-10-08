<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductChild;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\OptionsForm;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Show;
use Livewire\Livewire;
use Tests\Fixtures\ProductTypes\EngravedProductType;

beforeEach( function (): void {
    app( ProductTypeRegistry::class )->register( EngravedProductType::KEY, new EngravedProductType() );

    $this->product = Product::factory()->create( [ 'type' => EngravedProductType::KEY, 'name' => 'Pocket watch' ] );
    ProductPrice::factory()->forPriceable( $this->product )->create( [ 'currency' => 'USD', 'price_amount' => 9900, 'compare_at_amount' => null ] );
} );

it( 'renders the type\'s fields from its option schema', function (): void {
    Livewire::test( OptionsForm::class, [ 'product' => $this->product ] )
        ->assertOk()
        ->assertSee( 'Hand engraved' )
        ->assertSee( 'Ships in 3 days.' )
        ->assertSee( 'Engraving' )
        ->assertSee( 'Script' )
        ->assertSee( 'Gift wrap' )
        ->assertDontSee( 'Dropped' )
        ->assertSet( 'options.engraving.font', 'serif' );
} );

it( 'is what the product page shows for a type with options and no registered form', function (): void {
    Livewire::test( Show::class, [ 'product' => $this->product ] )->assertSeeLivewire( OptionsForm::class );
} );

it( 'validates the fields with their own rules', function ( array $values, string $error ): void {
    $component = Livewire::test( OptionsForm::class, [ 'product' => $this->product ] );

    foreach ( $values as $key => $value ) {
        $component->set( $key, $value );
    }

    $component->call( 'addToCart' )->assertHasErrors( $error )->assertNotDispatched( 'ecommerce-cart-updated' );
} )->with( [
    'required'       => [ [], 'options.engraving.text' ],
    'too long'       => [ [ 'options.engraving.text' => str_repeat( 'x', 21 ) ], 'options.engraving.text' ],
    'unknown option' => [ [ 'options.engraving.text' => 'J.M.', 'options.engraving.font' => 'comic' ], 'options.engraving.font' ],
] );

it( 'adds the product with the values as line options', function (): void {
    Livewire::test( OptionsForm::class, [ 'product' => $this->product ] )
        ->set( 'options.engraving.text', 'J.M.' )
        ->set( 'options.engraving.font', 'script' )
        ->set( 'options.gift_wrap', true )
        ->call( 'addToCart' )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated', count: 1 );

    expect( Cart::query()->sole()->items()->sole()->options )->toMatchArray( [
        'engraving' => [ 'text' => 'J.M.', 'font' => 'script' ],
        'gift_wrap' => true,
    ] );
} );

it( 'adds separately-added fields as their own lines', function (): void {
    $group = Product::factory()->grouped()->create();
    $mug   = makeProduct( 900, [ 'name' => 'Mug' ] );
    $row   = ProductChild::factory()->create( [ 'parent_product_id' => $group->id, 'child_product_id' => $mug->id ] );

    Livewire::test( OptionsForm::class, [ 'product' => $group ] )
        ->assertSee( 'Mug' )
        ->call( 'addToCart' )
        ->assertHasErrors( 'options' )
        ->set( "options.items.{$row->id}", 3 )
        ->call( 'addToCart' )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated', count: 3 );

    expect( Cart::query()->sole()->items()->sole()->product_id )->toBe( $mug->id );
} );
