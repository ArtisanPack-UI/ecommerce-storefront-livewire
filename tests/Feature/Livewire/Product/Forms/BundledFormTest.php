<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductChild;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\BundledForm;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach( function (): void {
    $this->bundle = Product::factory()->bundled()->create( [ 'name' => 'Starter kit' ] );
    ProductPrice::factory()->forPriceable( $this->bundle )->create( [ 'currency' => 'USD', 'price_amount' => 4500, 'compare_at_amount' => null ] );

    $this->brush = makeProduct( 1000, [ 'name' => 'Brush' ] );
    $this->paint = makeProduct( 900, [ 'name' => 'Paint' ] );

    ProductChild::factory()->create( [ 'parent_product_id' => $this->bundle->id, 'child_product_id' => $this->brush->id, 'quantity' => 2 ] );
    ProductChild::factory()->create( [ 'parent_product_id' => $this->bundle->id, 'child_product_id' => $this->paint->id, 'quantity' => 3, 'position' => 1 ] );
} );

it( 'lists what the bundle includes as checked, disabled checkboxes', function (): void {
    $html = Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
        ->assertOk()
        ->assertSeeHtml( 'class="ec-purchase-form" data-purchase-form="bundled"' )
        ->assertSee( 'In this set' )
        ->assertSee( '2 × Brush' )
        ->assertSee( '3 × Paint' )
        ->assertSee( 'Included' )
        ->assertSeeHtml( 'type="checkbox"' )
        ->assertSeeHtml( 'data-bundle-item=' )
        ->assertSee( 'Add set to cart' )
        ->html();

    expect( substr_count( $html, 'type="checkbox"' ) )->toBe( 2 )
        ->and( preg_match_all( '/<input data-bundle-item=.*?\/>/s', $html, $inputs ) )->toBe( 2 );

    foreach ( $inputs[0] as $input ) {
        expect( $input )->toContain( 'checked' )->toContain( 'disabled' );
    }

    expect( $html )->not->toContain( 'text-success' )
        ->not->toContain( 'text-sm' )
        ->not->toContain( 'flex-col' );
} );

it( 'names a member once without a count when the bundle holds one of it', function (): void {
    ProductChild::query()->where( 'child_product_id', $this->brush->id )->update( [ 'quantity' => 1 ] );

    Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
        ->assertSee( 'Brush' )
        ->assertDontSee( '1 × Brush' );
} );

it( 'shows the total with the saving against the members bought on their own, following the quantity', function (): void {
    // Members: 2 × $10.00 + 3 × $9.00 = $47.00 against the bundle's $45.00.
    Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
        ->assertSeeHtml( 'data-bundle-total' )
        ->assertSee( '$45.00' )
        ->assertSee( 'You save $2.00' )
        ->assertSeeHtml( 'wire:model.live.change="quantity"' )
        ->assertDontSeeHtml( 'data-add-to-cart-price' )
        ->set( 'quantity', 2 )
        ->assertSee( '$90.00' )
        ->assertSee( 'You save $4.00' );
} );

it( 'shows no saving when the members cost no more on their own', function (): void {
    ProductPrice::query()->where( 'priceable_id', $this->bundle->id )->where( 'priceable_type', $this->bundle->getMorphClass() )->update( [ 'price_amount' => 4700 ] );

    Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
        ->assertSee( '$47.00' )
        ->assertDontSeeHtml( 'data-bundle-saving' );
} );

it( 'hides the total while the quantity is invalid or the bundle can\'t be bought', function (): void {
    Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
        ->set( 'quantity', 'abc' )
        ->assertDontSeeHtml( 'data-bundle-total' );

    setStock( $this->paint, 1 );

    Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
        ->assertDontSeeHtml( 'data-bundle-total' );
} );

it( 'uses the block\'s button text in place of "Add set to cart"', function (): void {
    Livewire::test( BundledForm::class, [ 'product' => $this->bundle, 'buttonText' => 'Buy the kit' ] )
        ->assertSee( 'Buy the kit' )
        ->assertDontSee( 'Add set to cart' );
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
    withoutBackInStock();
    setStock( $this->paint, 1 );

    Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
        ->assertSee( 'This product is out of stock.' )
        ->call( 'addToCart' )
        ->assertHasErrors( 'quantity' )
        ->assertNotDispatched( 'ecommerce-cart-updated' );
} );

it( 'counts a member pinned to a variant at the variant\'s price, without lazy loading', function (): void {
    $mug     = Product::factory()->variable()->create( [ 'name' => 'Mug' ] );
    $variant = ProductVariant::factory()->create( [ 'product_id' => $mug->id, 'name' => 'Blue' ] );
    ProductPrice::factory()->forPriceable( $variant )->create( [ 'currency' => 'USD', 'price_amount' => 1000, 'compare_at_amount' => null ] );
    ProductChild::factory()->create( [ 'parent_product_id' => $this->bundle->id, 'child_product_id' => $mug->id, 'child_variant_id' => $variant->id, 'position' => 2 ] );

    Model::preventLazyLoading();

    try {
        // Members: $20.00 + $27.00 + $10.00 = $57.00 against $45.00.
        Livewire::test( BundledForm::class, [ 'product' => $this->bundle ] )
            ->assertSee( 'Mug — Blue' )
            ->assertSee( 'You save $12.00' );
    } finally {
        Model::preventLazyLoading( false );
    }
} );

