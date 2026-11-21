<?php

/**
 * Star rating input component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPack\LivewireUiComponents\Support\IconRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-rating-input id="rating" :label="__( 'Your rating' )" wire:model="rating" :error="$errors->first( 'rating' )" />`
 *
 * A 1–5 star picker on the design-system Rating: a radiogroup fieldset of
 * native radios, each named for screen readers ("4 stars"), so arrow keys
 * move between ratings. Hover previews the value and the choice shows as
 * "3 of 5". An error is linked to the group with `aria-describedby`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class RatingInput extends Component
{
    /**
     * The star's `<svg>`, from the design system's icon registry.
     *
     * @since 2.0.0
     *
     * @var HtmlString
     */
    public HtmlString $starIcon;

    /**
     * The error's alert `<svg>`, from the design system's icon registry.
     *
     * @since 2.0.0
     *
     * @var HtmlString
     */
    public HtmlString $errorIcon;

    /**
     * @since 1.0.0
     *
     * @param  string       $id        The id prefix (and radio group name).
     * @param  string|null  $label     The legend.
     * @param  string|null  $error     The error message to show.
     * @param  bool         $required  Mark the group as required.
     */
    public function __construct(
        public string $id,
        public ?string $label = null,
        public ?string $error = null,
        public bool $required = false,
    ) {
        $this->starIcon  = new HtmlString( IconRegistry::render( 'star' ) );
        $this->errorIcon = new HtmlString( IconRegistry::render( 'circle-alert' ) );
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
        return view( 'ecommerce-storefront::components.rating-input' );
    }
}
