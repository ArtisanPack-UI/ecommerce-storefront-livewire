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

/**
 * Gives a component the shopper's cart and announces cart changes.
 *
 * After any change, call {@see self::cartChanged()}: it dispatches
 * `ecommerce-cart-updated` (a browser and Livewire event) with the new item
 * count, which the header cart button, drawer, and any host listener pick
 * up (spec §5.1).
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

        $this->dispatch( 'ecommerce-cart-updated', count: $carts->count() );
    }

    /**
     * Gives feedback after an "Add to cart", per `cart.after_add`:
     * `drawer` (the default) dispatches `ecommerce-cart-open` for the cart
     * drawer, `toast` shows a toast naming the product, `none` does nothing
     * (the header count still updates). Needs {@see SendsToasts}.
     *
     * @since 1.0.0
     *
     * @param  string|null  $productName  The product added, for the toast.
     *
     * @return void
     */
    protected function afterAddToCart( ?string $productName ): void
    {
        match ( (string) config( 'artisanpack.ecommerce-storefront-livewire.cart.after_add', 'drawer' ) ) {
            'toast' => $this->toastSuccess(
                __( 'Added to your cart' ),
                null === $productName ? null : __( ':name is in your cart.', [ 'name' => $productName ] ),
            ),
            'none'  => null,
            default => $this->dispatch( 'ecommerce-cart-open' ),
        };
    }
}
