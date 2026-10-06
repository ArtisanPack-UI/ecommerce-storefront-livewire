<?php

/**
 * One-time action tokens.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Mints and checks the tokens that make money-moving storefront actions
 * idempotent (spec §5.2).
 *
 * The REST API protects such calls with an `Idempotency-Key`; Livewire calls
 * engine services in-process, so the button carries a token minted when it
 * renders instead. A double click sends the same token twice, and the
 * engine's `IdempotentAction` runs the action once and replays its result
 * for the repeat (see {@see \ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\WithActionToken}).
 *
 * A token is a nonce and an issue time, signed with the app key over the
 * shopper (user id, or session id for guests), the scope, and the nonce, so
 * it can't be forged or replayed by another shopper.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class ActionTokens
{
    /**
     * How long a minted token stays usable, in seconds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const TTL_SECONDS = 43_200;

    /**
     * Mints a token for the current shopper and a scope.
     *
     * @since 1.0.0
     *
     * @param  string  $scope  What it authorizes, e.g. `place-order|cart:12`.
     *
     * @return string
     */
    public static function mint( string $scope ): string
    {
        $payload = Str::random( 40 ) . '.' . Carbon::now()->getTimestamp();

        return $payload . '.' . self::signature( $scope, $payload );
    }

    /**
     * Whether a token was minted for the current shopper and scope and has
     * not expired. Says nothing about whether it has been used.
     *
     * @since 1.0.0
     *
     * @param  string  $scope  The scope.
     * @param  string  $token  The submitted token.
     *
     * @return bool
     */
    public static function isValid( string $scope, string $token ): bool
    {
        $parts = explode( '.', $token );

        if ( 3 !== count( $parts ) || 1 !== preg_match( '/^[A-Za-z0-9]{40}$/D', $parts[0] ) || 1 !== preg_match( '/^[0-9]{1,12}$/D', $parts[1] ) ) {
            return false;
        }

        [ $nonce, $issuedAt, $signature ] = $parts;

        if ( ! hash_equals( self::signature( $scope, $nonce . '.' . $issuedAt ), $signature ) ) {
            return false;
        }

        $age = Carbon::now()->getTimestamp() - (int) $issuedAt;

        return $age >= 0 && $age <= self::TTL_SECONDS;
    }

    /**
     * The signature over the shopper, scope, and payload.
     *
     * @since 1.0.0
     *
     * @param  string  $scope    The scope.
     * @param  string  $payload  The nonce and issue time.
     *
     * @return string
     */
    private static function signature( string $scope, string $payload ): string
    {
        return hash_hmac( 'sha256', self::actor() . '|' . $scope . '|' . $payload, 'ecommerce-storefront-action-token|' . config( 'app.key' ) );
    }

    /**
     * Who the token belongs to: the signed-in user, else the session.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private static function actor(): string
    {
        $user = auth()->user();

        if ( null !== $user ) {
            return 'user:' . $user->getAuthIdentifier();
        }

        return 'session:' . ( app()->bound( 'session' ) ? (string) session()->getId() : '' );
    }
}
