<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Gateways;

use ArtisanPackUI\Ecommerce\Contracts\PaymentGateway;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentResult;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;
use ArtisanPackUI\Ecommerce\ValueObjects\RefundResult;
use ArtisanPackUI\Ecommerce\ValueObjects\WebhookResult;
use Illuminate\Http\Request;
use Money\Currency;
use Money\Money;
use RuntimeException;

/**
 * An in-memory gateway. Sessions start `requires_payment_method`; tests
 * move them on with {@see self::setStatus()}. With `$redirectUrl`, each
 * session carries a redirect URL, so the engine renders it with the
 * `redirect` driver.
 */
class FakeGateway implements PaymentGateway
{
    /**
     * Sessions, by reference.
     *
     * @var array<string, PaymentSession>
     */
    public array $sessions = [];

    /**
     * The context of each `createPaymentSession()` call.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $contexts = [];

    /**
     * Throw when a session is loaded.
     */
    public bool $failRetrieval = false;

    /**
     * Throw when a session is created.
     */
    public bool $failCreation = false;

    private int $sequence = 0;

    public function __construct(
        public string $keyName = 'fake',
        public string $labelText = 'Fake card',
        public ?string $redirectUrl = null,
    ) {
    }

    public function key(): string
    {
        return $this->keyName;
    }

    public function label(): string
    {
        return $this->labelText;
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    public function supportsPartialRefunds(): bool
    {
        return false;
    }

    public function supportsSavedInstruments(): bool
    {
        return false;
    }

    public function createPaymentSession( Cart $cart, array $context = [] ): PaymentSession
    {
        if ( $this->failCreation ) {
            throw new RuntimeException( 'The provider is down.' );
        }

        $this->contexts[] = $context;
        $reference        = $this->keyName . '_ps_' . ( ++$this->sequence );

        return $this->sessions[ $reference ] = new PaymentSession(
            gatewayKey: $this->keyName,
            reference: $reference,
            amount: new Money( (int) $cart->total_amount, new Currency( (string) $cart->currency ) ),
            clientSecret: $reference . '_secret',
            redirectUrl: $this->redirectUrl,
            status: PaymentSession::STATUS_REQUIRES_PAYMENT_METHOD,
        );
    }

    /**
     * Moves a session to `$status`.
     */
    public function setStatus( string $reference, string $status ): void
    {
        $session = $this->sessions[ $reference ] ?? throw new RuntimeException( "No session {$reference}." );

        $this->sessions[ $reference ] = new PaymentSession(
            gatewayKey: $session->gatewayKey,
            reference: $session->reference,
            amount: $session->amount,
            clientSecret: $session->clientSecret,
            redirectUrl: $session->redirectUrl,
            status: $status,
        );
    }

    public function retrievePaymentSession( string $reference ): PaymentSession
    {
        if ( $this->failRetrieval ) {
            throw new RuntimeException( 'The provider is down.' );
        }

        return $this->sessions[ $reference ] ?? throw new RuntimeException( "No session {$reference}." );
    }

    public function capturePayment( Order $order, PaymentSession $session ): PaymentResult
    {
        return PaymentResult::success( $session->amount, 'ch_' . $session->reference );
    }

    public function voidPendingPayment( Order $order ): void
    {
    }

    public function refund( Order $order, Money $amount, ?string $reason = null, array $context = [] ): RefundResult
    {
        return RefundResult::success( $amount, 're_' . $order->id );
    }

    public function handleWebhook( Request $request ): WebhookResult
    {
        return WebhookResult::unverified( 'not_implemented' );
    }
}
