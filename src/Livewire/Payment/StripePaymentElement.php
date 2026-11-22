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

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutPlacement;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Livewire;

/**
 * `stripe-payment-element` — on-site card (and wallet) entry with Stripe's
 * Payment Element (spec §8.3, parent plan §8.1, §8.5).
 *
 * The view's script loads Stripe.js from `https://js.stripe.com/v3` once
 * per page, mounts the Payment Element with the session's client secret,
 * the locale, and an appearance built from the design-system tokens
 * (`--apui-color-primary`, `--apui-surface-default`, `--apui-text-*`,
 * `--apui-color-error-text`, `--apui-radius-field`, and the field border
 * and focus ring) with the gateway's `appearance` config on top, so its
 * fields match the Input; it is rebuilt when the page switches between
 * light and dark. While Stripe loads, field skeletons sit under the glass
 * Loading overlay (#112). "Pay now" calls `stripe.confirmPayment()` with
 * `redirect: 'if_required'`, so a 3-D Secure challenge runs in the page;
 * payment methods that must redirect come back through
 * `/shop/checkout/return`. A Stripe error is shown under the form in an
 * assertive live region and reported as `payment-failed`. On success the
 * script calls {@see self::confirm()} with the PaymentIntent id, which is
 * checked with Stripe before the checkout is told — and the checkout then
 * places the order.
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
 * @since      2.0.0 Rebuilt on the design system: a Card, `--apui-*` appearance, skeleton loading.
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
     * Whether this session still pays for the order as it is now. The
     * script asks before `confirmPayment()`, because Stripe charges the
     * PaymentIntent's amount: a form set up before the cart changed (in
     * another tab, say) would charge the old total.
     *
     * True for the session of an order already placed
     * ({@see CheckoutPlacement}), whose total is fixed, and for the cart's
     * session when Stripe has it for the cart's total and currency. When
     * the cart has replaced the session or its total moved on, it is false
     * and `ecommerce-cart-updated` is dispatched, so the checkout sets the
     * payment up again and this form is replaced. When Stripe can't be
     * reached the answer is true: the checkout checks the amount again
     * before it places the order.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    #[Renderless]
    public function stillPayable(): bool
    {
        $placement = CheckoutPlacement::current();
        $placed    = null === $placement ? '' : (string) ( $placement['order']->payment_reference ?? '' );

        if ( '' !== $placed && '' !== $this->reference && hash_equals( $placed, $this->reference ) ) {
            return true;
        }

        $carts   = app( StorefrontCart::class );
        $cart    = $carts->current();
        $current = null === $cart ? '' : (string) ( $cart->payment_reference ?? '' );

        if ( null === $cart || '' === $current || '' === $this->reference || ! hash_equals( $current, $this->reference ) ) {
            $this->dispatch( 'ecommerce-cart-updated', count: $carts->count(), source: static::class );

            return false;
        }

        $session = $this->session();

        if ( null === $session ) {
            return ! $this->wasThrottled();
        }

        $payable = (int) $session->amount->getAmount() === (int) $cart->total_amount
            && strtoupper( $session->amount->getCurrency()->getCode() ) === strtoupper( (string) $cart->currency );

        if ( ! $payable ) {
            $this->dispatch( 'ecommerce-cart-updated', count: $carts->count(), source: static::class );
        }

        return $payable;
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
                    'placing'    => __( 'Placing your order…' ),
                    'changed'    => __( 'Your order changed, so we\'re updating the payment form. Check the new total, then pay.' ),
                ],
            ],
        ] );
    }
}
