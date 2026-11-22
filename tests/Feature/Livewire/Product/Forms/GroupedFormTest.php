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

it( 'lays the products out in a design-system table with a quantity input each', function (): void {
    $html = Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->assertSeeHtml( 'class="ec-purchase-form" data-purchase-form="grouped"' )
        ->assertSeeHtml( 'data-grouped-table' )
        ->assertSeeHtml( '<table' )
        ->assertSee( 'Item' )
        ->assertSee( 'Price' )
        ->assertSee( 'Qty' )
        ->assertSeeHtml( 'type="number"' )
        ->assertSeeHtml( 'wire:model.live.change="quantities.' . $this->cupRow->id . '"' )
        ->assertSeeHtml( 'data-grouped-quantity="' . $this->cupRow->id . '"' )
        ->assertSee( 'Add selected to cart' )
        ->html();

    expect( $html )->not->toContain( 'divide-base-content' )
        ->not->toContain( 'text-base-content' )
        ->not->toContain( '--color-error' );
} );

it( 'shows an out-of-stock product with its badge and a dash instead of a quantity', function (): void {
    setStock( $this->cup, 0 );

    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->assertSee( 'Out of stock' )
        ->assertSee( 'Cup can\'t be added right now.' )
        ->assertDontSeeHtml( 'data-grouped-quantity="' . $this->cupRow->id . '"' )
        ->assertSeeHtml( 'data-grouped-quantity="' . $this->potRow->id . '"' );
} );

it( 'keeps a running count and total of the chosen quantities', function (): void {
    $component = Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->assertDontSeeHtml( 'data-grouped-total' );

    $component->set( "quantities.{$this->cupRow->id}", 2 )
        ->assertSee( '2 items' )
        ->assertSeeHtml( '<bdi class="ec-grouped-summary__total" data-grouped-total>$16.00</bdi>' )
        ->set( "quantities.{$this->potRow->id}", 1 )
        ->assertSee( '3 items' )
        ->assertSee( '$46.00' )
        ->set( "quantities.{$this->potRow->id}", 0 )
        ->assertSee( '2 items' )
        ->assertSee( '$16.00' );
} );

it( 'hides the total while a filled-in quantity is invalid', function ( mixed $quantity ): void {
    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->set( "quantities.{$this->cupRow->id}", 2 )
        ->set( "quantities.{$this->potRow->id}", $quantity )
        ->assertSee( '2 items' )
        ->assertDontSeeHtml( 'data-grouped-total' );
} )->with( [
    'decimal'  => '1.5',
    'text'     => 'abc',
    'negative' => '-1',
] );

it( 'skips an empty quantity without hiding the total', function (): void {
    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->set( "quantities.{$this->cupRow->id}", 2 )
        ->set( "quantities.{$this->potRow->id}", '' )
        ->assertSee( '$16.00' );
} );

it( 'leaves the total out when a quantity is over the line limit', function (): void {
    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->set( "quantities.{$this->cupRow->id}", 1 )
        ->set( "quantities.{$this->potRow->id}", 1000000 )
        ->assertSee( '1 item' )
        ->assertDontSeeHtml( 'data-grouped-total' );
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
        ->assertSee( 'Choose a quantity for at least one product.' )
        ->assertSee( 'Not added to your cart: Choose a quantity for at least one product.' )
        ->assertSeeHtml( 'aria-describedby="ec-product-' . $this->group->id . '-quantities-error"' );
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
        ->assertDispatched( 'ecommerce-cart-updated', count: 1 )
        // The refused quantity stays beside its error; the added one is cleared.
        ->assertSet( "quantities.{$this->potRow->id}", 5 )
        ->assertSet( "quantities.{$this->cupRow->id}", null );

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

it( 'prices the rows and the running total for the signed-in customer, as the cart will', function (): void {
    // A customer-group pricing satellite: half price for any known customer.
    addFilter( 'ap.ecommerce.pricing.priceDisplay', static fn ( Money\Money $price, mixed $subject, ?ArtisanPackUI\Ecommerce\Models\Customer $customer ): Money\Money => null === $customer ? $price : $price->divide( 2 ) );

    shopper();
    app()->forgetScopedInstances();

    Livewire::test( GroupedForm::class, [ 'product' => $this->group ] )
        ->assertSee( '$15.00' )
        ->assertDontSee( '$30.00' )
        ->set( 'quantities.' . $this->potRow->id, 1 )
        ->set( 'quantities.' . $this->cupRow->id, 2 )
        ->assertSeeHtml( 'data-grouped-total>$23.00</bdi>' );

    removeAllFilters( 'ap.ecommerce.pricing.priceDisplay' );
} );
