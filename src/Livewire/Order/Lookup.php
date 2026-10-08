<?php

/**
 * Guest order lookup component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Order;

use ArtisanPackUI\Ecommerce\Exceptions\GuestLookupLockedException;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Services\GuestOrderLookupService;
use ArtisanPackUI\Ecommerce\Support\OrderViewToken;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-order-lookup />`
 *
 * Lets a guest find an order with the email it was placed with and its
 * number (spec §7.6, S31), through the engine's `GuestOrderLookupService`.
 * A match opens the order's signed page (`storefront.order-view`), which
 * shows it read-only and stops working after
 * `order_lookup.link_ttl_minutes`.
 *
 * Any failure — no such order, the wrong email, an anonymized order — gets
 * one message, so the form can't tell anyone which order numbers exist.
 * Every attempt counts against `ecommerce.lookup.attempt` (per IP), and
 * the engine locks an order number or address out after repeated failures;
 * a locked-out shopper is told how long to wait.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Lookup extends Component
{
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * The email the order was placed with.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $email = '';

    /**
     * The order number.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $orderNumber = '';

    /**
     * Why the last lookup failed, shown above the form.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    public ?string $failure = null;

    /**
     * Looks the order up and, when it matches, opens it.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function find(): void
    {
        $this->resetErrorBag();
        $this->failure = null;

        $this->email       = trim( $this->email );
        $this->orderNumber = trim( $this->orderNumber );

        $validator = Validator::make(
            [ 'email' => $this->email, 'orderNumber' => $this->orderNumber ],
            [
                'email'       => [ 'required', 'string', 'email', 'max:255' ],
                'orderNumber' => [ 'required', 'string', 'max:50' ],
            ],
            [
                'email.required'       => __( 'Enter the email address you used for the order.' ),
                'email.email'          => __( 'Enter a valid email address.' ),
                'email.max'            => __( 'Keep this under :max characters.' ),
                'orderNumber.required' => __( 'Enter the order number.' ),
                'orderNumber.max'      => __( 'Keep this under :max characters.' ),
            ],
        );

        if ( $validator->fails() ) {
            foreach ( $validator->errors()->messages() as $field => $messages ) {
                $this->addError( $field, (string) $messages[0] );
            }

            return;
        }

        $outcome = $this->rateLimited( 'ecommerce.lookup.attempt', fn (): Order|string|null => $this->attempt() );

        if ( $this->wasThrottled() ) {
            $this->failure = $this->lockedOut( (int) $this->throttledFor );

            return;
        }

        if ( ! $outcome instanceof Order ) {
            $this->failure = is_string( $outcome ) ? $outcome : __( 'We couldn\'t find an order with that email address and order number.' );

            return;
        }

        $ttl   = max( 1, (int) config( 'artisanpack.ecommerce-storefront-livewire.order_lookup.link_ttl_minutes', 60 ) );
        $token = OrderViewToken::for( $outcome, Carbon::now()->addMinutes( $ttl ) );

        $this->redirect( route( 'artisanpack.ecommerce.storefront.order-view', [ 'token' => $token ] ) );
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
        $loginRoute = (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.login_route', 'login' );

        return view( 'ecommerce-storefront::livewire.order.lookup', [
            'loginUrl' => null === auth()->user() && '' !== $loginRoute && Route::has( $loginRoute ) ? route( $loginRoute ) : null,
        ] );
    }

    /**
     * Asks the engine for the order.
     *
     * @since 1.0.0
     *
     * @return Order|string|null The order, a lockout message, or null when nothing matched.
     */
    protected function attempt(): Order|string|null
    {
        try {
            return app( GuestOrderLookupService::class )->find( $this->email, $this->orderNumber, request()->ip() );
        } catch ( GuestLookupLockedException $exception ) {
            return $this->lockedOut( $exception->retryAfter );
        } catch ( Throwable $exception ) {
            report( $exception );

            return __( 'We couldn\'t look up orders right now. Try again in a moment.' );
        }
    }

    /**
     * The lockout message, with the wait in minutes (or seconds under one
     * minute).
     *
     * @since 1.0.0
     *
     * @param  int  $seconds  Seconds until another attempt is allowed.
     *
     * @return string
     */
    protected function lockedOut( int $seconds ): string
    {
        $seconds = max( 1, $seconds );

        if ( $seconds < 60 ) {
            return trans_choice( 'Too many attempts. Try again in :seconds second.|Too many attempts. Try again in :seconds seconds.', $seconds, [ 'seconds' => $seconds ] );
        }

        $minutes = (int) ceil( $seconds / 60 );

        return trans_choice( 'Too many attempts. Try again in :minutes minute.|Too many attempts. Try again in :minutes minutes.', $minutes, [ 'minutes' => $minutes ] );
    }
}
