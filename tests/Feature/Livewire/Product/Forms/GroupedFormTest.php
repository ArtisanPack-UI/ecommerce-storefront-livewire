<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductChild;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\GroupedForm;
use Livewire\Livewire;

beforeEach( function (): void {
    $this->group = Product::factory()->grouped()->create( [ 'name' => 'Tea set' ] );
    $this->pot   = makeProduct( 3000, [ 'name' => 'Teapot' ] );
    $this->cup   = makeProduct( 800, [ 'name' => 'Cup' ] );
    $this->draft = Product::factory()->draft()->create( [ 'name' => 'Draft saucer' ] );

    $this->potRow   = ProductChild::factory()->create( [ 'parent_product_id' => $this->group->id, 'child_product_id' => $this->pot->id, 'position' => 0 ] );
    $this->cupRow   = ProductChild::factory()->create( [ 'parent_product_id' => $this->group->id, 'child_product_id' => $this->cup->id, 'position' => 1 ] );
    $this->draftRow = ProductChild::factory()->create( [ 'parent_product_id' => $this->group->id, 'child_product_id' => $this->draft->id, 'position' => 2 ] );
} );

it( 'lists the visible products with their own prices, stock, and quantities', function (): void {
    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->assertOk()
        ->assertSee( 'Teapot' )
        ->assertSee( '$30.00' )
        ->assertSee( 'Cup' )
        ->assertDontSee( 'Draft saucer' )
        ->assertSeeHtml( 'data-grouped-child="' . $this->potRow->id . '"' )
        ->assertSee( 'Quantity of Cup' );
} );

it( 'adds 2 of one product and 1 of another as separate lines', function (): void {
    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->set( "quantities.{$this->cupRow->id}", 2 )
        ->set( "quantities.{$this->potRow->id}", 1 )
        ->call( 'addToCart' )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated', count: 3 )
        ->assertSet( 'quantities', [] );

    expect( Cart::query()->sole()->items()->pluck( 'quantity', 'product_id' )->all() )
        ->toEqualCanonicalizing( [ $this->cup->id => 2, $this->pot->id => 1 ] );
} );

it( 'asks for at least one quantity', function (): void {
    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->call( 'addToCart' )
        ->assertHasErrors( 'quantities' )
        ->assertSee( 'Choose a quantity for at least one product.' );
} );

it( 'rejects a bad quantity', function (): void {
    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->set( "quantities.{$this->cupRow->id}", -1 )
        ->call( 'addToCart' )
        ->assertHasErrors( "quantities.{$this->cupRow->id}" );
} );

it( 'shows the engine\'s reason next to the product it refused and adds the rest', function (): void {
    setStock( $this->pot, 1 );

    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->set( "quantities.{$this->potRow->id}", 5 )
        ->set( "quantities.{$this->cupRow->id}", 1 )
        ->call( 'addToCart' )
        ->assertHasErrors( "quantities.{$this->potRow->id}" )
        ->assertDispatched( 'ecommerce-cart-updated', count: 1 );

    expect( Cart::query()->sole()->items()->sole()->product_id )->toBe( $this->cup->id );
} );

it( 'ignores a child id that is not in the group', function (): void {
    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->set( "quantities.{$this->draftRow->id}", 2 )
        ->set( 'quantities.999999', 2 )
        ->call( 'addToCart' )
        ->assertHasErrors( 'quantities' );

    expect( Cart::query()->count() )->toBe( 0 );
} );
