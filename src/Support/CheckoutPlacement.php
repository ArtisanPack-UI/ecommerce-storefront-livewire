<?php

/**
 * Placed-but-unpaid checkout tracking.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use Illuminate\Support\Facades\Route;

/**
 * Remembers, in the shopper's session, an order checkout placed whose
 * payment isn't finished (spec §7.4 step 4).
 *
 * Placing the order turns the cart into the order, so after a challenged
 * payment (3-D Secure, a redirect) or a declined capture there is no open
 * cart left to check out. The checkout and the payment return route use
 * this to find the order again — after a reload or the provider's
 * redirect — and finish paying for it through the engine's `finalize()`,
 * which is idempotent per cart.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class CheckoutPlacement
{
    /**
     * The session key holding the cart and order ids.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SESSION_KEY = 'ecommerce-storefront.checkout.placement';

    /**
     * Remembers the order placed from `$cart`.
     *
     * @since 1.0.0
     *
     * @param  Cart   $cart   The (now converted) cart.
     * @param  Order  $order  The order.
     *
     * @return void
     */
    public static function remember( Cart $cart, Order $order ): void
    {
        if ( app()->bound( 'session' ) ) {
            session()->put( self::SESSION_KEY, [ 'cart' => (int) $cart->id, 'order' => (int) $order->id ] );
        }
    }

    /**
     * The remembered cart and order, when the cart still became that
     * order. Forgets an entry that no longer matches.
     *
     * @since 1.0.0
     *
     * @return array{cart: Cart, order: Order}|null
     */
    public static function current(): ?array
    {
        $stored = app()->bound( 'session' ) ? session()->get( self::SESSION_KEY ) : null;

        if ( ! is_array( $stored ) || ! is_int( $stored['cart'] ?? null ) || ! is_int( $stored['order'] ?? null ) ) {
            return null;
        }

        $cart  = Cart::query()->find( $stored['cart'] );
        $order = Order::query()->find( $stored['order'] );

        if ( null === $cart || null === $order || (int) $cart->completed_order_id !== (int) $order->id ) {
            self::forget();

            return null;
        }

        return [ 'cart' => $cart, 'order' => $order ];
    }

    /**
     * Forgets the remembered order.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function forget(): void
    {
        if ( app()->bound( 'session' ) ) {
            session()->forget( self::SESSION_KEY );
        }
    }

    /**
     * Whether the order no longer needs paying.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return bool
     */
    public static function isPaid( Order $order ): bool
    {
        return in_array( (string) $order->payment_status, [ 'paid', 'partially_refunded', 'refunded' ], true );
    }

    /**
     * The order's confirmation page, signed for anyone who can't see it as
     * its owner; null when the storefront routes are off.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string|null
     */
    public static function confirmationUrl( Order $order ): ?string
    {
        if ( ! Route::has( 'artisanpack.ecommerce.storefront.confirmation' ) ) {
            return null;
        }

        $parameters = [ 'order' => $order->id ];

        if ( null === OrderAccess::owned( (string) $order->id ) ) {
            $parameters['token'] = OrderViewToken::for( $order );
        }

        return route( 'artisanpack.ecommerce.storefront.confirmation', $parameters );
    }
}
