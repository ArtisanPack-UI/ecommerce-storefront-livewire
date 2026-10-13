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
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Gate;

/**
 * Finds an order a shopper may see (spec §6): their own, authorized by the
 * engine's order policy, or one a signed order-view link (E12) names.
 * Anything else is null, so callers answer 404 rather than 403 and an
 * order's existence isn't given away. `canClaim()` decides who may claim
 * guest orders.
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
     * Whether `$user` may claim guest orders placed with their email.
     *
     * A claim takes over every unclaimed guest order under the account's
     * email, so the account must have proved it owns that email: with
     * `account.claim_requires_verified_email` (the default) the user model
     * must implement `MustVerifyEmail` and be verified; without it, only an
     * unverified `MustVerifyEmail` user is refused.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The signed-in user.
     *
     * @return bool
     */
    public static function canClaim( ?Authenticatable $user ): bool
    {
        if ( null === $user ) {
            return false;
        }

        if ( $user instanceof MustVerifyEmail ) {
            return $user->hasVerifiedEmail();
        }

        return ! (bool) config( 'artisanpack.ecommerce-storefront-livewire.account.claim_requires_verified_email', true );
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
