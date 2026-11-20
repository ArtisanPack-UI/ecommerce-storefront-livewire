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
 * Renders the engine's {@see DisplayPrice} (spec §8.1) with design-system
 * text styles (`ec-price` in resources/css/storefront.css): weight 500 and
 * tabular figures, sized for the context (`size`: sm, md for cards, lg for
 * the product page).
 *
 * - Sale: the price in the error text color, then the original price muted
 *   and struck through, each with a visually hidden label ("Sale price",
 *   "Original price"), so color is never the only signal; `sale-badge` adds
 *   a soft error badge with the percentage off.
 * - Range: "$98.00 – $138.00", read as "From $98.00 to $138.00"; with
 *   `from`, a muted "From" before the lowest price instead.
 * - Free: a zero price reads "Free" in the success text color, never "$0.00".
 * - Whether the price includes tax, muted after it.
 *
 * A null price renders "Price unavailable".
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
     * @since 2.0.0 Added `size` and `from`; the sale badge shows the percentage off.
     *
     * @param  DisplayPrice|null  $price      The display price.
     * @param  bool               $showTax    Show the "incl. / excl. tax" label.
     * @param  bool               $saleBadge  Show the percentage-off badge for a sale price.
     * @param  string|null        $locale     The display locale; defaults to the app locale.
     * @param  string             $size       sm, md (cards) or lg (the product page).
     * @param  bool               $from       Show a range as "From" and its lowest price.
     */
    public function __construct(
        public ?DisplayPrice $price = null,
        public bool $showTax = true,
        public bool $saleBadge = true,
        public ?string $locale = null,
        public string $size = 'md',
        public bool $from = false,
    ) {
        if ( ! in_array( $this->size, [ 'sm', 'md', 'lg' ], true ) ) {
            $this->size = 'md';
        }
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
     * Whether the price is free: zero, and not a range.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    public function isFree(): bool
    {
        return null !== $this->price && ! $this->price->isRange() && $this->price->price->isZero();
    }

    /**
     * The whole percentage off a sale price, or null when there is no sale
     * or it rounds to nothing.
     *
     * @since 2.0.0
     *
     * @return int|null
     */
    public function percentOff(): ?int
    {
        if ( null === $this->price || ! $this->price->onSale() || null === $this->price->compareAt ) {
            return null;
        }

        $was = (int) $this->price->compareAt->getAmount();
        $now = (int) $this->price->price->getAmount();

        if ( $was <= 0 || $now >= $was ) {
            return null;
        }

        $percent = (int) round( ( $was - $now ) / $was * 100 );

        return $percent > 0 ? $percent : null;
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
