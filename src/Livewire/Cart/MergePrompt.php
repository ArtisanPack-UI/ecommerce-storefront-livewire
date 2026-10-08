<?php

/**
 * Cart merge prompt component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart;

use ArtisanPackUI\Ecommerce\Exceptions\CartOperationException;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Services\CurrentCart;
use ArtisanPackUI\Ecommerce\ValueObjects\CartMergeResolution;
use ArtisanPackUI\Ecommerce\ValueObjects\PendingCartMerge;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * `<livewire:artisanpack-ecommerce-storefront-cart-merge-prompt />`
 *
 * Asks a shopper who just signed in what to do when the cart they built as a
 * guest and their account cart are in different currencies (parent plan
 * §7.1). The engine's login listener merges same-currency carts itself and
 * leaves a mismatch pending in the session; this modal offers the three
 * resolutions and applies the one chosen through `CurrentCart`.
 *
 * Mounted once by `ecommerce-storefront::partials.global`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class MergePrompt extends Component
{
    use InteractsWithStorefrontCart;
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * Whether the modal is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $open = false;

    /**
     * The guest cart's currency.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $guestCurrency = '';

    /**
     * The account cart's currency.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $accountCurrency = '';

    /**
     * Opens the modal when a merge is waiting on the shopper.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $pending = $this->pending();

        if ( null === $pending ) {
            return;
        }

        $this->open            = true;
        $this->guestCurrency   = $pending->guestCurrency;
        $this->accountCurrency = $pending->accountCurrency;
    }

    /**
     * Applies the shopper's choice to the pending merge.
     *
     * @since 1.0.0
     *
     * @param  string  $resolution  A {@see CartMergeResolution} value.
     *
     * @return void
     */
    public function resolve( string $resolution ): void
    {
        $choice = CartMergeResolution::tryFrom( $resolution );
        $user   = auth()->user();

        if ( null === $choice || null === $user ) {
            return;
        }

        if ( null === $this->pending() ) {
            $this->open = false;

            return;
        }

        try {
            $cart = $this->rateLimited(
                'ecommerce.cart.mutate',
                static fn (): ?Cart => app( CurrentCart::class )->resolvePendingMerge( session()->driver(), $user, $choice ),
            );
        } catch ( CartOperationException $exception ) {
            $this->open = false;
            $this->toastError( __( 'Your carts could not be combined.' ), $exception->getMessage() );

            return;
        }

        if ( $this->wasThrottled() ) {
            return;
        }

        $this->open = false;
        $this->cartChanged( $cart );

        $this->toastSuccess( match ( $choice ) {
            CartMergeResolution::KeepGuestCurrency       => __( 'Your carts are combined in :currency.', [ 'currency' => $this->guestCurrency ] ),
            CartMergeResolution::SwitchToAccountCurrency => __( 'Your carts are combined in :currency.', [ 'currency' => $this->accountCurrency ] ),
            CartMergeResolution::CancelMerge             => __( 'Your saved cart is unchanged.' ),
        } );
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
        return view( 'ecommerce-storefront::livewire.cart.merge-prompt', [
            'choices' => [
                CartMergeResolution::KeepGuestCurrency->value       => __( 'Keep :currency', [ 'currency' => $this->guestCurrency ] ),
                CartMergeResolution::SwitchToAccountCurrency->value => __( 'Switch to :currency', [ 'currency' => $this->accountCurrency ] ),
                CartMergeResolution::CancelMerge->value             => __( "Don't merge" ),
            ],
        ] );
    }

    /**
     * The merge waiting on the signed-in shopper, if any.
     *
     * @since 1.0.0
     *
     * @return PendingCartMerge|null
     */
    protected function pending(): ?PendingCartMerge
    {
        if ( null === auth()->user() || ! app()->bound( 'session' ) ) {
            return null;
        }

        return app( CurrentCart::class )->pendingMerge( session()->driver() );
    }
}
