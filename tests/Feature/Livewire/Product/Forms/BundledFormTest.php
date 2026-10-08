<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductChild;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\BundledForm;
use Livewire\Livewire;

beforeEach( function (): void {
    $this->bundle = Product::factory()->bundled()->create( [ 'name' => 'Starter kit' ] );
    ProductPrice::factory()->forPriceable( $this->bundle )->create( [ 'currency' => 'USD', 'price_amount' => 4500, 'compare_at_amount' => null ] );

    $this->brush = makeProduct( 1000, [ 'name' => 'Brush' ] );
    $this->paint = makeProduct( 900, [ 'name' => 'Paint' ] );

    ProductChild::factory()->create( [ 'parent_product_id' => $this->bundle->id, 'child_product_id' => $this->brush->id, 'quantity' => 2 ] );
    ProductChild::factory()->create( [ 'parent_product_id' => $this->bundle->id, 'child_product_id' => $this->paint->id, 'quantity' => 3, 'position' => 1 ] );
} );

it( 'lists what the bundle includes', function (): void {
    Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
        ->assertOk()
        ->assertSee( 'This bundle includes' )
        ->assertSee( '2 × Brush' )
        ->assertSee( '3 × Paint' );
} );

it( 'adds the bundle as one line at the bundle price', function (): void {
    Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
        ->set( 'quantity', 2 )
        ->call( 'addToCart' )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated', count: 2 );

    $item = Cart::query()->sole()->items()->sole();

    expect( $item->product_id )->toBe( $this->bundle->id )
        ->and( (int) $item->unit_price_amount )->toBe( 4500 );
} );

it( 'is blocked when a member is out of stock', function (): void {
    setStock( $this->paint, 1 );

    Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
        ->assertSee( 'This product is out of stock.' )
        ->call( 'addToCart' )
        ->assertHasErrors( 'quantity' )
        ->assertNotDispatched( 'ecommerce-cart-updated' );
} );
