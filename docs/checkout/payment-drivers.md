---
title: Payment Drivers
---

# Payment Drivers

Payment gateways live in the engine. The storefront doesn't know any gateway: it asks the gateway how its payment step should be rendered, and hands that to a **payment driver**, a Livewire component that draws the payment UI and reports the outcome back to the checkout.

## How a gateway describes its payment step

A gateway whose payment step runs in the browser implements the engine's `Contracts\RendersClientPayment`:

```php
public function clientConfig( Cart $cart, PaymentSession $session ): array;
```

It returns:

```php
[
    'driver'          => 'stripe-payment-element', // which driver renders it
    'flow'            => 'embedded',               // or 'redirect'
    'publishable_key' => 'pk_…',                   // optional
    'client_secret'   => 'pi_…_secret_…',          // optional
    'redirect_url'    => null,                     // for redirect flows
    'options'         => [ … ],                    // driver-specific (locale, appearance, …)
]
```

The checkout builds it with the engine's `Support\ClientPaymentConfig::for( $gateway, $cart, $session )`, which also:

- describes a gateway without the contract as `redirect` when its session has a redirect URL, and as not renderable otherwise;
- runs the result through the `ap.ecommerce.payment.clientConfig` filter `(?array $config, PaymentGateway $gateway, Cart $cart, PaymentSession $session)`;
- adds `gateway` (the gateway key).

The array may only hold values the provider means to be public, such as a publishable key or a client secret scoped to one payment. Never put a secret key in it.

## The driver registry

`Registries\PaymentDriverRegistry` maps a `driver` to a Livewire component (name or class). The checkout looks up the gateway's `driver` there. A gateway whose driver isn't registered, or that isn't renderable, is hidden from the payment step with "This payment method isn't available".

```php
public function register( string $driver, string $component ): void // replaces an existing entry
public function has( string $driver ): bool
public function component( string $driver ): ?string
public function all(): array
```

### Core drivers

| Driver | Component | Used for |
| --- | --- | --- |
| `stripe-payment-element` | `artisanpack-ecommerce-storefront-payment-stripe` (`Livewire\Payment\StripePaymentElement`) | The engine's Stripe gateway. On-site card and wallet entry with Stripe's Payment Element. |
| `redirect` | `artisanpack-ecommerce-storefront-payment-redirect` (`Livewire\Payment\RedirectDriver`) | Any gateway that sends the shopper away to pay. Says "You'll go to {gateway} to pay, then come back here to finish your order.", shows "Continue to {gateway}", and redirects to the session's `redirect_url`. |

**Stripe Payment Element** loads Stripe.js from `https://js.stripe.com/v3` once per page and mounts the Payment Element with the session's client secret, the locale, and an appearance built from your daisyUI theme (`--color-primary`, `--color-base-100`, `--color-base-content`, `--color-error`, `--radius-field`), with the gateway's `appearance` config on top. "Pay now" calls `stripe.confirmPayment()` with `redirect: 'if_required'`, so 3-D Secure runs in the page, and methods that must redirect come back through the return route. A Stripe error shows under the form in an assertive live region. With a Content Security Policy, allow `script-src https://js.stripe.com`, `frame-src https://js.stripe.com https://hooks.stripe.com`, and `connect-src https://api.stripe.com`.

### Registering drivers in config

```php
// config/artisanpack/ecommerce-storefront-livewire.php
'payments' => [
    'drivers' => [
        'acme-pay' => 'acme-pay-driver',
    ],
],
```

Core drivers are always registered. A config entry with the same key replaces one. A driver a satellite registered before the storefront booted is left in place by the core registration, but config entries always win.

## Writing a payment driver

Extend `Livewire\Payment\PaymentDriver`.

### Props

The checkout mounts the driver with:

| Prop | Holds |
| --- | --- |
| `config` | The gateway's client config for the session (`driver`, `flow`, `publishable_key`, `client_secret`, `redirect_url`, `options`, `gateway`). |
| `gateway` | The gateway key. |
| `gatewayLabel` | The gateway's name. |
| `reference` | The payment session reference. |
| `token` | The one-time action token that places the order, minted when the payment step rendered. It goes back with `payment-confirmed`. Empty when the driver pays for an order that is already placed. |

All five are `#[Locked]`: the browser can't change them. The driver is keyed by the reference, so a new session (a changed total) mounts a fresh driver.

### Methods

