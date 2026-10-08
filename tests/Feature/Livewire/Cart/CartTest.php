<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Coupon;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Models\PromotionAction;
use ArtisanPackUI\Ecommerce\Models\ShippingMethod;
use ArtisanPackUI\Ecommerce\Models\ShippingZone;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Index;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\RelatedProducts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * The shopper's cart, holding `$quantity` of each product.
 *
 * @param  array<int, Product>  $products  Products to add.
 */
function shopperCartWith( array $products, int $quantity = 1 ): Cart
{
    $cart = app( StorefrontCart::class )->current( true );

    foreach ( $products as $product ) {
        app( StorefrontCartService::class )->addItem( $cart, $product->id, null, $quantity );
    }

    return $cart->refresh();
}

/**
 * A coupon `$code` for `$percent`% off the cart, from a promotion called `$name`.
 */
function percentCoupon( string $code, int $percent, string $name = 'Spring sale' ): Coupon
{
    $promotion = Promotion::factory()->coupon()->create( [ 'name' => $name ] );
    PromotionAction::factory()->create( [ 'promotion_id' => $promotion->id, 'type' => 'percent-off-cart', 'config' => [ 'percent' => $percent ] ] );

    return Coupon::factory()->create( [ 'promotion_id' => $promotion->id, 'code' => $code ] );
}

/**
 * A US zone with flat-rate shipping methods, label => amount.
 *
 * @param  array<string, int>  $methods  Methods.
 */
function usShipping( array $methods = [ 'Standard' => 500 ] ): void
{
    $zone = ShippingZone::factory()->create( [ 'name' => 'US', 'country_codes' => [ 'US' ] ] );

    foreach ( array_keys( $methods ) as $position => $label ) {
        ShippingMethod::factory()->create( [ 'zone_id' => $zone->id, 'key' => 'flat-rate', 'label' => $label, 'config' => [ 'amount' => $methods[ $label ] ], 'position' => $position ] );
    }
}

it( 'shows an empty cart with a way back to the catalog', function (): void {
    Livewire::test( Index::class )
        ->assertOk()
        ->assertSee( 'Your cart is empty' )
        ->assertSee( 'Continue shopping' )
        ->assertDontSeeHtml( 'data-cart-summary' );
} );

it( 'lists the lines and the totals', function (): void {
    $made = makeVariableProduct( [ 'red/m' => [ 'price' => 2000 ] ] );
    $mug  = makeProduct( 1250, [ 'name' => 'Mug' ] );

    $cart = shopperCartWith( [ $mug ], 2 );
    app( StorefrontCartService::class )->addItem( $cart, $made['product']->id, $made['variants']['red/m']->id, 1 );

    Livewire::test( Index::class )
        ->assertSee( 'Mug' )
        ->assertSee( 'Linen shirt' )
        ->assertSee( 'Red / M' )
        ->assertSee( '$12.50' )
        ->assertSee( '$25.00' )
        ->assertSeeHtml( 'aria-label="Increase quantity of Mug"' )
        ->assertSeeHtml( 'aria-label="Remove Mug"' )
        ->assertSee( 'Order summary' )
        ->assertSee( '$45.00' )
        ->assertSeeHtml( 'data-cart-shipping="checkout"' )
        ->assertSeeHtml( 'data-cart-tax="estimated"' )
        ->assertSee( 'Tax (estimated)' )
        ->assertSee( 'Calculated at checkout' )
        ->assertSeeHtml( 'data-cart-checkout' )
        ->assertDontSeeHtml( 'data-cart-checkout-blocked' )
        ->assertSeeLivewire( RelatedProducts::class );
} );

it( 'changes a quantity and announces the new total', function (): void {
    $mug  = makeProduct( 1000, [ 'name' => 'Mug' ] );
    $cart = shopperCartWith( [ $mug ] );
    $line = $cart->items()->sole();

    Livewire::test( Index::class )
        ->assertSet( 'quantities.' . $line->id, 1 )
        ->set( 'quantities.' . $line->id, 3 )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated', count: 3 )
        ->assertSet( 'announcement', 'Cart updated. Total: $30.00.' )
        ->assertSee( '$30.00' );

    expect( $line->refresh()->quantity )->toBe( 3 );
} );

