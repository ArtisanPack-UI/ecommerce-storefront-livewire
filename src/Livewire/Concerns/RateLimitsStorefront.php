<?php

/**
 * Storefront rate-limit concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Exceptions\RateLimitExceededException;
use ArtisanPackUI\Ecommerce\RateLimiting\EcommerceRateLimiter;
use ArtisanPackUI\Ecommerce\RateLimiting\RateLimitSubject;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;

/**
 * Runs storefront actions through the engine's rate-limit policies (spec §5.2).
 *
 * Every Livewire call is `POST /livewire/update`, so limits keyed on the
 * route or the IP are too coarse. Actions here count against the shopper's
 * cart (or the signed-in customer, or the IP when there is neither) in the
 * same buckets the REST API uses, so switching surfaces doesn't reset them:
 *
 * ```php
 * $cart = $this->rateLimited(
 *     self::COUPON_POLICY,
 *     fn () => $carts->applyCoupon( $cart, $code ),
 * );
 * ```
 *
 * Over the limit, the action doesn't run and the shopper gets a toast with
 * the retry time. Uses {@see SendsToasts}.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
trait RateLimitsStorefront
{
    /**
     * Seconds until the last throttled action may be retried, or null when
     * the last action ran.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    protected ?int $throttledFor = null;

    /**
     * Runs `$callback` within `$policy`, or tells the shopper to wait.
     *
     * Policies: `ecommerce.cart.mutate`, `ecommerce.coupon.attempt`,
     * `ecommerce.review.submit`, `ecommerce.lookup.attempt`,
     * `ecommerce.claim.attempt`, `ecommerce.checkout.finalize`.
     *
     * @since 1.0.0
     *
     * @template TResult
     *
     * @param  string                 $policy    The engine policy name.
     * @param  callable(): TResult    $callback  The action.
     * @param  RateLimitSubject|null  $subject   Who it counts against; defaults to the shopper's cart.
     *
     * @return TResult|null Null when throttled; check {@see self::wasThrottled()} when the action may return null.
     */
    protected function rateLimited( string $policy, callable $callback, ?RateLimitSubject $subject = null ): mixed
    {
        $this->throttledFor = null;

        try {
            return app( EcommerceRateLimiter::class )->attempt(
                $policy,
                $subject ?? app( StorefrontCart::class )->subject(),
                $callback,
            );
        } catch ( RateLimitExceededException $exception ) {
            $this->throttledFor = max( 1, $exception->retryAfter );

            $this->toastWarning(
                __( 'Slow down' ),
                trans_choice(
                    'Too many attempts. Try again in :seconds second.|Too many attempts. Try again in :seconds seconds.',
                    $this->throttledFor,
                    [ 'seconds' => $this->throttledFor ],
                ),
            );

            return null;
        }
    }

    /**
     * Whether the last {@see self::rateLimited()} call was throttled.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function wasThrottled(): bool
    {
        return null !== $this->throttledFor;
    }
}