| Method | Use |
| --- | --- |
| `confirm(): void` | **Abstract.** Start or finish the payment confirmation: redirect to the provider, or check the outcome the provider's client SDK reported. Then call `confirmed()` or `failed()`. You may add optional parameters (the Stripe driver takes the PaymentIntent id). |
| `confirmed()` | Tells the checkout the payment is confirmed: dispatches `payment-confirmed` with `reference`, `gateway`, and `token`. The checkout then places the order. |
| `failed( string $message )` | Tells the checkout it failed: dispatches `payment-failed` with `message` (tags stripped, up to 300 characters) and `gateway`. The shopper stays on the payment step. |
| `fail( string $message = '' )` | Public, for your client script to call when the provider's SDK reports an error. Calls `failed()`, with a generic message when none is given. |
| `confirmedByProvider(): bool` | Asks the gateway whether the session is confirmed (authorized, succeeded, or processing). Use it before `confirmed()`. |
| `session(): ?PaymentSession` | The session as the gateway sees it now, or null when it can't be loaded. Each call counts against the shopper's `ecommerce.cart.mutate` limit, so a looping script can't spend the store's provider API quota. Over the limit, it returns null and the shopper gets a "slow down" toast. |
| `returnUrl(): ?string` | `/shop/checkout/return`, for providers that redirect or run 3-D Secure off-page. Null when the storefront routes are off. |

Use `confirmed()` and `failed()` rather than dispatching the events yourself. The checkout doesn't repeat a failure message, so show it in your driver, in a live region.

Payment is the checkout's last step, so `payment-confirmed` places the order. The checkout doesn't trust the event on its own: it checks the reference against the cart's session and asks the gateway whether the session is confirmed for the cart's current total and currency, then places the order under the action token. The token makes a repeated confirmation place one order. The engine's `finalize()` checks the session with the provider again before capturing.

### PCI

Card data must never enter the component's public properties or a Livewire request. Your client script sends it straight to the provider (in the provider's iframe or SDK), and the driver only ever handles the provider's own reference. Put the provider's form inside `wire:ignore` so Livewire never re-renders it.

### Example

An embedded "Acme Pay" card form. Acme Pay is made up: swap in your provider's client SDK calls. The gateway (in your gateway satellite) names the driver in its client config:

```php
use ArtisanPackUI\Ecommerce\Contracts\PaymentGateway;
use ArtisanPackUI\Ecommerce\Contracts\RendersClientPayment;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;

class AcmePayGateway implements PaymentGateway, RendersClientPayment
{
    // … key(), label(), createPaymentSession(), retrievePaymentSession(), capturePayment(), …

    public function clientConfig( Cart $cart, PaymentSession $session ): array
    {
        return [
            'driver'          => 'acme-pay',
            'flow'            => 'embedded',
            'publishable_key' => (string) config( 'services.acme_pay.public_key' ),
            'client_secret'   => $session->clientSecret,
            'redirect_url'    => null,
            'options'         => [ 'locale' => app()->getLocale() ],
        ];
    }
}
```

The driver:

```php
<?php

declare( strict_types=1 );

namespace AcmePay\Storefront;

use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Payment\PaymentDriver;
use Illuminate\Contracts\View\View;

class AcmePayDriver extends PaymentDriver
{
    /**
     * Called by the view's script once Acme Pay's SDK reports success.
     */
    public function confirm(): void
    {
        $session = $this->session();

        if ( null === $session ) {
            $this->failed( __( 'We couldn\'t check your payment. Try again.' ) );

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

    public function render(): View
    {
        return view( 'acme-pay::storefront.driver', [
            'settings' => [
                'publicKey'    => (string) ( $this->config['publishable_key'] ?? '' ),
                'clientSecret' => (string) ( $this->config['client_secret'] ?? '' ),
                'locale'       => (string) ( $this->config['options']['locale'] ?? 'auto' ),
                'returnUrl'    => $this->returnUrl(),
            ],
        ] );
    }
}
```

Its view keeps the provider's form out of Livewire's hands and calls back with `$wire`:

```blade
<div class="flex flex-col gap-3">
    <div wire:ignore>
        <div data-acme-card></div>
    </div>

    <p class="text-sm text-error empty:hidden" role="alert" aria-live="assertive" data-acme-error></p>

    <div>
        <x-artisanpack-button :label="__( 'Pay now' )" color="primary" data-acme-pay />
    </div>
</div>

@script
<script>
    const root     = $wire.$el;
    const settings = @js( $settings );
    const card     = AcmePay( settings.publicKey, { locale: settings.locale } ).mountCard( root.querySelector( '[data-acme-card]' ) );

    root.querySelector( '[data-acme-pay]' ).addEventListener( 'click', async () => {
        // Card data goes from Acme's iframe straight to Acme, never to Livewire.
        const result = await card.confirm( settings.clientSecret, { returnUrl: settings.returnUrl } );

        if ( result.error ) {
            root.querySelector( '[data-acme-error]' ).textContent = result.error.message;
            $wire.fail( result.error.message );

            return;
        }

        $wire.confirm();
    } );
</script>
@endscript
```

