<?php

/**
 * Price range component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CurrencyNames;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Throwable;

/**
 * `<x-artisanpack-ec-price-range :min="0" :max="12000" currency="EUR" from-model="priceMin" to-model="priceMax" />`
 *
 * The design-system Range with two thumbs (lowest and highest price) and
 * an Input for each bound with the currency symbol as its prefix, bound to
 * a parent Livewire component's properties in minor units. The values are
 * entangled (deferred) and sent when a thumb is released or an input
 * changes, so dragging doesn't send a request per pixel. A bound at its end
 * of the range sends null, which keeps an unused bound out of the URL.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class PriceRange extends Component
{
    /**
     * Minor units per major unit of the currency (100 for EUR).
     *
     * @since 1.0.0
     *
     * @var int
     */
    public int $divisor;

    /**
     * Decimal places in a major-unit amount (2 for EUR, 0 for JPY).
     *
     * @since 2.0.0
     *
     * @var int
     */
    public int $decimals;

    /**
     * The inputs' prefix: the currency's symbol ("€"), else its code.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public string $symbol;

    /**
     * @since 1.0.0
     *
     * @param  int          $min        The lowest selectable amount (minor units).
     * @param  int          $max        The highest selectable amount (minor units).
     * @param  string       $currency   ISO 4217 code.
     * @param  string       $fromModel  The parent property for the lowest price.
     * @param  string       $toModel    The parent property for the highest price.
     * @param  int          $step       Step in minor units.
     * @param  string|null  $label      The group's visible label.
     * @param  string|null  $idPrefix   Prefix for the inputs' ids.
     * @param  int|null     $from       The current lowest price, for the first render (null: `min`).
     * @param  int|null     $to         The current highest price, for the first render (null: `max`).
     */
    public function __construct(
        public int $min,
        public int $max,
        public string $currency,
        public string $fromModel,
        public string $toModel,
        public int $step = 100,
        public ?string $label = null,
        public ?string $idPrefix = null,
        public ?int $from = null,
        public ?int $to = null,
    ) {
        $this->max       = max( $this->min, $this->max );
        $this->step      = max( 1, $this->step );
        $this->idPrefix ??= 'ec-price-range';
        $this->from       = null === $this->from ? null : max( $this->min, min( $this->max, $this->from ) );
        $this->to         = null === $this->to ? null : max( $this->from ?? $this->min, min( $this->max, $this->to ) );

        try {
            $this->decimals = ( new ISOCurrencies() )->subunitFor( new Currency( strtoupper( $this->currency ) ) );
        } catch ( Throwable ) {
            $this->decimals = 2;
        }

        $this->divisor = 10 ** $this->decimals;
        $this->symbol  = CurrencyNames::symbol( strtoupper( $this->currency ) ) ?? strtoupper( $this->currency );
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
        return view( 'ecommerce-storefront::components.price-range' );
    }
}
