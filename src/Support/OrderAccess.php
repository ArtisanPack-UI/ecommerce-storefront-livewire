<?php

/**
 * Order access for shoppers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use Illuminate\Support\Facades\Gate;

/**
 * Finds an order a shopper may see (spec §6): their own, authorized by the
 * engine's order policy, or one a signed order-view link (E12) names.
 * Anything else is null, so callers answer 404 rather than 403 and an
 * order's existence isn't given away.
 *
 * Orders are referenced by id in storefront URLs; the order number is what
 * the shopper reads.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class OrderAccess
{
    /**
     * The signed-in shopper's order `$reference`, or null when it isn't
     * theirs (or they have no customer record).
     *
     * @since 1.0.0
     *
     * @param  string  $reference  The order id.
     *
     * @return Order|null
     */
    public static function owned( string $reference ): ?Order
    {
        $user     = auth()->user();
        $customer = Customer::forUser( $user );

        if ( null === $user || null === $customer || ! ctype_digit( $reference ) ) {
            return null;
        }

        $order = Order::query()->forCustomer( $customer )->with( 'items' )->find( (int) $reference );

        return null !== $order && Gate::forUser( $user )->allows( 'view', $order ) ? $order : null;
    }

    /**
     * Order `$reference`, when `$token` is a valid order-view token for it.
     *
     * @since 1.0.0
     *
     * @param  string  $reference  The order id.
     * @param  string  $token      The signed order-view token.
     *
     * @return Order|null
     */
    public static function signed( string $reference, string $token ): ?Order
    {
        if ( '' === $token || ! ctype_digit( $reference ) ) {
            return null;
        }

        $order = OrderViewToken::verify( $token );

        return null !== $order && (int) $order->id === (int) $reference ? $order->load( 'items' ) : null;
    }
}
