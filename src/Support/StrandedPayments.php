<?php

/**
 * Payments taken for a checkout that can't become its order.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;
use RuntimeException;
use Throwable;

/**
 * Spots and records a "stranded" payment: one the gateway confirmed
 * (authorized, succeeded, or processing) that the engine won't place the
 * order with, because it was made for an earlier cart total, or for a
 * payment session the cart has since replaced (another tab, a reload).
 *
 * The shopper has been charged, so the checkout must never ask them to pay
 * again. Recording one:
 *
 * - reports it (`report()`), so it shows in the store's error tracking;
 * - fires `ap.ecommerceStorefrontLivewire.checkout.paymentMismatch` with
 *   the cart, the gateway key, the session reference and the session, so
 *   the store can alert staff, refund, or place the order by hand;
 * - keeps it in the session for that cart, so the checkout stays locked
 *   (no new payment session, a "we've received your payment" notice) across
 *   reloads and the provider's return route.
 *
 * The checkout also remembers which payment sessions it showed for a cart
 * ({@see self::remember()}), so a confirmation or a return for a session
 * the cart has since replaced is recognised as this shopper's payment
 * rather than refused as someone else's.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */
final class StrandedPayments
{
    /**
     * Where a stranded payment is kept: `cart` and `reference`.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const SESSION_KEY = 'ecommerce-storefront.checkout.stranded-payment';

    /**
     * Where the payment sessions the checkout showed are kept: a list of
     * `cart`, `gateway` and `reference`.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const SHOWN_SESSION_KEY = 'ecommerce-storefront.checkout.shown-payments';

    /**
     * How many shown payment sessions are kept.
     *
     * @since 2.0.0
     *
     * @var int
     */
    public const SHOWN_LIMIT = 10;

    /**
     * Whether `$session` is confirmed by the gateway but doesn't pay for
     * the cart as it is now (another total or currency).
     *
     * @since 2.0.0
     *
     * @param  PaymentSession|null  $session  The session, as the gateway reports it.
     * @param  Cart                 $cart     The cart.
     *
     * @return bool
     */
    public static function isStranded( ?PaymentSession $session, Cart $cart ): bool
    {
        return null !== $session && $session->isConfirmed() && ! PaymentSessions::confirmedFor( $session, $cart );
    }

    /**
     * Asks the gateway for a session; null when the gateway is unknown or
     * can't be reached (reported).
     *
     * @since 2.0.0
     *
     * @param  string  $gatewayKey  The gateway key.
     * @param  string  $reference   The session reference.
     *
     * @return PaymentSession|null
     */
    public static function retrieve( string $gatewayKey, string $reference ): ?PaymentSession
    {
        $gateway = '' === $gatewayKey || '' === $reference ? null : app( PaymentGatewayRegistry::class )->find( $gatewayKey );

        if ( null === $gateway ) {
            return null;
        }

        try {
            return $gateway->retrievePaymentSession( $reference );
        } catch ( Throwable $exception ) {
            report( $exception );

            return null;
        }
    }

    /**
     * Records a stranded payment for `$cart`: reports it, fires
     * `ap.ecommerceStorefrontLivewire.checkout.paymentMismatch`, and keeps
     * it in the session. Recording the same payment again does nothing
     * more than keep it.
     *
     * @since 2.0.0
     *
     * @param  Cart            $cart        The cart the payment was for.
     * @param  string          $gatewayKey  The gateway that took it.
     * @param  PaymentSession  $session     The confirmed session.
     *
     * @return void
     */
    public static function record( Cart $cart, string $gatewayKey, PaymentSession $session ): void
    {
        if ( $session->reference === self::for( $cart ) ) {
            return;
        }

        report( new RuntimeException( sprintf(
            'Checkout payment %s (%s, %d %s) was confirmed for cart %d, but can\'t place its order (cart total %d %s).',
            $session->reference,
            $gatewayKey,
            (int) $session->amount->getAmount(),
            $session->amount->getCurrency()->getCode(),
            (int) $cart->id,
            (int) $cart->total_amount,
            (string) $cart->currency,
        ) ) );

        if ( app()->bound( 'session' ) ) {
            session()->put( self::SESSION_KEY, [ 'cart' => (int) $cart->id, 'reference' => $session->reference ] );
        }

        doAction( 'ap.ecommerceStorefrontLivewire.checkout.paymentMismatch', $cart, $gatewayKey, $session->reference, $session );
    }

    /**
     * The stranded payment kept for `$cart`, or null.
     *
     * @since 2.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return string|null The session reference.
     */
    public static function for( Cart $cart ): ?string
    {
        $stranded = app()->bound( 'session' ) ? session()->get( self::SESSION_KEY ) : null;

        if ( ! is_array( $stranded ) || (int) ( $stranded['cart'] ?? 0 ) !== (int) $cart->id || ! is_string( $stranded['reference'] ?? null ) ) {
            return null;
        }

        return $stranded['reference'];
    }

    /**
     * Remembers that the checkout showed `$reference` (from `$gatewayKey`)
     * for `$cart`.
     *
     * @since 2.0.0
     *
     * @param  Cart    $cart        The cart.
     * @param  string  $gatewayKey  The gateway key.
     * @param  string  $reference   The session reference.
     *
     * @return void
     */
    public static function remember( Cart $cart, string $gatewayKey, string $reference ): void
    {
        if ( ! app()->bound( 'session' ) || '' === $gatewayKey || '' === $reference || null !== self::shownGateway( $cart, $reference ) ) {
            return;
        }

        $shown   = self::shown();
        $shown[] = [ 'cart' => (int) $cart->id, 'gateway' => $gatewayKey, 'reference' => $reference ];

        session()->put( self::SHOWN_SESSION_KEY, array_slice( $shown, -self::SHOWN_LIMIT ) );
    }

    /**
     * The gateway of a payment session the checkout showed for `$cart`, or
     * null when it didn't show `$reference`.
     *
     * @since 2.0.0
     *
     * @param  Cart    $cart       The cart.
     * @param  string  $reference  The session reference.
     *
     * @return string|null
     */
    public static function shownGateway( Cart $cart, string $reference ): ?string
    {
        if ( '' === $reference ) {
            return null;
        }

        foreach ( self::shown() as $entry ) {
            if ( (int) $cart->id === $entry['cart'] && hash_equals( $entry['reference'], $reference ) ) {
                return $entry['gateway'];
            }
        }

        return null;
    }

    /**
     * What the shopper is told about a stranded payment.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public static function title(): string
    {
        return __( 'We\'ve received your payment, but haven\'t placed your order' );
    }

    /**
     * What the shopper is told to do about a stranded payment.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public static function message(): string
    {
        return __( 'Your payment went through for an earlier order total, so we haven\'t placed your order yet. Don\'t pay again: contact us and we\'ll sort it out.' );
    }

    /**
     * The payment sessions the checkout showed.
     *
     * @since 2.0.0
     *
     * @return array<int, array{cart: int, gateway: string, reference: string}>
     */
    private static function shown(): array
    {
        $shown = app()->bound( 'session' ) ? session()->get( self::SHOWN_SESSION_KEY ) : null;

        if ( ! is_array( $shown ) ) {
            return [];
        }

        return array_values( array_filter( $shown, static fn ( mixed $entry ): bool => is_array( $entry )
            && is_int( $entry['cart'] ?? null )
            && is_string( $entry['gateway'] ?? null )
            && is_string( $entry['reference'] ?? null ) ) );
    }
}
