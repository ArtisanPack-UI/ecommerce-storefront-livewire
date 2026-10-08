<?php

/**
 * Header cart button component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * `<livewire:artisanpack-ecommerce-storefront-cart-button />`
 *
 * The header's cart button (spec §7.3, S15): a link to the cart page with
 * a count badge and the accessible name "Cart, 3 items". When the cart
 * drawer is on the page, clicking it opens the drawer instead, and focus
 * comes back to it when the drawer closes. The count follows
 * `ecommerce-cart-updated` (recounted here, not taken from the event).
 *
 * The package layout renders it as the `cart` header action; a host layout
 * can place it anywhere.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class HeaderButton extends Component
{
    /**
     * Units in the cart.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $count = 0;

    /**
     * Counts the cart.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->count = app( StorefrontCart::class )->count();
    }

    /**
     * Recounts the cart after a change.
     *
     * @since 1.0.0
     *
     * @return void
     */
    #[On( 'ecommerce-cart-updated' )]
    public function cartUpdated(): void
    {
        $this->count = app( StorefrontCart::class )->count();
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
        return view( 'ecommerce-storefront::livewire.cart.header-button', [
            'cartUrl' => Route::has( 'artisanpack.ecommerce.storefront.cart' ) ? route( 'artisanpack.ecommerce.storefront.cart' ) : null,
        ] );
    }
}
