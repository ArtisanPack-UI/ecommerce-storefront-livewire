<?php

/**
 * Order description concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\OrderNote;
use ArtisanPackUI\Ecommerce\Models\Refund;
use ArtisanPackUI\Ecommerce\Models\Shipment;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a placed order into what the confirmation page, the account
 * screens, and the guest order view show (spec §7.4, §7.5): lines, totals
 * in the shape of `partials.order-totals`, the customer-facing status, the
 * payment method, downloads, shipments, refunds, and customer-visible
 * notes.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
trait DescribesOrder
{
    /**
     * The status a shopper sees for an order, through
     * `ap.ecommerceStorefrontLivewire.order.statusLabel`.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string
     */
    protected function orderStatusLabel( Order $order ): string
    {
        $status = (string) $order->system_status;
        $label  = match ( $status ) {
            'pending'    => __( 'Awaiting payment' ),
            'processing' => __( 'Processing' ),
            'complete'   => __( 'Completed' ),
            'cancelled'  => __( 'Cancelled' ),
            'refunded'   => __( 'Refunded' ),
            'failed'     => __( 'Payment failed' ),
            default      => Str::headline( $status ),
        };

        $filtered = applyFilters( 'ap.ecommerceStorefrontLivewire.order.statusLabel', $label, $order );

        return is_string( $filtered ) && '' !== trim( $filtered ) ? $filtered : $label;
    }

    /**
     * The badge colour for an order's status.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string
     */
    protected function orderStatusColor( Order $order ): string
    {
        return match ( (string) $order->system_status ) {
            'complete'             => 'badge-success',
            'processing'           => 'badge-info',
            'pending'              => 'badge-warning',
            'cancelled', 'failed'  => 'badge-error',
            default                => 'badge-ghost',
        };
    }

    /**
     * An order as a row in a list: `id`, `number`, `date`, `status`,
     * `color`, `total`, `currency`, `items` (units), and `url` (its page in
     * the account, when the route exists).
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order, with its items.
     *
     * @return array{id: int, number: string, date: string, status: string, color: string, total: int, currency: string, items: int, url: string|null}
     */
    protected function orderRow( Order $order ): array
    {
        return [
            'id'       => (int) $order->id,
            'number'   => (string) $order->order_number,
            'date'     => $this->orderDate( $order ),
            'status'   => $this->orderStatusLabel( $order ),
            'color'    => $this->orderStatusColor( $order ),
            'total'    => (int) $order->total_amount,
            'currency' => (string) $order->currency,
            'items'    => $this->orderItemCount( $order ),
            'url'      => Route::has( 'artisanpack.ecommerce.account.orders.show' ) ? route( 'artisanpack.ecommerce.account.orders.show', [ 'order' => $order->id ] ) : null,
        ];
    }

    /**
     * The order's lines: `id`, `name`, `variant`, `sku`, `quantity`,
     * `unit`, `total` (before discounts), `currency`, `free`, and whether
     * the product still exists (`product_id`).
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order, with its items.
     *
     * @return array<int, array{id: int, name: string, variant: string|null, sku: string|null, quantity: int, unit: int, total: int, currency: string, free: bool, product_id: int|null}>
     */
    protected function orderLines( Order $order ): array
    {
        return $order->items->map( static function ( OrderItem $item ): array {
            $snapshot = (array) ( $item->product_snapshot ?? [] );
            $name     = is_string( $snapshot['name'] ?? null ) && '' !== trim( $snapshot['name'] ) ? trim( $snapshot['name'] ) : __( 'Item' );
            $variant  = is_string( $snapshot['variant_name'] ?? null ) && '' !== trim( $snapshot['variant_name'] ) ? trim( $snapshot['variant_name'] ) : null;
            $sku      = is_string( $snapshot['sku'] ?? null ) && '' !== trim( $snapshot['sku'] ) ? trim( $snapshot['sku'] ) : null;

            return [
                'id'         => (int) $item->id,
                'name'       => $name,
                'variant'    => $variant,
                'sku'        => $sku,
                'quantity'   => (int) $item->quantity,
                'unit'       => (int) $item->unit_price_amount,
                'total'      => (int) $item->unit_price_amount * (int) $item->quantity,
                'currency'   => (string) $item->unit_price_currency,
                'free'       => true === ( $snapshot['free_item'] ?? false ),
                'product_id' => null === $item->product_id ? null : (int) $item->product_id,
            ];
        } )->values()->all();
    }

    /**
     * How many units the order holds.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order, with its items.
     *
     * @return int
     */
    protected function orderItemCount( Order $order ): int
    {
        return (int) $order->items->sum( 'quantity' );
    }

    /**
     * The order's totals, in the shape `partials.order-totals` takes.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<string, mixed>
     */
    protected function orderTotals( Order $order ): array
    {
        $meta     = (array) ( $order->meta ?? [] );
        $rate     = is_array( $meta['shipping_rate'] ?? null ) ? $meta['shipping_rate'] : null;
        $coupon   = is_string( $meta['coupon_code'] ?? null ) && '' !== $meta['coupon_code'] ? $meta['coupon_code'] : null;
        $discount = (int) $order->discount_amount;

        $shipping = match ( true ) {
            ! is_array( $order->shipping_address ) && (int) $order->shipping_amount <= 0  => [ 'state' => 'none', 'label' => null, 'amount' => 0 ],
            (int) $order->shipping_amount <= 0                                            => [ 'state' => 'free', 'label' => null, 'amount' => 0 ],
            default                                                                       => [ 'state' => 'chosen', 'label' => (string) ( $rate['label'] ?? '' ), 'amount' => (int) $order->shipping_amount ],
        };

        return [
            'currency'  => (string) $order->currency,
            'subtotal'  => (int) $order->subtotal_amount,
            'discounts' => $discount > 0 ? [ [ 'label' => null === $coupon ? __( 'Discount' ) : __( 'Discount (:code)', [ 'code' => $coupon ] ), 'amount' => $discount, 'free_shipping' => false ] ] : [],
            'discount'  => $discount,
            'shipping'  => $shipping,
            'tax'       => [
                'amount'    => (int) $order->tax_amount,
                'estimated' => false,
                'inclusive' => true === ( $meta['prices_include_tax'] ?? false ),
            ],
            'total'     => (int) $order->total_amount,
            'refunded'  => (int) $order->total_refunded_amount,
        ];
    }

    /**
     * The name of the gateway that took the payment, or null when the
     * order needed none.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string|null
     */
    protected function orderPaymentMethod( Order $order ): ?string
    {
        $key = trim( (string) $order->payment_gateway_key );

        if ( '' === $key ) {
            return null;
        }

        try {
            $gateway = app( PaymentGatewayRegistry::class )->find( $key );
        } catch ( Throwable $exception ) {
            report( $exception );

            $gateway = null;
        }

        return null === $gateway ? Str::headline( $key ) : $gateway->label();
    }

    /**
     * When the order was placed, in the shopper's locale.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string
     */
    protected function orderDate( Order $order ): string
    {
        return LocalizedDate::format( $order->placed_at ?? $order->created_at );
    }

    /**
     * The digital downloads the order unlocked: `id`, `name`, `remaining`
     * (null for unlimited), and `expires` (a date, or null).
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<int, array{id: int, name: string, remaining: int|null, expires: string|null}>
     */
    protected function orderDownloads( Order $order ): array
    {
        return DigitalDownload::query()
            ->whereIn( 'order_item_id', $order->items->pluck( 'id' ) )
            ->with( 'file' )
            ->orderBy( 'id' )
            ->get()
            ->map( static fn ( DigitalDownload $download ): array => [
                'id'        => (int) $download->id,
                'name'      => '' !== trim( (string) $download->file?->label ) ? trim( (string) $download->file?->label ) : __( 'Download' ),
                'remaining' => null === $download->downloads_remaining ? null : (int) $download->downloads_remaining,
                'expires'   => null === $download->expires_at ? null : LocalizedDate::format( $download->expires_at ),
            ] )
            ->all();
    }

    /**
     * The order's shipments: `id`, `carrier`, `status`, `tracking`
     * (number), `url` (only an http(s) link), and `shipped` (a date).
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<int, array{id: int, carrier: string|null, status: string, tracking: string|null, url: string|null, shipped: string|null}>
     */
    protected function orderShipments( Order $order ): array
    {
        return $order->shipments()->orderBy( 'id' )->get()->map( function ( Shipment $shipment ): array {
            $url = trim( (string) $shipment->tracking_url );

            return [
                'id'       => (int) $shipment->id,
                'carrier'  => '' !== trim( (string) $shipment->carrier ) ? trim( (string) $shipment->carrier ) : null,
                'status'   => $this->shipmentStatusLabel( (string) $shipment->status ),
                'tracking' => '' !== trim( (string) $shipment->tracking_number ) ? trim( (string) $shipment->tracking_number ) : null,
                'url'      => 1 === preg_match( '#^https?://#i', $url ) && false !== filter_var( $url, FILTER_VALIDATE_URL ) ? $url : null,
                'shipped'  => null === $shipment->shipped_at ? null : LocalizedDate::format( $shipment->shipped_at ),
            ];
        } )->all();
    }

    /**
     * A shipment status as the shopper reads it.
     *
     * @since 1.0.0
     *
     * @param  string  $status  The shipment's status.
     *
     * @return string
     */
    protected function shipmentStatusLabel( string $status ): string
    {
        return match ( $status ) {
            Shipment::STATUS_PENDING    => __( 'Preparing' ),
            Shipment::STATUS_IN_TRANSIT => __( 'In transit' ),
            Shipment::STATUS_DELIVERED  => __( 'Delivered' ),
            Shipment::STATUS_EXCEPTION  => __( 'Delivery problem' ),
            default                     => Str::headline( $status ),
        };
    }

    /**
     * The order's refunds that went through or are on their way: `id`,
     * `amount`, `currency`, `date`, and `pending`.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<int, array{id: int, amount: int, currency: string, date: string, pending: bool}>
     */
    protected function orderRefunds( Order $order ): array
    {
        return $order->refunds()->counting()->orderBy( 'id' )->get()->map( static fn ( Refund $refund ): array => [
            'id'       => (int) $refund->id,
            'amount'   => (int) $refund->amount,
            'currency' => (string) $refund->currency,
            'date'     => LocalizedDate::format( $refund->created_at ),
            'pending'  => Refund::STATUS_PENDING === $refund->status,
        ] )->all();
    }

    /**
     * The notes the store shared with the shopper: `id`, `body`, `date`.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<int, array{id: int, body: string, date: string}>
     */
    protected function orderNotes( Order $order ): array
    {
        return $order->customerNotes()->orderBy( 'id' )->get()->map( static fn ( OrderNote $note ): array => [
            'id'   => (int) $note->id,
            'body' => (string) $note->body,
            'date' => LocalizedDate::format( $note->created_at ),
        ] )->all();
    }
}
