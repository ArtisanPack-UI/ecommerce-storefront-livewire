<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Fraud;

use ArtisanPackUI\Ecommerce\Contracts\FraudProvider;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\ValueObjects\Address;
use ArtisanPackUI\Ecommerce\ValueObjects\FraudDecision;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentSession;

/**
 * A fraud provider that gives the verdict it was built with.
 */
class FakeFraudProvider implements FraudProvider
{
    public function __construct( public string $verdict = 'block' )
    {
    }

    public function key(): string
    {
        return 'fake-fraud';
    }

    public function label(): string
    {
        return 'Fake fraud screening';
    }

    public function assess( Cart $cart, Address $shipping, PaymentSession $session ): FraudDecision
    {
        return match ( $this->verdict ) {
            'block'     => FraudDecision::block( 99, [ 'card_testing' ] ),
            'challenge' => FraudDecision::challenge( 60, [ 'velocity' ] ),
            default     => FraudDecision::approve(),
        };
    }
}
