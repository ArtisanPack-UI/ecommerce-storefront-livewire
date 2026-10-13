<?php

/**
 * Payment driver registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Registries;

use InvalidArgumentException;

/**
 * Maps a payment driver to the Livewire component that renders its payment
 * UI in the checkout's payment step (spec §8.3, decision D5).
 *
 * The driver is the `driver` value of the engine's client-render contract
 * (`RendersClientPayment::clientConfig()`, through `ClientPaymentConfig`).
 * The storefront registers `redirect` and `stripe-payment-element`; a
 * gateway satellite registers its own from its service provider's
 * `boot()`:
 *
 * ```php
 * use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\PaymentDriverRegistry;
 *
 * if ( class_exists( PaymentDriverRegistry::class ) ) {
 *     app( PaymentDriverRegistry::class )->register( 'paypal-buttons', 'paypal-storefront-buttons' );
 * }
 * ```
 *
 * Hosts can also list drivers in the `payments.drivers` config
 * (`driver => component`). The component should extend
 * `Livewire\Payment\PaymentDriver`, which documents the props it is
 * mounted with and the `payment-confirmed` / `payment-failed` events it
 * reports. A gateway whose driver isn't registered is hidden from the
 * payment step with "This payment method isn't available".
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class PaymentDriverRegistry
{
    /**
     * Livewire component names (or classes), keyed by driver.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected array $drivers = [];

    /**
     * Registers (or replaces) the component for a driver.
     *
     * @since 1.0.0
     *
     * @param  string  $driver     The `driver` from the gateway's client config (`paypal-buttons`).
     * @param  string  $component  The Livewire component name or class.
     *
     * @throws InvalidArgumentException For an empty driver or component.
     *
     * @return void
     */
    public function register( string $driver, string $component ): void
    {
        if ( '' === trim( $driver ) || '' === trim( $component ) ) {
            throw new InvalidArgumentException( 'A payment driver needs a driver name and a Livewire component.' );
        }

        $this->drivers[ $driver ] = $component;
    }

    /**
     * Whether a driver has a registered component.
     *
     * @since 1.0.0
     *
     * @param  string  $driver  Driver name.
     *
     * @return bool
     */
    public function has( string $driver ): bool
    {
        return isset( $this->drivers[ $driver ] );
    }

    /**
     * The component for a driver, or null when none is registered.
     *
     * @since 1.0.0
     *
     * @param  string  $driver  Driver name.
     *
     * @return string|null
     */
    public function component( string $driver ): ?string
    {
        return $this->drivers[ $driver ] ?? null;
    }

    /**
     * Every registered driver, keyed by driver name.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->drivers;
    }
}
