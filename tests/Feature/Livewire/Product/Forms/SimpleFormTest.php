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
        ->assertSeeHtml( 'class="ec-purchase-form" data-purchase-form="simple"' )
        ->assertSeeHtml( 'data-add-to-cart-row' )
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
        ->assertSeeHtml( 'data-scope="input" data-part="error"' )
        ->assertSeeHtml( 'aria-invalid="true"' )
        ->assertNotDispatched( 'ecommerce-cart-updated' );
} );

it( 'disables the button for an out-of-stock or unpriced product', function ( Closure $make, string $reason ): void {
    withoutBackInStock();

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

describe( 'add-to-cart row (#86)', function (): void {
    afterEach( function (): void {
        removeAllFilters( 'ap.ecommerceStorefrontLivewire.product.addToCartActions' );
    } );

    it( 'shows the line price on the button and follows the quantity live', function (): void {
        Livewire::test( SimpleForm::class, [ 'product' => makeProduct( 2800 ) ] )
            ->assertSeeHtml( 'data-add-to-cart-row' )
            ->assertSeeHtml( 'wire:model.live.change="quantity"' )
            ->assertSeeHtmlInOrder( [ 'Add to cart', '<span aria-hidden="true">·</span>', '<bdi>$28.00</bdi>' ] )
            ->set( 'quantity', 3 )
            ->assertSee( '$84.00' );
    } );

    it( 'leaves the line price out for a quantity it cannot price', function ( mixed $quantity ): void {
        Livewire::test( SimpleForm::class, [ 'product' => makeProduct( 2800 ) ] )
            ->set( 'quantity', $quantity )
            ->assertDontSeeHtml( 'data-add-to-cart-price' );
    } )->with( [ 'empty' => '', 'zero' => 0, 'text' => 'two', 'too many' => 100000 ] );

    it( 'leaves the line price out and binds the quantity deferred when the product cannot be bought', function (): void {
        withoutBackInStock();

        $product = makeProduct( 2800 );
        setStock( $product, 0 );

        Livewire::test( SimpleForm::class, [ 'product' => $product ] )
            ->assertDontSeeHtml( 'data-add-to-cart-price' )
            ->assertSeeHtml( 'wire:model="quantity"' )
            ->assertSeeHtml( 'class="ec-add-to-cart__blocked"' );
    } );

    it( 'renders extra row buttons from the filter and drops anything else', function (): void {
        addFilter( 'ap.ecommerceStorefrontLivewire.product.addToCartActions', static fn ( array $actions, Product $product ): array => $actions + [
            'wishlist' => new Illuminate\Support\HtmlString( '<button type="button" data-wishlist="' . $product->id . '">Save</button>' ),
            'missing'  => 'no-such-view',
            'junk'     => 42,
        ] );

        $product = makeProduct();

        Livewire::test( SimpleForm::class, [ 'product' => $product ] )
            ->assertSeeHtml( 'data-add-to-cart-actions' )
            ->assertSeeHtml( 'data-wishlist="' . $product->id . '"' );
    } );

    it( 'has no actions box without row buttons', function (): void {
        Livewire::test( SimpleForm::class, [ 'product' => makeProduct() ] )
            ->assertDontSeeHtml( 'data-add-to-cart-actions' );
    } );

    it( 'uses no DaisyUI or Tailwind utility classes', function (): void {
        Livewire::test( SimpleForm::class, [ 'product' => makeProduct() ] )
            ->assertDontSeeHtml( 'class="sr-only"' )
            ->assertDontSeeHtml( 'base-content' )
            ->assertDontSeeHtml( 'flex-wrap' );
    } );
} );
