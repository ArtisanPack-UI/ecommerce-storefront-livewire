<?php

declare( strict_types=1 );

namespace Tests\Browser\Support;

use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Payment\PaymentDriver;
use Tests\Fixtures\Gateways\FakeGateway;

/**
 * The payment UI the browser suite checks out with, standing in for a
 * provider's embedded form (Stripe's Payment Element in a real store).
 *
 * "Pay with test card" does what the provider's client SDK would: it
 * confirms the session with the gateway, then reports the outcome to the
 * checkout through the driver contract. "Use a declined card" reports a
 * decline.
 */
class TestCardDriver extends PaymentDriver
{
    /**
     * The driver key the test-card gateway's client config names.
     */
    public const DRIVER = 'test-card';

    public function pay(): void
    {
        $gateway = app( PaymentGatewayRegistry::class )->find( $this->gateway );

        if ( $gateway instanceof FakeGateway ) {
            $gateway->setStatus( $this->reference, PaymentSession::STATUS_AUTHORIZED );
        }

        $this->confirm();
    }

    public function decline(): void
    {
        $this->fail( 'Your card was declined.' );
    }

    public function confirm(): void
    {
        if ( $this->confirmedByProvider() ) {
            $this->confirmed();

            return;
        }

        $this->fail();
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div class="flex flex-wrap gap-2" data-test-card="{{ $reference }}">
                <x-artisanpack-button label="Pay with test card" color="primary" wire:click="pay" spinner="pay" data-test-card-pay />
                <x-artisanpack-button label="Use a declined card" wire:click="decline" data-test-card-decline />
            </div>
        BLADE;
    }
}
