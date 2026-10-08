<?php

/**
 * Currency switcher component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Currency;

use ArtisanPackUI\Ecommerce\Contracts\CurrencyResolver;
use ArtisanPackUI\Ecommerce\Exceptions\CartOperationException;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Services\StoreCurrencies;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CurrencyNames;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

/**
 * `<livewire:artisanpack-ecommerce-storefront-currency-switcher />`
 *
 * Lets the shopper pick a currency (spec §7.3, S16). It renders only when
 * the store sells in more than one (the engine's `StoreCurrencies`); each
 * option names the currency, its code, and its symbol ("Euro (EUR, €)").
 *
 * A choice is stored through the engine's `CurrencyResolver` (the default
 * keeps it in the session) and in the engine's `currency.cookie` cookie,
 * so catalog, product, and search prices use it. When the resolver won't
 * keep it (it still resolves another currency), nothing changes and the
 * shopper is told. An existing cart is
 * re-priced through `StorefrontCartService::changeCurrency()` (counting
 * against `ecommerce.cart.mutate`); if it can't be, the choice is undone
 * and the shopper told why. The page then reloads in the new currency with
 * a toast saying the cart total changed.
 *
 * The package layout shows it in the header; a host layout can place it
 * anywhere.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Switcher extends Component
{
    use InteractsWithStorefrontCart;
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * How long the currency cookie lasts, in minutes (a year).
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const COOKIE_MINUTES = 525_600;

    /**
     * The chosen currency.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $currency = '';

    /**
     * Starts on the shopper's current currency.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->currency = app( StorefrontCart::class )->currency();
    }

    /**
     * Switches to the chosen currency.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedCurrency(): void
    {
        $this->resetErrorBag( 'currency' );

        $chosen   = strtoupper( trim( $this->currency ) );
        $previous = app( StorefrontCart::class )->currency();

        if ( ! app( StoreCurrencies::class )->isEnabled( $chosen ) ) {
            $this->currency = $previous;
            $this->addError( 'currency', __( 'Choose one of the listed currencies.' ) );

            return;
        }

        $this->currency = $chosen;
        $cart           = $this->cart();
        $repriced       = null !== $cart && $chosen !== strtoupper( (string) $cart->currency );

        if ( $chosen === $previous && ! $repriced ) {
            return;
        }

        // Store the choice first: a resolver that won't keep it (a geo-IP
        // resolver, say) would put the shopper straight back, so nothing is
        // changed and they are told.
        if ( ! $this->remember( $chosen ) ) {
            $this->remember( $previous );
            $this->currency = $previous;
            $this->toastError( __( 'Prices can\'t be shown in :currency here.', [ 'currency' => $chosen ] ) );

            return;
        }

        if ( $repriced ) {
            try {
                $this->rateLimited( 'ecommerce.cart.mutate', static fn (): Cart => app( StorefrontCartService::class )->changeCurrency( $cart, $chosen ) );
            } catch ( CartOperationException $exception ) {
                $this->remember( $previous );
                $this->currency = $previous;
                $this->toastError( __( 'Your cart can\'t be shown in :currency.', [ 'currency' => $chosen ] ), $exception->getMessage() );

                return;
            }

            if ( $this->wasThrottled() ) {
                $this->remember( $previous );
                $this->currency = $previous;

                return;
            }

            $this->cartChanged( $cart );
        }

        $this->flashToastSuccess(
            __( 'Prices are now shown in :currency.', [ 'currency' => $chosen ] ),
            $repriced ? __( 'Your cart was re-priced in :currency, so its total has changed.', [ 'currency' => $chosen ] ) : null,
        );

        $this->js( 'window.location.reload()' );
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
        $enabled = app( StoreCurrencies::class )->enabled();

        return view( 'ecommerce-storefront::livewire.currency.switcher', [
            'options' => array_map( static fn ( string $code ): array => [ 'id' => $code, 'name' => CurrencyNames::label( $code ) ], $enabled ),
            'show'    => count( $enabled ) > 1,
        ] );
    }

    /**
     * Stores the choice: through the resolver (the default resolver keeps
     * it in the session) and in the engine's currency cookie.
     *
     * The engine's `CurrencyResolver` contract only promises `resolve()`,
     * so the choice counts as kept only when the resolver now answers with
     * it.
     *
     * @since 1.0.0
     *
     * @param  string  $currency  The chosen currency.
     *
     * @return bool Whether the resolver now resolves to `$currency`.
     */
    protected function remember( string $currency ): bool
    {
        $resolver = app( CurrencyResolver::class );

        if ( method_exists( $resolver, 'remember' ) ) {
            $resolver->remember( request(), $currency );
        }

        Cookie::queue( (string) config( 'artisanpack.ecommerce.currency.cookie', 'ecommerce_currency' ), $currency, self::COOKIE_MINUTES );

        return strtoupper( $resolver->resolve( request() ) ) === $currency;
    }
}
