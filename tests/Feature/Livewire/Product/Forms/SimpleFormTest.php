<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\SimpleForm;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

afterEach( function (): void {
    Model::preventLazyLoading( false );
} );

it( 'renders the quantity stepper and an enabled button', function (): void {
    Livewire::test( SimpleForm::class, [ 'product' => makeProduct() ] )
        ->assertOk()
        ->assertSet( 'quantity', 1 )
        ->assertSeeHtml( 'data-quantity=' )
        ->assertSeeHtml( 'aria-label="Increase quantity"' )
        ->assertSee( 'Add to cart' )
        ->assertDontSeeHtml( 'data-add-blocked' );
} );

it( 'adds the chosen quantity, announces it, and opens the cart drawer', function (): void {
    $mug = makeProduct( 1200, [ 'name' => 'Mug' ] );

    Livewire::test( SimpleForm::class, [ 'product' => $mug ] )
        ->set( 'quantity', 2 )
        ->call( 'addToCart' )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated', count: 2 )
        ->assertDispatched( 'ecommerce-cart-open' )
        ->assertSet( 'announcement', 'Added to your cart.' );

    expect( Cart::query()->sole()->items()->sole()->quantity )->toBe( 2 );
} );

it( 'toasts instead of opening the drawer when configured', function ( string $mode, bool $drawer, bool $toast ): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.cart.after_add', $mode );

    $component = Livewire::test( SimpleForm::class, [ 'product' => makeProduct() ] )->call( 'addToCart' );

    $drawer ? $component->assertDispatched( 'ecommerce-cart-open' ) : $component->assertNotDispatched( 'ecommerce-cart-open' );

    expect( str_contains( json_encode( $component->effects['xjs'] ?? [] ), 'Added to your cart' ) )->toBe( $toast );
} )->with( [
    'toast' => [ 'toast', false, true ],
    'none'  => [ 'none', false, false ],
] );

it( 'rejects a bad quantity', function ( mixed $quantity, string $message ): void {
    Livewire::test( SimpleForm::class, [ 'product' => makeProduct() ] )
        ->set( 'quantity', $quantity )
        ->call( 'addToCart' )
        ->assertHasErrors( 'quantity' )
        ->assertSee( $message )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    expect( Cart::query()->count() )->toBe( 0 );
} )->with( [
    'empty'    => [ '', 'Enter a quantity.' ],
    'zero'     => [ 0, 'Add at least 1.' ],
    'fraction' => [ '1.5', 'Enter a whole number.' ],
    'too many' => [ 10001, 'You can add at most 10000 at once.' ],
] );

it( 'shows the engine\'s reason on the quantity field', function (): void {
    $product = makeProduct();
    setStock( $product, 2 );

    Livewire::test( SimpleForm::class, [ 'product' => $product ] )
        ->set( 'quantity', 5 )
        ->call( 'addToCart' )
        ->assertHasErrors( 'quantity' )
        ->assertSeeHtml( 'data-quantity-error' )
        ->assertSeeHtml( 'aria-invalid="true"' )
        ->assertNotDispatched( 'ecommerce-cart-updated' );
} );

it( 'disables the button for an out-of-stock or unpriced product', function ( Closure $make, string $reason ): void {
    Livewire::test( SimpleForm::class, [ 'product' => $make() ] )
        ->assertSee( $reason )
        ->assertSeeHtml( 'data-add-blocked' );
} )->with( [
    'out of stock' => [ function (): Product {
        $product = makeProduct();
        setStock( $product, 0 );

        return $product;
    }, 'This product is out of stock.' ],
    'unpriced'     => [ fn (): Product => Product::factory()->create(), 'This product isn\'t available to buy right now.' ],
] );

it( 'throttles adds per cart', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.cart.mutate.per_cart', 1 );

    $component = Livewire::test( SimpleForm::class, [ 'product' => makeProduct() ] )
        ->call( 'addToCart' )
        ->call( 'addToCart' )
        ->call( 'addToCart' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Too many attempts' );
} );

it( 'adds to the cart in hosts that prevent lazy loading', function (): void {
    Model::preventLazyLoading();

    Livewire::test( SimpleForm::class, [ 'product' => makeProduct() ] )
        ->call( 'addToCart' )
        ->assertDispatched( 'ecommerce-cart-updated', count: 1 );
} );