it( 'rejects a bad quantity', function ( mixed $quantity, string $message ): void {
    $cart = shopperCartWith( [ makeProduct() ] );
    $line = $cart->items()->sole();

    Livewire::test( Index::class )
        ->set( 'quantities.' . $line->id, $quantity )
        ->assertHasErrors( 'quantities.' . $line->id )
        ->assertSee( $message )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    expect( $line->refresh()->quantity )->toBe( 1 );
} )->with( [
    'empty'    => [ '', 'Enter a quantity.' ],
    'negative' => [ -1, 'Enter 0 or more.' ],
    'fraction' => [ '1.5', 'Enter a whole number.' ],
    'too many' => [ 10001, 'You can add at most 10000 at once.' ],
] );

it( 'shows the engine\'s refusal and puts the quantity back', function (): void {
    $mug = makeProduct( 1000, [ 'name' => 'Mug' ] );
    setStock( $mug, 2 );

    $line = shopperCartWith( [ $mug ] )->items()->sole();

    Livewire::test( Index::class )
        ->set( 'quantities.' . $line->id, 5 )
        ->assertHasErrors( 'quantities.' . $line->id )
        ->assertSet( 'quantities.' . $line->id, 1 )
        ->assertSeeHtml( 'data-quantity-error' )
        ->assertNotDispatched( 'ecommerce-cart-updated' );
} );

it( 'removes a line at quantity 0 or with Remove, and can undo it', function (): void {
    $mug  = makeProduct( 1000, [ 'name' => 'Mug' ] );
    $line = shopperCartWith( [ $mug ], 2 )->items()->sole();

    $component = Livewire::test( Index::class )
        ->call( 'removeLine', $line->id )
        ->assertDispatched( 'ecommerce-cart-updated', count: 0 )
        ->assertSet( 'removedLine.name', 'Mug' )
        ->assertSee( 'Mug was removed from your cart.' )
        ->assertSee( 'Undo' )
        ->assertSee( 'Your cart is empty' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Removed from your cart' );

    $component->call( 'undoRemove' )
        ->assertSet( 'removedLine', null )
        ->assertDispatched( 'ecommerce-cart-updated', count: 2 )
        ->assertSee( 'Mug is back in your cart.' );

    $restored = Cart::query()->sole()->items()->sole();

    $component->set( 'quantities.' . $restored->id, 0 )
        ->assertSet( 'removedLine.quantity', 2 );

    expect( Cart::query()->sole()->items()->count() )->toBe( 0 );
} );

it( 'ignores another cart\'s line', function (): void {
    shopperCartWith( [ makeProduct() ] );

    $other = app( StorefrontCartService::class )->create( 'USD' );
    $line  = app( StorefrontCartService::class )->addItem( $other, makeProduct()->id, null, 1 );

    Livewire::test( Index::class )
        ->call( 'removeLine', $line->id )
        ->set( 'quantities.' . $line->id, 4 )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    expect( $line->refresh()->quantity )->toBe( 1 );
} );

it( 'applies a coupon and shows its discount by promotion', function (): void {
    percentCoupon( 'SAVE10', 10, 'Spring sale' );
    shopperCartWith( [ makeProduct( 5000 ) ] );

    Livewire::test( Index::class )
        ->set( 'couponCode', 'save10' )
        ->call( 'applyCoupon' )
        ->assertHasNoErrors()
        ->assertSet( 'couponCode', '' )
        ->assertSeeHtml( 'data-cart-coupon-code' )
        ->assertSee( 'SAVE10' )
        ->assertSee( 'Spring sale' )
        ->assertSee( '$5.00' )
        ->assertSee( '$45.00' )
        ->assertSet( 'announcement', 'Coupon applied. Total: $45.00.' )
        ->call( 'removeCoupon', 'SAVE10' )
        ->assertDontSee( 'Spring sale' )
        ->assertSet( 'announcement', 'Coupon removed. Total: $50.00.' );
} );

it( 'shows the engine\'s coupon messages inline', function ( string $code, string $message ): void {
    shopperCartWith( [ makeProduct() ] );

    Livewire::test( Index::class )
        ->set( 'couponCode', $code )
        ->call( 'applyCoupon' )
        ->assertHasErrors( 'couponCode' )
        ->assertSee( $message );
} )->with( [
    'empty'   => [ '  ', 'Enter a coupon code.' ],
    'unknown' => [ 'NOPE', 'That coupon code is not valid.' ],
    'long'    => [ str_repeat( 'A', 65 ), 'That coupon code is not valid.' ],
] );

it( 'says so when removing a coupon that isn\'t applied', function (): void {
    shopperCartWith( [ makeProduct() ] );

    Livewire::test( Index::class )
        ->call( 'removeCoupon', 'OTHER' )
        ->assertHasErrors( 'couponCode' )
        ->assertSee( 'That coupon is not applied to this cart.' );
} );

it( 'rate limits coupon attempts', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.coupon.attempt.per_cart', 2 );

    shopperCartWith( [ makeProduct() ] );

    $component = Livewire::test( Index::class );

    foreach ( range( 1, 3 ) as $attempt ) {
        $component->set( 'couponCode', 'GUESS' . $attempt )->call( 'applyCoupon' );
    }

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Too many attempts' );
} );

