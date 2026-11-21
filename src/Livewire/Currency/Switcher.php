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
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * `<livewire:artisanpack-ecommerce-storefront-currency-switcher />`
 *
 * Lets the shopper pick a currency (spec §7.3, S16). It renders only when
 * the store sells in more than one (the engine's `StoreCurrencies`), as a
 * design-system DropdownMenu: the trigger shows the current code, and each
 * radio item names the currency in the shopper's language with its code
 * ("Euro", EUR).
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
 * The package layout shows it in the header (from 768px) and in the
 * footer; a host layout can place it anywhere. Give each copy on a page its
 * own `placement`, which keeps their ids apart:
 * `<livewire:artisanpack-ecommerce-storefront-currency-switcher placement="footer" />`.
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
    public const COOKIE_MINUTES = StorefrontCart::CURRENCY_COOKIE_MINUTES;

    /**
     * The chosen currency.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $currency = '';

    /**
     * Where the switcher sits (`header`, `footer`, …): the header's menu is
     * `ecommerce-currency-switcher`, any other's
     * `ecommerce-currency-switcher-{placement}` (see {@see self::menuId()}).
     *
     * @since 2.0.0
     *
     * @var string
     */
    #[Locked]
    public string $placement = 'header';

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
     * @since 2.0.0 Passes the menu's radio `items` instead of select `options`, and its `menuId`.
     *
     * @return View
     */
    public function render(): View
    {
        $enabled = app( StoreCurrencies::class )->enabled();

        return view( 'ecommerce-storefront::livewire.currency.switcher', [
            'items' => array_map( fn ( string $code ): array => [
                'type'     => 'radio',
                'value'    => $code,
                'label'    => CurrencyNames::name( $code ),
                'shortcut' => $code,
                'checked'  => $code === $this->currency,
            ], $enabled ),
            'show'   => count( $enabled ) > 1,
            'menuId' => $this->menuId(),
        ] );
    }

    /**
     * The menu's id: `ecommerce-currency-switcher` in the header, else
     * `ecommerce-currency-switcher-{placement}`. A placement with characters
     * an id can't hold is stripped to the safe ones and given a short hash
     * of the original, so two placements never share an id (`footer.left`
     * and `footerleft` differ).
     *
     * @since 2.0.0
     *
     * @return string
     */
    protected function menuId(): string
    {
        if ( 'header' === $this->placement ) {
            return 'ecommerce-currency-switcher';
        }

        $safe = (string) preg_replace( '/[^a-z0-9_-]/i', '', $this->placement );

        if ( '' === $safe || $safe !== $this->placement ) {
            $safe = ( '' === $safe ? 'other' : $safe ) . '-' . substr( hash( 'sha256', $this->placement ), 0, 8 );
        }

        return 'ecommerce-currency-switcher-' . $safe;
    }

    /**
     * Stores the choice through {@see StorefrontCart::rememberCurrency()}.
     *
     * @since 1.0.0
     *
     * @param  string  $currency  The chosen currency.
     *
     * @return bool Whether the resolver now resolves to `$currency`.
     */
    protected function remember( string $currency ): bool
    {
        return app( StorefrontCart::class )->rememberCurrency( $currency );
    }
}
