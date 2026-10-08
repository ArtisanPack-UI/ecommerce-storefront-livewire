<?php

/**
 * Account guest-order claim component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account;

use ArtisanPackUI\Ecommerce\Exceptions\ClaimRateLimitedException;
use ArtisanPackUI\Ecommerce\Exceptions\ClaimVerificationFailedException;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\RateLimiting\RateLimitSubject;
use ArtisanPackUI\Ecommerce\Services\CustomerClaimService;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-account-claim />`
 *
 * Attaches orders the shopper placed as a guest to their account (spec
 * §7.5, S30). They give one order's number and shipping postcode; the
 * engine's `CustomerClaimService` checks them against an unclaimed guest
 * order under the account's email and, when they match, claims every guest
 * order under that email. The shopper then lands on their order history.
 *
 * Attempts count against `ecommerce.claim.attempt` (per user and per IP)
 * and the engine's own failed-attempt cap. A wrong number, a wrong
 * postcode, and an order that doesn't exist all get the same message, so
 * the form can't be used to find out which orders exist.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Claim extends Component
{
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * The order number.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $orderNumber = '';

    /**
     * The order's shipping postcode.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $postalCode = '';

    /**
     * Why the last claim failed, shown above the form.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    public ?string $failure = null;

    /**
     * Claims the shopper's guest orders.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function claim(): void
    {
        $this->resetErrorBag();
        $this->failure = null;

        $this->orderNumber = trim( $this->orderNumber );
        $this->postalCode  = trim( $this->postalCode );

        $validator = Validator::make(
            [ 'orderNumber' => $this->orderNumber, 'postalCode' => $this->postalCode ],
            [
                'orderNumber' => [ 'required', 'string', 'max:50' ],
                'postalCode'  => [ 'required', 'string', 'max:20' ],
            ],
            [
                'orderNumber.required' => __( 'Enter the order number.' ),
                'orderNumber.max'      => __( 'Keep this under :max characters.' ),
                'postalCode.required'  => __( 'Enter the postcode the order was shipped to.' ),
                'postalCode.max'       => __( 'Keep this under :max characters.' ),
            ],
        );

        if ( $validator->fails() ) {
            foreach ( $validator->errors()->messages() as $field => $messages ) {
                $this->addError( $field, (string) $messages[0] );
            }

            return;
        }

        $user     = auth()->user();
        $ip       = request()->ip() ?? '127.0.0.1';
        $customer = null;

        try {
            $customer = null === $user ? null : app( CustomerService::class )->customerForUser( $user, true );
        } catch ( Throwable $exception ) {
            report( $exception );
        }

        if ( null === $user || null === $customer ) {
            $this->failure = __( 'Your account can\'t claim orders yet. Verify your email address and try again.' );

            return;
        }

        $outcome = $this->rateLimited(
            'ecommerce.claim.attempt',
            fn (): Collection|string => $this->attempt( $customer, $ip ),
            RateLimitSubject::customer( $user, $ip ),
        );

        if ( $this->wasThrottled() ) {
            $this->failure = trans_choice(
                'Too many attempts. Try again in :seconds second.|Too many attempts. Try again in :seconds seconds.',
                (int) $this->throttledFor,
                [ 'seconds' => (int) $this->throttledFor ],
            );

            return;
        }

        if ( is_string( $outcome ) ) {
            $this->failure = $outcome;

            return;
        }

        $count = $outcome instanceof Collection ? $outcome->count() : 0;

        $this->flashToastSuccess( trans_choice( ':count order added to your account|:count orders added to your account', $count, [ 'count' => $count ] ) );

        $this->redirect(
            Route::has( 'artisanpack.ecommerce.account.orders.index' ) ? route( 'artisanpack.ecommerce.account.orders.index' ) : url( '/' ),
        );
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
        return view( 'ecommerce-storefront::livewire.account.claim' );
    }

    /**
     * Asks the engine to claim the orders.
     *
     * @since 1.0.0
     *
     * @param  Customer  $customer  The shopper's customer record.
     * @param  string    $ip        The shopper's address (audit only).
     *
     * @return Collection<int, Order>|string The claimed orders, or why nothing was claimed.
     */
    protected function attempt( Customer $customer, string $ip ): Collection|string
    {
        try {
            return app( CustomerClaimService::class )->claim( $customer, $this->orderNumber, $this->postalCode, $ip );
        } catch ( ClaimVerificationFailedException ) {
            return __( 'We couldn\'t find a guest order with that order number and postcode under your email address.' );
        } catch ( ClaimRateLimitedException ) {
            return __( 'Too many attempts. Try again later.' );
        } catch ( Throwable $exception ) {
            report( $exception );

            return __( 'We couldn\'t claim your orders right now. Try again in a moment.' );
        }
    }
}
