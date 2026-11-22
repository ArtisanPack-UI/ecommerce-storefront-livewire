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
     * The email whose guest orders `$user` may claim, or null when they may
     * claim none.
     *
     * The engine claims by the customer record's email, which can be older
     * than the user's (a record is never moved to a new address). So a claim
     * is allowed only when `canClaim()` passes and the record's email is the
     * user's current, verified email; without a record yet, the user's own
     * email (the engine links or makes the record under it). Lowercased and
     * trimmed, as the engine stores order emails.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable|null  $user      The signed-in user.
     * @param  Customer|null         $customer  The user's customer record, if any.
     *
     * @return string|null
     */
    public static function claimEmail( ?Authenticatable $user, ?Customer $customer ): ?string
    {
        if ( ! self::canClaim( $user ) ) {
            return null;
        }

        $userEmail = self::normalizeEmail( $user instanceof MustVerifyEmail ? $user->getEmailForVerification() : ( $user->email ?? null ) );

        if ( null === $userEmail ) {
            return null;
        }

        if ( null === $customer ) {
            return $userEmail;
        }

        return self::normalizeEmail( $customer->email ) === $userEmail ? $userEmail : null;
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

    /**
     * `$email` lowercased and trimmed, or null when it isn't a non-empty string.
     *
     * @since 2.0.0
     *
     * @param  mixed  $email  The email.
     *
     * @return string|null
     */
    private static function normalizeEmail( mixed $email ): ?string
    {
        if ( ! is_string( $email ) || '' === trim( $email ) ) {
            return null;
        }

        return mb_strtolower( trim( $email ) );
    }
}