Register the component and the driver from your service provider's `boot()`. Guard on the class so the gateway still works without the Livewire storefront:

```php
use AcmePay\Storefront\AcmePayDriver;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\PaymentDriverRegistry;
use Livewire\Livewire;

if ( class_exists( PaymentDriverRegistry::class ) ) {
    Livewire::component( 'acme-pay-driver', AcmePayDriver::class );

    app( PaymentDriverRegistry::class )->register( 'acme-pay', 'acme-pay-driver' );
}
```

A redirect-only gateway needs no driver: leave out `RendersClientPayment`, give its session a `redirectUrl`, and the core `redirect` driver handles it.

### Real examples

- `tests/Fixtures/Livewire/FakePaymentDriver.php`: the smallest driver. `confirm()` reports confirmed when `confirmedByProvider()` agrees, declined otherwise.
- `tests/Browser/Support/TestCardDriver.php`: the browser suite's stand-in for an embedded form, with "Pay with test card" and "Use a declined card" buttons.
- `src/Livewire/Payment/StripePaymentElement.php` and `src/Livewire/Payment/RedirectDriver.php`: the core drivers.

## Testing with Stripe test mode

The package's own browser suite never talks to Stripe: `tests/BrowserTestCase.php` registers a fake `test-card` gateway whose UI is `TestCardDriver`. To try the real Stripe flow by hand:

1. **Install the SDK.** The engine's Stripe gateway needs `stripe/stripe-php`:

   ```bash
   composer require stripe/stripe-php
   ```

2. **Add your test keys** from the Stripe dashboard (test mode) to `.env`:

   ```dotenv
   ECOMMERCE_STRIPE_ENABLED=true
   ECOMMERCE_STRIPE_SECRET_KEY=sk_test_…
   ECOMMERCE_STRIPE_PUBLISHABLE_KEY=pk_test_…
   ECOMMERCE_STRIPE_WEBHOOK_SECRET=whsec_…
   ```

   These are the engine's `gateways.stripe` config. The gateway registers as `stripe` only when it is enabled and the SDK is installed. Store owners can also switch it on with **Enable Stripe** on the admin's Payments settings; the keys always come from the environment. `ECOMMERCE_STRIPE_CAPTURE_METHOD` is `automatic` (default) or `manual`.

3. **Forward webhooks.** The engine receives provider webhooks at `POST /ecommerce/webhooks/{provider}` (route `ecommerce.webhooks`), so Stripe's is `/ecommerce/webhooks/stripe`. With the Stripe CLI:

   ```bash
   stripe listen --forward-to https://your-store.test/ecommerce/webhooks/stripe
   ```

   Put the `whsec_…` secret it prints in `ECOMMERCE_STRIPE_WEBHOOK_SECRET`. A verified webhook with a payment outcome queues the engine's payment reconciliation, so run a queue worker. The checkout doesn't wait for webhooks, but they matter when the shopper closes the tab after paying: the reconciler places the order from the cart. When the webhook places the order before the checkout does, the checkout picks up that order and still sends the shopper to the confirmation (see [Checkout](Checkout#when-the-providers-webhook-places-the-order-first)).

4. **Check out** at `/shop/checkout` and pay with a test card. Use any future expiry date and any CVC.

   | Card | Result |
   | --- | --- |
   | `4242 4242 4242 4242` | Succeeds. |
   | `4000 0025 0000 3155` | Asks for 3-D Secure. Completing the challenge confirms the payment and places the order; failing it keeps the shopper on the payment step. |
   | `4000 0000 0000 0002` | Declined ("Your card was declined"). |
   | `4000 0000 0000 9995` | Declined for insufficient funds. |

Payment methods that redirect (and 3-D Secure, when Stripe can't run it in the page) come back to `/shop/checkout/return` (`artisanpack.ecommerce.storefront.checkout.return`). A confirmed payment goes back to the checkout's payment step, which places the order. The storefront passes that URL to the engine as the session's `return_url`, which must be on your site. Without it, the engine turns off Stripe's redirect-based payment methods.
