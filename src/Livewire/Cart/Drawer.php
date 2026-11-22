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

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Services\CurrencyConverter;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\ManagesCartLines;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-cart-drawer />`
 *
 * The slide-out mini-cart (spec §7.3, S15; rebuilt in #95): a
 * free-shipping meter, the cart's lines with a quantity stepper and
 * "Remove" (with "Undo"), then the subtotal and "View cart" and
 * "Checkout" in the footer, in an `x-artisanpack-drawer` from the end
 * edge.
 *
 * The meter shows when `cart.free_shipping_threshold` is set, or when a
 * promotion has made shipping free.
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
     * Lists the lines when the drawer opens, re-reading the quantities on
     * every open: the cart may have changed somewhere the drawer never
     * heard about (another tab, a host action that sent no event).
     *
     * @since 1.0.0
     * @since 2.0.0 Re-syncs the quantities on every open, not just the first.
     *
     * @return void
     */
    #[On( 'ecommerce-cart-open' )]
    public function opened(): void
    {
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
            'freeShipping'  => null === $cart || [] === $lines ? null : $this->freeShipping( $cart ),
        ] );
    }

    /**
     * The free-shipping meter: whether shipping is free, how much more
     * unlocks it (formatted), and the progress in percent. Null when there
     * is no threshold and no promotion made shipping free, or the
     * threshold can't be shown in the cart's currency.
     *
     * The cart's lines after discounts are measured, as the engine's
     * free-shipping method does.
     *
     * @since 2.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return array{unlocked: bool, remaining: string|null, percent: int}|null
     */
    protected function freeShipping( Cart $cart ): ?array
    {
        $promoted = [] !== (array) ( ( (array) ( $cart->meta ?? [] ) )[ StorefrontCartService::FREE_SHIPPING_META_KEY ] ?? [] );

        if ( $promoted ) {
            return [ 'unlocked' => true, 'remaining' => null, 'percent' => 100 ];
        }

        $configured = config( 'artisanpack.ecommerce-storefront-livewire.cart.free_shipping_threshold' );

        if ( null === $configured ) {
            return null;
        }

        try {
            $threshold = app( CurrencyConverter::class )->fromConfigured( $configured, (string) $cart->currency );
        } catch ( Throwable $exception ) {
            report( $exception );

            return null;
        }

        $target = null === $threshold ? 0 : (int) $threshold->getAmount();

        if ( $target <= 0 ) {
            return null;
        }

        $subtotal = max( 0, (int) $cart->items()->sum( 'line_total_amount' ) - max( 0, (int) $cart->discount_amount ) );

        if ( $subtotal >= $target ) {
            return [ 'unlocked' => true, 'remaining' => null, 'percent' => 100 ];
        }

        return [
            'unlocked'  => false,
            'remaining' => ( new Money( $target - $subtotal, (string) $cart->currency ) )->formatted(),
            'percent'   => (int) floor( $subtotal / $target * 100 ),
        ];
    }
}
