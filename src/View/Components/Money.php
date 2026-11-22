<?php

/**
 * Money display component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-money :amount="$order->total_amount" :currency="$order->currency" />`
 *
 * Formats an integer minor-unit amount through the engine's `MoneyFormatter`
 * in the app locale (or `locale`), so the admin and storefront agree.
 *
 * Design-system text styles only (`ec-money` in resources/css/storefront.css):
 * tabular figures that never wrap, so columns of money line up. A negative
 * amount (a discount) reads with a true minus sign, "−", and a visually
 * hidden "Minus". With `equivalent` (minor units in `equivalent-currency`,
 * usually the store currency), a converted amount shows that equivalent
 * after it, muted ("€25,90 ≈ $28.00"). A missing amount is a dash read as
 * "No amount". In a table, align the cell to the inline end.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Money extends Component
{
    /**
     * The minus sign a negative amount reads with.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const MINUS = "\u{2212}";

    /**
     * @since 1.0.0
     * @since 2.0.0 Added `equivalent` and `equivalentCurrency`.
     *
     * @param  int|null     $amount              The amount in minor units; null renders a dash.
     * @param  string|null  $currency            The ISO 4217 code; defaults to the store's base currency.
     * @param  string|null  $locale              The display locale; defaults to the app locale.
     * @param  int|null     $equivalent          The same amount in `equivalentCurrency`, in its minor units.
     * @param  string|null  $equivalentCurrency  The equivalent's currency; defaults to the store's base currency.
     */
    public function __construct(
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $locale = null,
        public ?int $equivalent = null,
        public ?string $equivalentCurrency = null,
    ) {
        $this->currency ??= (string) config( 'artisanpack.ecommerce.base_currency', 'USD' );
        $this->equivalentCurrency ??= (string) config( 'artisanpack.ecommerce.base_currency', 'USD' );
    }

    /**
     * The formatted amount, a negative one with a true minus sign.
     *
     * @since 1.0.0
     * @since 2.0.0 A negative amount uses "−" (U+2212), not a hyphen.
     *
     * @return string|null
     */
    public function formatted(): ?string
    {
        if ( null === $this->amount ) {
            return null;
        }

        return $this->isNegative() ? self::MINUS . $this->magnitude() : (string) $this->magnitude();
    }

    /**
     * The formatted amount without its sign.
     *
     * @since 2.0.0
     *
     * @return string|null
     */
    public function magnitude(): ?string
    {
        return null === $this->amount ? null : MoneyFormatter::format( abs( $this->amount ), (string) $this->currency, $this->locale );
    }

    /**
     * Whether the amount is below zero.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    public function isNegative(): bool
    {
        return null !== $this->amount && $this->amount < 0;
    }

    /**
     * The formatted equivalent, when there is one in another currency.
     *
     * @since 2.0.0
     *
     * @return string|null
     */
    public function formattedEquivalent(): ?string
    {
        if ( null === $this->amount || null === $this->equivalent || strtoupper( (string) $this->equivalentCurrency ) === strtoupper( (string) $this->currency ) ) {
            return null;
        }

        $formatted = MoneyFormatter::format( abs( $this->equivalent ), (string) $this->equivalentCurrency, $this->locale );

        return $this->equivalent < 0 ? self::MINUS . $formatted : $formatted;
    }

    /**
     * Get the view that represents the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-storefront::components.money' );
    }
}
