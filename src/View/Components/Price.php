<?php

/**
 * Display price component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\Ecommerce\Pricing\DisplayPrice;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Money\Money;

/**
 * `<x-artisanpack-ec-price :price="$displayPrice" />`
 *
 * Renders the engine's {@see DisplayPrice} (spec §8.1): the price, the
 * struck-through compare-at price with a "Sale" badge, a variable product's
 * from/to range, and whether the price includes tax. Screen readers hear one
 * sentence ("Sale price: was $25.00, now $19.00") instead of two bare
 * amounts. A null price renders "Price unavailable".
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Price extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  DisplayPrice|null  $price      The display price.
     * @param  bool               $showTax    Show the "incl. / excl. tax" label.
     * @param  bool               $saleBadge  Show the "Sale" badge for a sale price.
     * @param  string|null        $locale     The display locale; defaults to the app locale.
     */
    public function __construct(
        public ?DisplayPrice $price = null,
        public bool $showTax = true,
        public bool $saleBadge = true,
        public ?string $locale = null,
    ) {
    }

    /**
     * Formats an amount.
     *
     * @since 1.0.0
     *
     * @param  Money  $money  The amount.
     *
     * @return string
     */
    public function format( Money $money ): string
    {
        return MoneyFormatter::formatMoney( $money, $this->locale );
    }

    /**
     * The tax label ("incl. VAT", "excl. tax"), or null when none applies.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function taxNote(): ?string
    {
        if ( ! $this->showTax || null === $this->price || null === $this->price->taxLabel || '' === $this->price->taxLabel ) {
            return null;
        }

        return $this->price->pricesIncludeTax
            ? __( 'incl. :label', [ 'label' => $this->price->taxLabel ] )
            : __( 'excl. :label', [ 'label' => $this->price->taxLabel ] );
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
        return view( 'ecommerce-storefront::components.price' );
    }
}
