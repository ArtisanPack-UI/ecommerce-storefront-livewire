<?php

/**
 * Marketing consent given at checkout.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index as Checkout;
use Throwable;

/**
 * Keeps the marketing consent a shopper gave at checkout once the order is
 * placed (decision D3).
 *
 * The checkout's contact step keeps the choice on the cart
 * (`carts.meta.storefront_marketing_consent`) until the order is placed.
 * On the engine's `ap.ecommerce.checkout.finalized` action — which also
 * fires when the provider's webhook places the order — a "yes" is
 * recorded twice:
 *
 * - on the order, as the audit trail:
 *   `orders.meta.marketing_consent = [ 'given' => true, 'at' => ISO 8601,
 *   'source' => 'storefront-checkout' ]`, plus `customer_id` (the customer
 *   whose flag was set), `customer_created` (true when the guest's
 *   customer record was created for it), and `applied` (since 2.0.0:
 *   whether the customer's flag was set);
 * - on the customer, as `accepts_marketing` (engine
 *   `CustomerService::updateProfile()`, which records when consent was
 *   given): the order's customer, or — for a guest — the customer for the
 *   order's email (`findOrCreateForEmail()`). A guest checkout never
 *   changes the preferences of a customer linked to a user account: that
 *   shopper manages them signed in. Nor does it opt in a customer record
 *   that already existed (from earlier guest orders) and hasn't opted in:
 *   the email isn't verified, and that customer may have opted out. The
 *   consent is kept on the order with `applied` false, and
 *   `ap.ecommerceStorefrontLivewire.checkout.marketingConsentPending`
 *   fires with the order and the customer, so the host can ask that
 *   address to confirm (double opt-in).
 *
 * Unticked, nothing is written. A failure is reported and never holds up
 * the order.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class CheckoutMarketingConsent
{
    /**
     * The `orders.meta` key the consent is recorded under.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ORDER_META_KEY = 'marketing_consent';

    /**
     * Listens for placed orders.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function boot(): void
    {
        addAction( 'ap.ecommerce.checkout.finalized', static function ( mixed $order = null, mixed $cart = null ): void {
            if ( $order instanceof Order && $cart instanceof Cart ) {
                self::record( $order, $cart );
            }
        } );
    }

    /**
     * Records the consent given on `$cart` for the order it became.
     *
     * @since 1.0.0
     * @since 2.0.0 Leaves an existing guest customer's flag alone and fires `marketingConsentPending`.
     *
     * @param  Order  $order  The order placed.
     * @param  Cart   $cart   The cart it was placed from.
     *
     * @return void
     */
    public static function record( Order $order, Cart $cart ): void
    {
        if ( true !== ( ( (array) ( $cart->meta ?? [] ) )[ Checkout::MARKETING_CONSENT_META_KEY ] ?? false ) ) {
            return;
        }

        try {
            $order = $order->fresh() ?? $order;
            $meta  = (array) ( $order->meta ?? [] );

            if ( is_array( $meta[ self::ORDER_META_KEY ] ?? null ) ) {
                return;
            }

            [ $customer, $created ] = self::customer( $order );

            // A guest's tick can't opt in a customer record that already
            // existed: the email isn't verified, and that customer may have
            // opted out. The host is asked to confirm it instead.
            $pending = null !== $customer && ! $created && null === $order->customer_id && true !== (bool) $customer->accepts_marketing;

            if ( null !== $customer && ! $pending ) {
                app( CustomerService::class )->updateProfile( $customer, [ 'accepts_marketing' => true ] );
            }

            $meta[ self::ORDER_META_KEY ] = [
                'given'            => true,
                'at'               => now()->toIso8601String(),
                'source'           => 'storefront-checkout',
                'customer_id'      => $customer?->id,
                'customer_created' => $created,
                'applied'          => null !== $customer && ! $pending,
            ];

            $order->meta = $meta;
            $order->save();

            if ( $pending ) {
                doAction( 'ap.ecommerceStorefrontLivewire.checkout.marketingConsentPending', $order, $customer );
            }
        } catch ( Throwable $exception ) {
            report( $exception );
        }
    }

    /**
     * Whether `$order`'s consent write created `$customer` (a guest's
     * customer record that didn't exist before).
     *
     * @since 1.0.0
     *
     * @param  Order     $order     The order.
     * @param  Customer  $customer  The customer.
     *
     * @return bool
     */
    public static function createdCustomerFor( Order $order, Customer $customer ): bool
    {
        $consent = ( (array) ( $order->meta ?? [] ) )[ self::ORDER_META_KEY ] ?? null;

        return is_array( $consent )
            && true === ( $consent['customer_created'] ?? false )
            && (int) ( $consent['customer_id'] ?? 0 ) === (int) $customer->id;
    }

    /**
     * The customer whose flag is set, and whether it was created for this:
     * the order's customer, else (a guest) the customer for its email,
     * unless that one belongs to a user account.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array{0: Customer|null, 1: bool}
     */
    private static function customer( Order $order ): array
    {
        if ( null !== $order->customer_id ) {
            return [ Customer::query()->find( $order->customer_id ), false ];
        }

        $email = trim( (string) $order->email );

        if ( '' === $email ) {
            return [ null, false ];
        }

        $customer = app( CustomerService::class )->findOrCreateForEmail( $email );

        if ( null !== $customer->user_id ) {
            return [ null, false ];
        }

        return [ $customer, $customer->wasRecentlyCreated ];
    }
}
