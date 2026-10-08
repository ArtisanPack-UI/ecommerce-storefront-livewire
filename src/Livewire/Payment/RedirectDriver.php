<?php

/**
 * Redirect payment driver.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Payment;

use Illuminate\Contracts\View\View;

/**
 * `redirect` — the core driver for any gateway that sends the shopper away
 * to pay and back (spec §8.3).
 *
 * Shows "Continue to {gateway}"; {@see self::confirm()} sends the shopper
 * to the session's `redirect_url`. The provider sends them back to
 * `/shop/checkout/return`, which checks the session with the gateway and
 * resumes the checkout at the next step (or back at payment, with the
 * reason, when the payment didn't go through).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class RedirectDriver extends PaymentDriver
{
    /**
     * Sends the shopper to the provider.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function confirm(): void
    {
        $url = $this->redirectUrl();

        if ( null === $url ) {
            $this->failed( __( 'This payment method isn\'t available.' ) );

            return;
        }

        $this->redirect( $url );
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-storefront::livewire.payment.redirect', [
            'available' => null !== $this->redirectUrl(),
            'label'     => '' === $this->gatewayLabel ? __( 'the payment provider' ) : $this->gatewayLabel,
        ] );
    }

    /**
     * The provider's URL, when it is an absolute http(s) URL.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    protected function redirectUrl(): ?string
    {
        $url = $this->config['redirect_url'] ?? null;

        if ( ! is_string( $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return null;
        }

        return in_array( strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ), [ 'https', 'http' ], true ) ? $url : null;
    }
}
