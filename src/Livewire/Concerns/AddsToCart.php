<?php

/**
 * Quick add-to-cart concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Exceptions\CartOperationException;
use ArtisanPackUI\Ecommerce\Models\CartItem;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;

/**
 * The `quickAdd` action behind the product card's "Add to cart" button.
 *
 * Any component that renders `<x-artisanpack-ec-sf-product-card>` with quick
 * add on uses this concern. The engine checks the product is visible,
 * sellable, priced, and in stock; its message is shown when it isn't. The
 * cart is created on the first add and the call counts against the
 * `ecommerce.cart.mutate` limit.
 *
 * Uses {@see InteractsWithStorefrontCart}, {@see RateLimitsStorefront}, and
 * {@see SendsToasts}.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
trait AddsToCart
{
    /**
     * Adds one unit of a simple product to the shopper's cart.
     *
     * @since 1.0.0
     *
     * @param  int  $productId  The product id.
     *
     * @return void
     */
    public function quickAdd( int $productId ): void
    {
        try {
            $item = $this->rateLimited(
                'ecommerce.cart.mutate',
                fn (): CartItem => app( StorefrontCartService::class )->addItem( $this->cart( true ), $productId, null, 1 ),
            );
        } catch ( CartOperationException $exception ) {
            $this->toastError( __( 'Not added to your cart' ), $exception->getMessage() );

            return;
        }

        if ( ! $item instanceof CartItem ) {
            return;
        }

        // Loaded explicitly so hosts that prevent lazy loading don't throw.
        $item->loadMissing( [ 'cart', 'product' ] );

        $this->cartChanged( $item->cart );

        $this->afterAddToCart( null === $item->product ? null : (string) $item->product->name );
    }
}
