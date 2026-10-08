<?php

/**
 * Payment driver component base.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Payment;

use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The base for the components that render a gateway's payment UI in the
 * checkout's payment step (spec §8.3). Register one with
 * `PaymentDriverRegistry::register( $driver, $component )`.
 *
 * **Props.** The checkout mounts the driver with the gateway's client
 * config and the payment session it was made for:
 *
 * - `config` — the engine's client-render config for the session
 *   (`driver`, `flow`, `publishable_key`, `client_secret`, `redirect_url`,
 *   `options`, `gateway`). It only holds values the provider means to be
 *   public;
 * - `gateway` / `gatewayLabel` — the gateway's key and name;
 * - `reference` — the payment session reference on the cart.
 *
 * All are locked: the browser can't change them.
 *
 * **Contract.** {@see self::confirm()} starts the payment confirmation:
 * redirect to the provider, or (for embedded UIs) check the outcome the
 * provider's client SDK reported. The driver then reports the outcome to
 * the checkout with a Livewire event:
 *
 * - `payment-confirmed` with `reference` — the provider confirmed the
 *   payment; the checkout moves on to the next step;
 * - `payment-failed` with `message` — it didn't; the shopper stays on the
 *   payment step. Show the message in the driver (inline, in a live
 *   region): the checkout doesn't repeat it.
 *
 * Use {@see self::confirmed()} and {@see self::failed()} rather than
 * dispatching the events yourself, and {@see self::confirmedByProvider()}
 * to check a session with the gateway before reporting it confirmed. The
 * checkout checks the reference against the cart again, and the engine's
 * `finalize()` checks the session with the provider before capturing it.
 *
 * **PCI.** Card data never enters the component's state or a Livewire
 * request: client code sends it straight to the provider (parent plan
 * §8.5), and the driver only ever receives the provider's own reference.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
abstract class PaymentDriver extends Component
{
    /**
     * The gateway's client config for the session.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $config = [];

    /**
     * The gateway key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $gateway = '';

    /**
     * The gateway's name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $gatewayLabel = '';

    /**
     * The payment session reference.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $reference = '';

    /**
     * Starts (or completes) the payment confirmation and reports the
     * outcome with {@see self::confirmed()} or {@see self::failed()}.
     *
     * @since 1.0.0
     *
     * @return void
     */
    abstract public function confirm(): void;

    /**
     * Reports a failure from the provider's client SDK (a declined card).
     *
     * @since 1.0.0
     *
     * @param  string  $message  The provider's message.
     *
     * @return void
     */
    public function fail( string $message = '' ): void
    {
        $this->failed( '' === trim( $message ) ? __( 'Your payment couldn\'t be completed. Try again or use another payment method.' ) : $message );
    }

    /**
     * Tells the checkout the payment is confirmed.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function confirmed(): void
    {
        $this->dispatch( 'payment-confirmed', reference: $this->reference, gateway: $this->gateway );
    }

    /**
     * Tells the checkout the payment failed.
     *
     * @since 1.0.0
     *
     * @param  string  $message  What the shopper is told (plain text).
     *
     * @return void
     */
    protected function failed( string $message ): void
    {
        $this->dispatch( 'payment-failed', message: Str::limit( trim( strip_tags( $message ) ), 300 ), gateway: $this->gateway );
    }

    /**
     * The session as the gateway sees it now, or null when it can't be
     * loaded.
     *
     * @since 1.0.0
     *
     * @return PaymentSession|null
     */
    protected function session(): ?PaymentSession
    {
        $gateway = '' === $this->gateway ? null : app( PaymentGatewayRegistry::class )->find( $this->gateway );

        if ( null === $gateway || '' === $this->reference ) {
            return null;
        }

        try {
            return $gateway->retrievePaymentSession( $this->reference );
        } catch ( Throwable $exception ) {
            report( $exception );

            return null;
        }
    }

    /**
     * Whether the gateway says the session is confirmed (authorized,
     * succeeded, or processing).
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function confirmedByProvider(): bool
    {
        return true === $this->session()?->isConfirmed();
    }

    /**
     * Where the provider sends the shopper back after a redirect or a
     * 3-D Secure step, or null when the storefront routes are off.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    protected function returnUrl(): ?string
    {
        return Route::has( 'artisanpack.ecommerce.storefront.checkout.return' ) ? route( 'artisanpack.ecommerce.storefront.checkout.return' ) : null;
    }
}
