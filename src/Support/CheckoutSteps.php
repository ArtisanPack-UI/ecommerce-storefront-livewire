<?php

/**
 * Checkout steps.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\Cart;

/**
 * The checkout's steps, in order (spec §7.4, §8.4).
 *
 * The core steps are `contact`, `address`, `shipping` (only for carts that
 * ship), `payment`, and `review`. A satellite adds a step through the
 * `ap.ecommerceStorefrontLivewire.checkout.steps` filter, which receives
 * the steps (key => definition) and the cart:
 *
 * ```php
 * addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', function ( array $steps, Cart $cart ): array {
 *     $steps['age-check'] = [
 *         'label'     => __( 'Date of birth' ),
 *         'component' => 'age-check-checkout-step',
 *         'before'    => 'payment', // optional; `review` by default
 *     ];
 *
 *     return $steps;
 * } );
 * ```
 *
 * An added step is a Livewire component, mounted with a `step` prop (its
 * key). When the shopper has completed it, it dispatches
 * `ecommerce-checkout-step-completed` with `step: <key>` and the checkout
 * moves on. That event comes from the browser, so a step that must hold
 * the shopper back (an age check) also refuses the engine's next state
 * through `ap.ecommerce.checkout.canTransitionTo`; the checkout shows the
 * refusal.
 *
 * Core steps can't be removed, relabelled, or reordered. Added steps sit
 * before `before` (`address`, `shipping`, `payment`, or `review`; a
 * skipped `shipping` means the next core step), in the order they were
 * added; an entry without a label or component, or with an invalid key, is
 * ignored.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class CheckoutSteps
{
    /**
     * The core steps, in order.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const CORE = [ 'contact', 'address', 'shipping', 'payment', 'review' ];

    /**
     * The core steps an added step may be placed before.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const INSERTABLE_BEFORE = [ 'address', 'shipping', 'payment', 'review' ];

    /**
     * The steps for `$cart`, keyed by step key.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart              The cart.
     * @param  bool  $requiresShipping  Whether the cart ships (the shipping step is skipped otherwise).
     *
     * @return array<string, array{key: string, label: string, component: string|null}>
     */
    public static function for( Cart $cart, bool $requiresShipping ): array
    {
        $core = self::core( $requiresShipping );

        $filtered = applyFilters(
            'ap.ecommerceStorefrontLivewire.checkout.steps',
            array_map( static fn ( array $step ): array => [ 'label' => $step['label'], 'component' => null ], $core ),
            $cart,
        );

        $added = [];

        foreach ( is_array( $filtered ) ? $filtered : [] as $key => $step ) {
            if ( isset( $core[ $key ] ) || in_array( $key, self::CORE, true ) || ! self::isValid( $key, $step ) ) {
                continue;
            }

            $before = is_string( $step['before'] ?? null ) && in_array( $step['before'], self::INSERTABLE_BEFORE, true ) ? $step['before'] : 'review';

            if ( ! isset( $core[ $before ] ) ) {
                $before = 'payment';
            }

            $added[ $before ][ $key ] = [ 'key' => $key, 'label' => trim( $step['label'] ), 'component' => trim( $step['component'] ) ];
        }

        $steps = [];

        foreach ( $core as $key => $step ) {
            foreach ( $added[ $key ] ?? [] as $extraKey => $extra ) {
                $steps[ $extraKey ] = $extra;
            }

            $steps[ $key ] = $step;
        }

        return $steps;
    }

    /**
     * The core steps for a cart that does (or doesn't) ship (also what
     * the Checkout Steps block previews).
     *
     * @since 1.0.0
     *
     * @param  bool  $requiresShipping  Whether the cart ships.
     *
     * @return array<string, array{key: string, label: string, component: null}>
     */
    public static function core( bool $requiresShipping ): array
    {
        $labels = [
            'contact'  => __( 'Contact' ),
            'address'  => $requiresShipping ? __( 'Address' ) : __( 'Billing address' ),
            'shipping' => __( 'Shipping' ),
            'payment'  => __( 'Payment' ),
            'review'   => __( 'Review order' ),
        ];

        if ( ! $requiresShipping ) {
            unset( $labels['shipping'] );
        }

        $steps = [];

        foreach ( $labels as $key => $label ) {
            $steps[ $key ] = [ 'key' => $key, 'label' => $label, 'component' => null ];
        }

        return $steps;
    }

    /**
     * Whether an added step's key and definition can be used.
     *
     * @since 1.0.0
     *
     * @param  mixed  $key   Step key.
     * @param  mixed  $step  Step definition.
     *
     * @return bool
     */
    protected static function isValid( mixed $key, mixed $step ): bool
    {
        return is_string( $key )
            && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $key )
            && is_array( $step )
            && is_string( $step['label'] ?? null ) && '' !== trim( $step['label'] )
            && is_string( $step['component'] ?? null ) && '' !== trim( $step['component'] );
    }
}
