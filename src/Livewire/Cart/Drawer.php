<?php

/**
 * Cart drawer component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart;

use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\ManagesCartLines;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * `<livewire:artisanpack-ecommerce-storefront-cart-drawer />`
 *
 * The slide-out mini-cart (spec §7.3, S15): the cart's lines with a
 * quantity stepper and "Remove" (with "Undo"), the subtotal, and "View
 * cart" and "Checkout" links, in an `x-artisanpack-drawer` from the end
 * edge.
 *
 * It opens on the `ecommerce-cart-open` browser event (the header cart
 * button, and "Add to cart" when `cart.after_add` is `drawer`) and closes
 * on `ecommerce-cart-close`, Escape, or the overlay. Focus moves into the
 * drawer while it is open and returns to the control that opened it; it
 * dispatches `ecommerce-cart-closed` once closed. The lines follow
 * `ecommerce-cart-updated`.
 *
 * Mounted once by `ecommerce-storefront::partials.global`, so it works in
 * a host's own layout too.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Drawer extends Component
{
    use InteractsWithStorefrontCart;
    use ManagesCartLines;
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * Picks up the quantities.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->syncQuantities();
    }

    /**
     * Follows changes made elsewhere (add to cart, the cart page).
     *
     * @since 1.0.0
     *
     * @return void
     */
    #[On( 'ecommerce-cart-updated' )]
    public function cartUpdated(): void
    {
        $this->syncQuantities();
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $cart  = $this->cart();
        $lines = null === $cart ? [] : $this->lines( $cart );

        return view( 'ecommerce-storefront::livewire.cart.drawer', [
            'cart'          => $cart,
            'lines'         => $lines,
            'count'         => array_sum( array_column( $lines, 'quantity' ) ),
            'hasUnsellable' => [] !== array_filter( $lines, static fn ( array $line ): bool => null !== $line['unsellable'] ),
            'cartUrl'       => Route::has( 'artisanpack.ecommerce.storefront.cart' ) ? route( 'artisanpack.ecommerce.storefront.cart' ) : null,
            'checkoutUrl'   => Route::has( 'artisanpack.ecommerce.storefront.checkout' ) ? route( 'artisanpack.ecommerce.storefront.checkout' ) : null,
            'catalogUrl'    => Route::has( 'artisanpack.ecommerce.storefront.catalog' ) ? route( 'artisanpack.ecommerce.storefront.catalog' ) : null,
        ] );
    }
}
