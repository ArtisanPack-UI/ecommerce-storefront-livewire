<?php

/**
 * Cart description concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Inventory\StockStatus;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\CartItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Describes the shopper's cart for the views: its lines (with the reason a
 * line can't be bought) and its order summary. Shared by the cart page,
 * the cart drawer, and the checkout.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
trait DescribesCart
{
    /**
     * The cart's lines as the views show them.
     *
     * Keys: `id`, `name`, `url`, `image`, `options` (strings), `quantity`,
     * `unit` and `total` (minor units), `currency`, `free` (a promotion's
     * free item, which can't be changed), and `unsellable` (why the line
     * can't be bought, or null).
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return array<int, array{id: int, name: string, url: string|null, image: array{url: string, srcset: string|null, alt: string}|null, options: array<int, string>, quantity: int, unit: int, total: int, currency: string, free: bool, unsellable: string|null}>
     */
    protected function lines( Cart $cart ): array
    {
        $items   = $cart->items()->with( [ 'product.images', 'product.inventoryItems', 'product.variants.inventoryItems', 'variant.inventoryItems' ] )->orderBy( 'id' )->get();
        $visible = Product::query()->storefrontVisible()->whereKey( $items->pluck( 'product_id' )->filter()->unique()->all() )->pluck( 'id' )->map( static fn ( $id ): int => (int) $id )->all();
        $hasUrl  = Route::has( 'artisanpack.ecommerce.storefront.product' );
        $code    = (string) $cart->currency;

        return $items->map( function ( CartItem $item ) use ( $visible, $hasUrl, $code ): array {
            $product = $item->product;
            $name    = null === $product ? __( 'Unavailable product' ) : (string) $product->name;

            return [
                'id'         => (int) $item->id,
                'name'       => $name,
                'url'        => null !== $product && $hasUrl && in_array( (int) $product->id, $visible, true ) ? route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) : null,
                'image'      => $this->lineImage( $item, $name ),
                'options'    => $this->lineOptions( $item ),
                'quantity'   => (int) $item->quantity,
                'unit'       => (int) $item->unit_price_amount,
                'total'      => (int) $item->line_subtotal_amount,
                'currency'   => (string) ( $item->unit_price_currency ?? $code ),
                'free'       => $item->isFreeItem(),
                'unsellable' => $this->unsellableReason( $item, $visible ),
            ];
        } )->all();
    }

    /**
     * Why a line can't be bought, or null when it can.
     *
     * @since 1.0.0
     *
     * @param  CartItem         $item     The line.
     * @param  array<int, int>  $visible  Ids of the cart's products shoppers can see.
     *
     * @return string|null
     */
    protected function unsellableReason( CartItem $item, array $visible ): ?string
    {
        $product = $item->product;

        if ( null === $product || ! in_array( (int) $product->id, $visible, true ) ) {
            return __( 'This product is no longer available.' );
        }

        if ( $product->typeIsMissing() ) {
            return __( 'This product can no longer be bought.' );
        }

        if ( null !== $item->product_variant_id && null === $item->variant ) {
            return __( 'This option is no longer available.' );
        }

        if ( ! $item->isFreeItem() && ! StockStatus::for( $item->variant?->setRelation( 'product', $product ) ?? $product )->purchasable() ) {
            return __( 'This item is out of stock.' );
        }

        return null;
    }

    /**
     * The line's image: the variant's, else the product's card image.
     *
     * @since 1.0.0
     *
     * @param  CartItem  $item  The line.
     * @param  string    $name  The product name, for the alt text.
     *
     * @return array{url: string, srcset: string|null, alt: string}|null
     */
    protected function lineImage( CartItem $item, string $name ): ?array
    {
        if ( null !== $item->variant?->image_media_id ) {
            $image = ProductImages::media( (int) $item->variant->image_media_id, $name );

            if ( null !== $image ) {
                return [ 'url' => (string) $image['url'], 'srcset' => is_string( $image['srcset'] ?? null ) ? $image['srcset'] : null, 'alt' => $name ];
            }
        }

        return null === $item->product ? null : ProductImages::card( $item->product );
    }

    /**
     * The line's variant and options as short texts ("Red / M",
     * "Engraving: Happy birthday").
     *
     * @since 1.0.0
     *
     * @param  CartItem  $item  The line.
     *
     * @return array<int, string>
     */
    protected function lineOptions( CartItem $item ): array
    {
        $options = [];

        if ( null !== $item->variant && '' !== trim( (string) $item->variant->name ) ) {
            $options[] = (string) $item->variant->name;
        }

        foreach ( (array) ( $item->options ?? [] ) as $key => $value ) {
            if ( is_string( $key ) && ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) && '' !== trim( (string) $value ) ) {
                $options[] = __( ':option: :value', [ 'option' => Str::headline( $key ), 'value' => (string) $value ] );
            }
        }

        return $options;
    }

    /**
     * A money amount for an announcement.
     *
     * @since 1.0.0
     *
     * @param  int     $amount    Minor units.
     * @param  string  $currency  ISO 4217 code.
     *
     * @return string
     */
    protected function formatMoney( int $amount, string $currency ): string
    {
        return MoneyFormatter::format( $amount, $currency );
    }

    /**
     * The order summary.
     *
     * Keys: `currency`, `subtotal`, `discounts` (`label`, `amount`, `free_shipping`),
     * `discount`, `shipping` (`state`: `chosen`, `free`, `checkout`, or
     * `none`; `label`; `amount`), `tax` (`amount`, `estimated`,
     * `inclusive`), and `total`.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return array<string, mixed>
     */
    protected function totals( Cart $cart ): array
    {
        $meta = (array) ( $cart->meta ?? [] );
        $rate = $this->chosenRate( $cart );
        $tax  = is_array( $meta[ StorefrontCartService::TAX_META_KEY ] ?? null ) ? $meta[ StorefrontCartService::TAX_META_KEY ] : [];
        $free = [] !== (array) ( $meta[ StorefrontCartService::FREE_SHIPPING_META_KEY ] ?? [] );

        $shipping = match ( true ) {
            ! $this->requiresShipping( $cart ) => [ 'state' => 'none', 'label' => null, 'amount' => 0 ],
            $free                              => [ 'state' => 'free', 'label' => null, 'amount' => 0 ],
            null !== $rate                     => [ 'state' => 'chosen', 'label' => (string) ( $rate['label'] ?? '' ), 'amount' => (int) $cart->shipping_amount ],
            default                            => [ 'state' => 'checkout', 'label' => null, 'amount' => 0 ],
        };

        return [
            'currency'  => (string) $cart->currency,
            'subtotal'  => (int) $cart->subtotal_amount,
            'discounts' => $this->discounts( $cart ),
            'discount'  => (int) $cart->discount_amount,
            'shipping'  => $shipping,
            'tax'       => [
                'amount'    => (int) $cart->tax_amount,
                'estimated' => (bool) ( $tax['estimated'] ?? true ),
                'inclusive' => (bool) ( $tax['prices_include_tax'] ?? false ),
            ],
            'total'     => (int) $cart->total_amount,
        ];
    }

    /**
     * Each applied promotion and its discount. When the promotions can't
     * be evaluated, one "Discount" line with the cart's discount.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return array<int, array{label: string, amount: int, free_shipping: bool}>
     */
    protected function discounts( Cart $cart ): array
    {
        $fallback = (int) $cart->discount_amount > 0 ? [ [ 'label' => __( 'Discount' ), 'amount' => (int) $cart->discount_amount, 'free_shipping' => false ] ] : [];

        try {
            $promotions = app( StorefrontCartService::class )->promotionResult( $cart )->toArray()['promotions'] ?? [];
        } catch ( Throwable $exception ) {
            report( $exception );

            return $fallback;
        }

        $discounts = [];

        foreach ( (array) $promotions as $promotion ) {
            $amount = (int) ( $promotion['amount'] ?? 0 );
            $label  = trim( (string) ( $promotion['name'] ?? '' ) );

            if ( $amount > 0 || (bool) ( $promotion['free_shipping'] ?? false ) ) {
                $discounts[] = [ 'label' => '' === $label ? __( 'Discount' ) : $label, 'amount' => $amount, 'free_shipping' => (bool) ( $promotion['free_shipping'] ?? false ) ];
            }
        }

        return [] === $discounts ? $fallback : $discounts;
    }

    /**
     * The shipping rate chosen for the cart, as the engine stored it.
     *
     * @since 1.0.0
     *
     * @param  Cart|null  $cart  The cart.
     *
     * @return array<string, mixed>|null
     */
    protected function chosenRate( ?Cart $cart ): ?array
    {
        $rate = null === $cart ? null : ( (array) ( $cart->meta ?? [] ) )[ StorefrontCartService::SHIPPING_RATE_META_KEY ] ?? null;

        return is_array( $rate ) ? $rate : null;
    }

    /**
     * Whether any line needs shipping.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return bool
     */
    protected function requiresShipping( Cart $cart ): bool
    {
        try {
            return app( StorefrontCartService::class )->requiresShipping( $cart );
        } catch ( Throwable $exception ) {
            report( $exception );

            return true;
        }
    }
}
