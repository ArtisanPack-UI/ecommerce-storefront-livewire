<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Payment\PaymentDriver;

/**
 * A payment driver that confirms when the gateway says the session is
 * confirmed, as a satellite's driver would.
 */
class FakePaymentDriver extends PaymentDriver
{
    public function confirm(): void
    {
        if ( $this->confirmedByProvider() ) {
            $this->confirmed();

            return;
        }

        $this->failed( 'Your card was declined.' );
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div data-fake-driver="{{ $reference }}">
                <span data-fake-driver-gateway>{{ $gatewayLabel }}</span>
                <span data-fake-driver-secret>{{ $config['client_secret'] ?? '' }}</span>
            </div>
        BLADE;
    }
}
