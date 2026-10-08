<?php

/**
 * Stripe Payment Element driver.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Payment;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Livewire;

/**
 * `stripe-payment-element` — on-site card (and wallet) entry with Stripe's
 * Payment Element (spec §8.3, parent plan §8.1, §8.5).
 *
 * The view's script loads Stripe.js from `https://js.stripe.com/v3` once
 * per page, mounts the Payment Element with the session's client secret,
 * the locale, and an appearance built from the daisyUI theme tokens
 * (`--color-primary`, `--color-base-100`, `--color-base-content`,
 * `--color-error`, `--radius-field`) with the gateway's `appearance`
 * config on top. "Pay now" calls `stripe.confirmPayment()` with
 * `redirect: 'if_required'`, so a 3-D Secure challenge runs in the page;
 * payment methods that must redirect come back through
 * `/shop/checkout/return`. A Stripe error is shown under the form in an
 * assertive live region and reported as `payment-failed`. On success the
 * script calls {@see self::confirm()} with the PaymentIntent id, which is
 * checked with Stripe before the checkout is told.
 *
 * Card data goes from the Payment Element's iframe straight to Stripe; it
 * never reaches Livewire state, a Livewire request, or the server.
 *
 * **Content Security Policy.** A host with a CSP must allow Stripe.js:
 * `script-src https://js.stripe.com`, `frame-src https://js.stripe.com
 * https://hooks.stripe.com`, and `connect-src https://api.stripe.com`
 * (see Stripe's CSP guide for wallets and 3-D Secure).
 *
 * **Manual testing** (Stripe test mode): `4242 4242 4242 4242` pays;
 * `4000 0025 0000 3155` asks for 3-D Secure; `4000 0000 0000 0002` is
 * declined ("Your card was declined"). Any future expiry and any CVC.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class StripePaymentElement extends PaymentDriver
{
    /**
     * Where Stripe.js is loaded from (Stripe requires its own CDN).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const STRIPE_JS = 'https://js.stripe.com/v3';

    /**
     * The storefront page the driver is on, for Stripe to return to when
     * the storefront's return route is off. Captured at mount, because
     * later renders run on Livewire's update endpoint.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $pageUrl = '';

    /**
     * Remembers the page the driver is on.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->pageUrl = Livewire::originalUrl();
    }

    /**
     * Checks the PaymentIntent the Payment Element confirmed and reports
     * it to the checkout. Called by the view's script with the
     * PaymentIntent id once `confirmPayment()` succeeds.
     *
     * @since 1.0.0
     *
     * @param  string|null  $paymentIntentId  The PaymentIntent Stripe confirmed.
     *
     * @return void
     */
    public function confirm( ?string $paymentIntentId = null ): void
    {
        if ( null === $paymentIntentId || '' === $this->reference || ! hash_equals( $this->reference, $paymentIntentId ) ) {
            $this->failed( __( 'Your payment couldn\'t be confirmed. Try again.' ) );

            return;
        }

        $session = $this->session();

        if ( null === $session ) {
            $this->failed( __( 'We couldn\'t check your payment with Stripe. Try again.' ) );

            return;
        }

        if ( $session->isConfirmed() ) {
            $this->confirmed();

            return;
        }

        $this->failed( $session->requiresAction()
            ? __( 'Your bank needs you to confirm this payment. Try again.' )
            : __( 'Your payment couldn\'t be completed. Try again or use another payment method.' ) );
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
        $publishableKey = is_string( $this->config['publishable_key'] ?? null ) ? trim( $this->config['publishable_key'] ) : '';
        $clientSecret   = is_string( $this->config['client_secret'] ?? null ) ? trim( $this->config['client_secret'] ) : '';
        $options        = is_array( $this->config['options'] ?? null ) ? $this->config['options'] : [];

        return view( 'ecommerce-storefront::livewire.payment.stripe-payment-element', [
            'available' => '' !== $publishableKey && '' !== $clientSecret,
            'stripe'    => [
                'src'            => self::STRIPE_JS,
                'publishableKey' => $publishableKey,
                'clientSecret'   => $clientSecret,
                'locale'         => is_string( $options['locale'] ?? null ) && '' !== $options['locale'] ? $options['locale'] : 'auto',
                'appearance'     => is_array( $options['appearance'] ?? null ) ? $options['appearance'] : [],
                'returnUrl'      => $this->returnUrl() ?? $this->pageUrl,
                'messages'       => [
                    'loadFailed' => __( 'The payment form couldn\'t load. Check your connection and reload the page.' ),
                    'failed'     => __( 'Your payment couldn\'t be completed. Try again or use another payment method.' ),
                ],
            ],
        ] );
    }
}
