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
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
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
 * The drawer is on every page, so its lines (and their images) are only
 * looked up once it has first opened; until then it renders just the
 * item count in its title, or the empty state.
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
     * Whether the drawer has opened, so its lines are listed.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $loaded = false;

    /**
     * Lists the lines the first time the drawer opens.
     *
     * @since 1.0.0
     *
     * @return void
     */
    #[On( 'ecommerce-cart-open' )]
    public function opened(): void
    {
        if ( $this->loaded ) {
            return;
        }

        $this->loaded = true;
        $this->syncQuantities();
    }

    /**
     * Follows changes made elsewhere (add to cart, the cart page). Its own
     * `ecommerce-cart-updated` (sent with `source` set to this class) is
     * skipped without re-rendering: the change that sent it already did.
     *
     * @since 1.0.0
     *
     * @param  string|null  $source  The component that changed the cart.
     *
     * @return void
     */
    #[On( 'ecommerce-cart-updated' )]
    public function cartUpdated( ?string $source = null ): void
    {
        if ( static::class === $source ) {
            $this->skipRender();

            return;
        }

        if ( $this->loaded ) {
            $this->syncQuantities();
        }
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
        $lines = null === $cart || ! $this->loaded ? [] : $this->lines( $cart );

        return view( 'ecommerce-storefront::livewire.cart.drawer', [
            'cart'          => $cart,
            'lines'         => $lines,
            'count'         => app( StorefrontCart::class )->count(),
            'hasUnsellable' => [] !== array_filter( $lines, static fn ( array $line ): bool => null !== $line['unsellable'] ),
            'onlyShort'     => [] === array_filter( $lines, static fn ( array $line ): bool => null !== $line['unsellable'] && ! $line['short'] ),
            'cartUrl'       => Route::has( 'artisanpack.ecommerce.storefront.cart' ) ? route( 'artisanpack.ecommerce.storefront.cart' ) : null,
            'checkoutUrl'   => Route::has( 'artisanpack.ecommerce.storefront.checkout' ) ? route( 'artisanpack.ecommerce.storefront.checkout' ) : null,
            'catalogUrl'    => Route::has( 'artisanpack.ecommerce.storefront.catalog' ) ? route( 'artisanpack.ecommerce.storefront.catalog' ) : null,
        ] );
    }
}
