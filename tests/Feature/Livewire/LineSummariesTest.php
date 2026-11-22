<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\OrderShow;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Drawer;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Index;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\LineSummaries;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Livewire\Livewire;
use Tests\Fixtures\ProductTypes\ConfiguredPrintProductType;

/*
 * Configuration summaries on cart, drawer, and order lines (#67).
 */

/**
 * The shopper's cart holding a configured print.
 *
 * @param  array<string, mixed>  $options  The print's options.
 */
function cartWithConfiguredPrint( array $options ): void
{
    app( ProductTypeRegistry::class )->register( ConfiguredPrintProductType::KEY, new ConfiguredPrintProductType() );

    $print = Product::factory()->create( [ 'type' => ConfiguredPrintProductType::KEY, 'name' => 'Harbour print' ] );
    ProductPrice::factory()->forPriceable( $print )->create( [ 'currency' => 'USD', 'price_amount' => 6000, 'compare_at_amount' => null ] );
    setStock( $print, 10 );

    app( StorefrontCartService::class )->addItem( app( StorefrontCart::class )->current( true ), $print->id, null, 1, $options );
}

/**
 * A placed order for `$customer` whose one line has `$snapshot` merged in.
 *
 * @param  array<string, mixed>  $snapshot  Snapshot keys.
 */
function orderWithSnapshot( ArtisanPackUI\Ecommerce\Models\Customer $customer, array $snapshot ): ArtisanPackUI\Ecommerce\Models\Order
{
    $order = placedOrder( [], $customer );
    $item  = $order->items->first();

    OrderItem::query()->whereKey( $item->id )->delete();
    OrderItem::factory()->create( [
        'order_id'          => $order->id,
        'product_id'        => $item->product_id,
        'product_snapshot'  => [ ...$item->product_snapshot, ...$snapshot ],
        'quantity'          => 1,
        'unit_price_amount' => 2000,
    ] );

    return $order->refresh();
}

it( 'summarizes a cart line with the product type\'s own labels', function ( string $component ): void {
    if ( ! LineSummaries::engineDescribes() ) {
        $this->markTestSkipped( 'Needs engine 1.4 (LineOptionDescriber).' );
    }

    cartWithConfiguredPrint( [ 'frame' => 'oak', 'size' => 'large', 'paper' => 'matte', 'mat' => 'black', 'gift' => false ] );

    $test = Livewire::test( $component );

    if ( Drawer::class === $component ) {
        $test->dispatch( 'ecommerce-cart-open' );
    }

    $test->assertSee( 'Frame: Black oak · Size: 24 × 36 · Paper: Matte · Mat: Black' )
        ->assertDontSee( 'Frame: oak' );
} )->with( [ 'cart page' => [ Index::class ], 'cart drawer' => [ Drawer::class ] ] );

it( 'shows the rows recorded when the order was placed', function (): void {
    if ( ! LineSummaries::engineDescribes() ) {
        $this->markTestSkipped( 'Needs engine 1.4 (LineOptionDescriber).' );
    }

    [ , $customer ] = shopper();
    $order          = orderWithSnapshot( $customer, [ 'variant_name' => 'Ignored', 'option_lines' => [ [ 'label' => 'Size', 'value' => '16 × 20 in' ], [ 'label' => 'Frame', 'value' => 'Black oak' ] ] ] );

    Livewire::test( OrderShow::class, [ 'order' => $order->id ] )
        ->assertSeeHtml( 'data-order-line-options' )
        ->assertSee( 'Size: 16 × 20 in · Frame: Black oak' )
        ->assertDontSee( 'Ignored' );
} );

it( 'falls back to the variant name for an order placed before the rows were recorded', function (): void {
    [ , $customer ] = shopper();
    $order          = orderWithSnapshot( $customer, [ 'variant_name' => 'Blue / Large' ] );

    expect( LineSummaries::forOrderItem( $order->items->first() ) )->toBe( [ 'Blue / Large' ] );

    Livewire::test( OrderShow::class, [ 'order' => $order->id ] )
        ->assertSee( 'Blue / Large' );
} );

it( 'reads a row without a label as its value and drops rows without a value', function (): void {
    if ( ! LineSummaries::engineDescribes() ) {
        $this->markTestSkipped( 'Needs engine 1.4 (LineOptionDescriber).' );
    }

    $item = new OrderItem( [ 'product_snapshot' => [ 'option_lines' => [ [ 'label' => '', 'value' => 'Red' ], [ 'label' => 'Note', 'value' => '' ], [ 'label' => 'Size', 'value' => 'M' ] ] ] ] );

    expect( LineSummaries::forOrderItem( $item ) )->toBe( [ 'Red', 'Size: M' ] );
} );
