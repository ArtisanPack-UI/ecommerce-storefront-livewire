<?php

/**
 * Storefront cart concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Support\Facades\Route;

/**
 * Gives a component the shopper's cart and announces cart changes.
 *
 * After any change, call {@see self::cartChanged()}: it dispatches
 * `ecommerce-cart-updated` (a browser and Livewire event) with the new item
 * count and `source` (the class of the component that made the change),
 * which the header cart button, drawer, and any host listener pick up
 * (spec §5.1). A listener that already followed its own change can ignore
 * the event when `source` is its own class.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
trait InteractsWithStorefrontCart
{
    /**
     * The shopper's cart.
     *
     * @since 1.0.0
     *
     * @param  bool  $create  Create a cart when the shopper has none.
     *
     * @return Cart|null
     */
    protected function cart( bool $create = false ): ?Cart
    {
        return app( StorefrontCart::class )->current( $create );
    }

    /**
     * Records the shopper's cart after a change and announces it.
     *
     * @since 1.0.0
     *
     * @param  Cart|null  $cart  The cart as it is now, when the change returned it.
     *
     * @return void
     */
    protected function cartChanged( ?Cart $cart = null ): void
    {
        $carts = app( StorefrontCart::class );

        if ( null !== $cart ) {
            $carts->remember( $cart->fresh() ?? $cart );
        } else {
            $carts->refresh();
        }

        $this->dispatch( 'ecommerce-cart-updated', count: $carts->count(), source: static::class );
    }

    /**
     * Gives feedback after an "Add to cart", per `cart.after_add`:
     * `drawer` (the default) dispatches `ecommerce-cart-open` for the cart
     * drawer, `toast` shows a toast naming the product, `none` does nothing
     * (the header count still updates). Needs {@see SendsToasts}.
     *
     * The toast (#97) names the product and its variant ("Oxbow mug ·
     * Stoneware · 14 oz") with a "View cart" button when the cart page is
     * registered.
     *
     * @since 1.0.0
     * @since 2.0.0 Added `$details`, and the "View cart" action.
     *
     * @param  string|null  $productName  The product added, for the toast.
     * @param  string|null  $details      The variant or options, after the name.
     *
     * @return void
     */
    protected function afterAddToCart( ?string $productName, ?string $details = null ): void
    {
        match ( (string) config( 'artisanpack.ecommerce-storefront-livewire.cart.after_add', 'drawer' ) ) {
            'toast' => $this->toastSuccess(
                __( 'Added to your cart' ),
                null === $productName ? null : implode( ' · ', array_filter( [ $productName, $details ], static fn ( ?string $part ): bool => null !== $part && '' !== trim( $part ) ) ),
                [
                    'action' => Route::has( 'artisanpack.ecommerce.storefront.cart' )
                        ? [ 'label' => __( 'View cart' ), 'url' => route( 'artisanpack.ecommerce.storefront.cart', absolute: false ) ]
                        : null,
                ],
            ),
            'none'  => null,
            default => $this->dispatch( 'ecommerce-cart-open' ),
        };
    }
}