it( 'estimates shipping, and the chosen rate carries into the cart', function (): void {
    usShipping( [ 'Standard' => 500, 'Express' => 1500 ] );
    $cart = shopperCartWith( [ makeProduct( 2000 ) ] );

    $component = Livewire::test( Index::class )
        ->assertSeeHtml( 'data-cart-estimate' )
        ->set( 'estimateCountry', 'US' )
        ->set( 'estimateRegion', 'IL' )
        ->set( 'estimatePostcode', '60601' )
        ->call( 'estimateShipping' )
        ->assertHasNoErrors()
        ->assertSet( 'announcement', '2 shipping options found.' )
        ->assertSee( 'Standard' )
        ->assertSee( 'Express' )
        ->assertSee( '$15.00' );

    $express = collect( $component->get( 'rates' ) )->firstWhere( 'label', 'Express' );

    $component->set( 'selectedRate', $express['id'] )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated' )
        ->assertSeeHtml( 'data-cart-shipping="chosen"' )
        ->assertSeeHtml( 'data-cart-tax="calculated"' )
        ->assertSet( 'announcement', 'Shipping estimate updated. Total: $35.00.' );

    $cart->refresh();

    expect( $cart->shipping_amount )->toBe( 1500 )
        ->and( $cart->meta[ StorefrontCartService::SHIPPING_RATE_META_KEY ]['destination']['country_code'] )->toBe( 'US' );

    // The page remembers the estimate on the next visit.
    Livewire::test( Index::class )
        ->assertSet( 'estimateCountry', 'US' )
        ->assertSet( 'estimatePostcode', '60601' )
        ->assertSet( 'selectedRate', $express['id'] );
} );

it( 'says when no shipping options reach an address', function (): void {
    usShipping();
    shopperCartWith( [ makeProduct() ] );

    Livewire::test( Index::class )
        ->set( 'estimateCountry', 'FR' )
        ->call( 'estimateShipping' )
        ->assertSet( 'rates', [] )
        ->assertSee( 'No shipping options for this address.' );
} );

it( 'validates the estimate destination', function ( array $input, string $field, string $message ): void {
    shopperCartWith( [ makeProduct() ] );

    $component = Livewire::test( Index::class );

    foreach ( $input as $key => $value ) {
        $component->set( $key, $value );
    }

    $component->call( 'estimateShipping' )
        ->assertHasErrors( $field )
        ->assertSet( 'rates', null );

    expect( $component->errors()->first( $field ) )->toBe( $message );
} )->with( [
    'no country'     => [ [], 'estimateCountry', 'Choose a country.' ],
    'bad country'    => [ [ 'estimateCountry' => 'XX' ], 'estimateCountry', 'Choose a country.' ],
    'unknown region' => [ [ 'estimateCountry' => 'US', 'estimateRegion' => 'ZZ' ], 'estimateRegion', 'Choose a region from the list.' ],
] );

