<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\WithActionToken;
use Livewire\Component;
use RuntimeException;

/**
 * A screen with a money-moving action and a rate-limited action, for
 * testing the storefront's Livewire safety concerns.
 */
class SafetyScreen extends Component
{
    use RateLimitsStorefront;
    use SendsToasts;
    use WithActionToken;

    /**
     * How many times each action body has run.
     */
    public static int $runs = 0;

    /**
     * Fail the next action body.
     */
    public static bool $fail = false;

    /**
     * The token minted at render.
     */
    public string $token = '';

    /**
     * The last result.
     */
    public mixed $result = null;

    /**
     * Whether the last rate-limited call was throttled.
     */
    public bool $throttled = false;

    public function mount(): void
    {
        $this->token = $this->actionToken( 'place-order', 7 );
    }

    /**
     * The money-moving action.
     */
    public function place( string $token ): void
    {
        $this->result = $this->withActionToken( $token, 'place-order', static function (): string {
            if ( self::$fail ) {
                self::$fail = false;

                throw new RuntimeException( 'Payment declined.' );
            }

            return 'order-' . ++self::$runs;
        }, 7 );
    }

    /**
     * Mints a token for another subject.
     */
    public function tokenFor( int $subject ): string
    {
        return $this->actionToken( 'place-order', $subject );
    }

    /**
     * A rate-limited action.
     */
    public function applyCoupon(): void
    {
        $this->result    = $this->rateLimited( 'ecommerce.coupon.attempt', static fn (): int => ++self::$runs );
        $this->throttled = $this->wasThrottled();
    }

    public function render(): string
    {
        return '<div>{{ $token }}</div>';
    }
}
