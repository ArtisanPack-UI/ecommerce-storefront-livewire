<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Gateways;

use ArtisanPackUI\Ecommerce\Contracts\RendersClientPayment;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;

/**
 * A {@see FakeGateway} with an embedded payment UI rendered by `$driver`.
 */
class FakeClientGateway extends FakeGateway implements RendersClientPayment
{
    public function __construct(
        string $keyName = 'fake',
        string $labelText = 'Fake card',
        public string $driver = 'fake-driver',
        public string $publishableKey = 'pk_test_fake',
    ) {
        parent::__construct( $keyName, $labelText );
    }

    public function clientConfig( Cart $cart, PaymentSession $session ): array
    {
        return [
            'driver'          => $this->driver,
            'flow'            => 'embedded',
            'publishable_key' => $this->publishableKey,
            'client_secret'   => $session->clientSecret,
            'redirect_url'    => null,
            'options'         => [ 'locale' => 'en', 'appearance' => [ 'theme' => 'night' ] ],
        ];
    }
}