it( 'only selects a rate that was quoted', function (): void {
    usShipping();
    $cart = shopperCartWith( [ makeProduct() ] );

    Livewire::test( Index::class )
        ->set( 'estimateCountry', 'US' )
        ->set( 'selectedRate', '999:made-up' )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    expect( $cart->refresh()->shipping_amount )->toBe( 0 );
} );

it( 'keeps quoted rates out of the browser\'s reach', function ( string $property, mixed $value ): void {
    shopperCartWith( [ makeProduct() ] );

    expect( fn () => Livewire::test( Index::class )->set( $property, $value ) )
        ->toThrow( CannotUpdateLockedPropertyException::class );
} )->with( [
    'rates'       => [ 'rates', [ [ 'id' => 'x', 'label' => 'Free', 'amount' => 0, 'currency' => 'USD' ] ] ],
    'removedLine' => [ 'removedLine', [ 'product_id' => 1, 'variant_id' => null, 'quantity' => 1, 'options' => [], 'name' => 'X' ] ],
] );

it( 'hides the estimate for a cart that doesn\'t ship', function (): void {
    $ebook = Product::factory()->digital()->create( [ 'name' => 'E-book' ] );
    ArtisanPackUI\Ecommerce\Models\ProductPrice::factory()->forPriceable( $ebook )->create( [ 'currency' => 'USD', 'price_amount' => 900 ] );

    shopperCartWith( [ $ebook ] );

    Livewire::test( Index::class )
        ->assertSee( 'E-book' )
        ->assertDontSeeHtml( 'data-cart-estimate' )
        ->assertDontSeeHtml( 'data-cart-shipping=' );
} );

it( 'flags lines that can no longer be bought and blocks checkout until they are removed', function (): void {
    $mug  = makeProduct( 1000, [ 'name' => 'Mug' ] );
    $lamp = makeProduct( 3000, [ 'name' => 'Lamp' ] );
    $cart = shopperCartWith( [ $mug, $lamp ] );

    $lamp->forceFill( [ 'status' => 'draft' ] )->save();

    $lampLine = $cart->items()->where( 'product_id', $lamp->id )->sole();

    Livewire::test( Index::class )
        ->assertSee( 'This product is no longer available.' )
        ->assertSeeHtml( 'data-cart-line-unsellable' )
        ->assertSeeHtml( 'data-cart-checkout-blocked' )
        ->assertSee( 'Remove the items that can\'t be bought to check out.' )
        ->call( 'removeLine', $lampLine->id )
        ->assertSet( 'removedLine', [ 'product_id' => $lamp->id, 'variant_id' => null, 'quantity' => 1, 'options' => [], 'name' => 'Lamp' ] )
        ->assertDontSeeHtml( 'data-cart-checkout-blocked' );
} );

it( 'flags an out-of-stock line', function (): void {
    $mug  = makeProduct( 1000, [ 'name' => 'Mug' ] );
    $cart = shopperCartWith( [ $mug ] );

    setStock( $mug, 0 );

    Livewire::test( Index::class )
        ->assertSee( 'This item is out of stock.' )
        ->assertSeeHtml( 'data-cart-checkout-blocked' );

    expect( $cart->items()->count() )->toBe( 1 );
} );

it( 'follows changes made in the cart drawer', function (): void {
    $line = shopperCartWith( [ makeProduct() ] )->items()->sole();

    $component = Livewire::test( Index::class );

    app( StorefrontCartService::class )->updateItem( Cart::query()->sole(), $line, 4 );

    $component->dispatch( 'ecommerce-cart-updated', count: 4 )
        ->assertSet( 'quantities.' . $line->id, 4 );
} );

it( 'escapes product names', function (): void {
    shopperCartWith( [ makeProduct( 1000, [ 'name' => '<b>Bold</b> mug' ] ) ] );

    Livewire::test( Index::class )->assertDontSeeHtml( '<b>Bold</b>' );
} );

it( 'renders the cart page route with the component', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.cart' ) )
        ->assertOk()
        ->assertSeeLivewire( Index::class )
        ->assertDontSeeHtml( 'data-screen-pending' );
} );
