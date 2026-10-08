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

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Throwable;

/**
 * `<x-artisanpack-ec-price-range :min="0" :max="12000" currency="EUR" from-model="priceMin" to-model="priceMax" />`
 *
 * Two labelled range inputs (lowest and highest price) bound to a parent
 * Livewire component's properties in minor units. The values are entangled
 * (deferred) and sent when a handle is released, so dragging doesn't send a
 * request per pixel. A handle at its end of the range sends null, which
 * keeps an unused bound out of the URL.
 *
 * Local stand-in for the library's dual-handle range slider
 * (livewire-ui-components#121, spec §10 U2); when it ships, this is the one
 * file to swap.
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
    ) {
        $this->max       = max( $this->min, $this->max );
        $this->step      = max( 1, $this->step );
        $this->idPrefix ??= 'ec-price-range';

        try {
            $this->divisor = 10 ** ( new ISOCurrencies() )->subunitFor( new Currency( strtoupper( $this->currency ) ) );
        } catch ( Throwable ) {
            $this->divisor = 100;
        }
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
