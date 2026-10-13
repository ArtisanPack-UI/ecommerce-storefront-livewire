<?php

/**
 * Payment session checks.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;

/**
 * Decides whether a payment session the gateway reports pays for the cart
 * as it is now.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class PaymentSessions
{
    /**
     * Whether `$session` is confirmed (authorized, succeeded, or
     * processing) and for the cart's current total and currency. A session
     * made before the cart changed doesn't count.
     *
     * @since 1.0.0
     *
     * @param  PaymentSession  $session  The session, as the gateway reports it.
     * @param  Cart            $cart     The cart.
     *
     * @return bool
     */
    public static function confirmedFor( PaymentSession $session, Cart $cart ): bool
    {
        return $session->isConfirmed()
            && (int) $session->amount->getAmount() === (int) $cart->total_amount
            && strtoupper( $session->amount->getCurrency()->getCode() ) === strtoupper( (string) $cart->currency );
    }
}
