<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Order\Confirmation;
use Livewire\Livewire;

/*
 * partials/order-details (#87) and the order status badge (#89): the
 * Items card of list rows, the Addresses and payment and Order summary
 * cards, and a soft design-system Badge per status.
 */

/**
 * The guest confirmation page's HTML for an order.
 *
 * @param  Order  $order  The order.
 *
 * @return string
 */
function orderDetailsHtml( Order $order ): string
{
    $html = Livewire::test( Confirmation::class, [ 'order' => $order->id, 'token' => OrderViewToken::for( $order ) ] )->html();

    return (string) preg_replace( '/<!--\[if (END)?BLOCK\]><!\[endif\]-->/', '', $html );
}

it( 'lays the order out in design-system cards with list rows', function (): void {
    $order = placedOrder( [ 'customer_note' => 'Leave it with the neighbor.' ] );
    $html  = orderDetailsHtml( $order );

    expect( $html )->toContain( 'data-order-details' )
        ->toMatch( '/class="apui-card ec-order-card ec-order-card--flush" role="region" aria-labelledby="ec-order-items-heading"/' )
        ->toContain( '<h2 id="ec-order-items-heading" class="ec-order-card__title">Items</h2>' )
        ->toContain( '2 items' )
        ->toMatch( '/<ul\s+class="apui-list ec-order-lines"[^>]*data-variant="divided"/' )
        ->toMatch( '/<li\s+class="apui-list-item ec-order-line"/' )
        ->toContain( 'Mug' )
        ->toMatch( '/class="ec-order-line__sku">SKU: SKU-\d+</' )
        ->toContain( '&times;' )
        ->toContain( '$20.00' )
        ->toContain( '$40.00' )
        ->toContain( 'Addresses and payment' )
        ->toContain( 'class="ec-order-meta"' )
        ->toContain( 'data-order-billing-same' )
        ->toContain( 'Same as shipping' )
        ->toContain( 'class="ec-order-note"' )
        ->toContain( 'Leave it with the neighbor.' )
        ->toContain( 'Order summary' )
        ->toContain( 'class="ec-totals"' );

    // The details close the confirmation page; the rest of it is rebuilt separately.
    $details = substr( $html, (int) strpos( $html, 'data-order-details' ) );

    expect( $details )->not->toContain( 'rounded-box' )
        ->not->toContain( 'base-content' )
        ->not->toContain( 'divide-y' )
        ->not->toContain( 'text-xl' )
        ->not->toContain( 'class="sr-only"' );
} );

it( 'shows a line\'s image, or a placeholder once the product is gone', function (): void {
    $mug  = makeProduct( 2000, [ 'name' => 'Mug', 'meta' => [ 'featured_image_url' => 'https://cdn.example.test/mug.jpg' ] ] );
    $vase = makeProduct( 2000, [ 'name' => 'Vase' ] );

    $order = placedOrder( [], null, [ $mug, $vase ] );
    $vase->forceDelete();

    $html = orderDetailsHtml( $order );

    expect( $html )->toContain( 'src="https://cdn.example.test/mug.jpg"' )
        ->toContain( 'width="64" height="64"' )
        ->toContain( 'alt=""' )
        ->and( substr_count( $html, 'data-order-line-image' ) )->toBe( 2 )
        ->and( $html )->toMatch( '/data-order-line-image>\s*<span\s+class="apui-icon"/' );
} );

it( 'lists the variant, marks a free gift and prices it as free', function (): void {
    $order = placedOrder();

    OrderItem::query()->whereKey( $order->items->first()->id )->delete();
    OrderItem::factory()->create( [
        'order_id'          => $order->id,
        'product_id'        => null,
        'product_snapshot'  => [ 'name' => 'Candle', 'variant_name' => 'Travel size', 'sku' => 'CDL-TRV', 'type' => 'simple', 'options' => [], 'free_item' => true ],
        'quantity'          => 1,
        'unit_price_amount' => 0,
    ] );

    expect( orderDetailsHtml( $order->refresh() ) )
        ->toContain( 'Travel size · Free gift' )
        ->toContain( '1 item' )
        ->toMatch( '/data-order-line-total>\s*Free\s*</' );
} );

it( 'shows both addresses when billing differs, and the payment method with its icon', function (): void {
    $order = placedOrder( [
        'billing_address'     => [ 'first_name' => 'Pat', 'last_name' => 'Lee', 'address1' => '9 Billing Road', 'city' => 'Austin', 'region_code' => 'TX', 'postal_code' => '73301', 'country_code' => 'US' ],
        'payment_gateway_key' => 'manual',
    ] );

    expect( orderDetailsHtml( $order ) )->toContain( '9 Billing Road' )
        ->not->toContain( 'data-order-billing-same' )
        ->toMatch( '/data-order-payment-method>\s*<span\s+class="apui-icon"/' );
} );

it( 'says no payment was needed, without an icon, when the order took none', function (): void {
    $order = placedOrder( [ 'payment_gateway_key' => null ] );

    expect( orderDetailsHtml( $order ) )->toMatch( '/data-order-payment-method>\s*<span>No payment needed<\/span>/' );
} );

it( 'lists downloads as rows in their own card', function (): void {
    $order = placedOrder();
    $file  = DigitalFile::factory()->create( [ 'label' => 'Field guide (PDF)', 'product_id' => $order->items->first()->product_id ] );

    DigitalDownload::factory()->create( [ 'order_item_id' => $order->items->first()->id, 'digital_file_id' => $file->id, 'downloads_remaining' => 3 ] );

    expect( orderDetailsHtml( $order ) )
        ->toMatch( '/class="apui-card ec-order-card ec-order-card--flush"[^>]*aria-labelledby="ec-order-downloads-heading"/' )
        ->toContain( 'Field guide (PDF)' )
        ->toContain( '3 downloads left' )
        ->toContain( 'class="ec-order-card__footnote" data-order-downloads-email' );
} );

it( 'shows each status as a soft badge in its design-system color, with an icon', function ( string $status, string $label, string $color ): void {
    $html = orderDetailsHtml( placedOrder( [ 'system_status' => $status ] ) );

    expect( $html )->toMatch( '/class="apui-badge" data-order-status="data-order-status"\s+data-variant="soft"\s+data-color="' . $color . '"[^>]*>\s*<span data-scope="badge" data-part="icon"[^>]*>.*?<\/span><span data-scope="badge" data-part="label">' . preg_quote( $label, '/' ) . '</s' )
        ->not->toContain( 'badge-' );
} )->with( [
    'processing' => [ 'processing', 'Processing', 'info' ],
    'complete'   => [ 'complete', 'Completed', 'success' ],
    'pending'    => [ 'pending', 'Awaiting payment', 'warning' ],
    'refunded'   => [ 'refunded', 'Refunded', 'warning' ],
    'cancelled'  => [ 'cancelled', 'Cancelled', 'neutral' ],
    'failed'     => [ 'failed', 'Payment failed', 'error' ],
    'unknown'    => [ 'on_hold', 'On Hold', 'neutral' ],
] );
