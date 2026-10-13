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

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-rating-input id="rating" :label="__( 'Your rating' )" wire:model="rating" :error="$errors->first( 'rating' )" />`
 *
 * A 1–5 star picker: a fieldset of radio buttons drawn as daisyUI rating
 * stars, each named for screen readers ("4 stars"), so arrow keys move
 * between ratings. An error is linked to the group with
 * `aria-describedby`.
 *
 * Local stand-in for `x-artisanpack-rating`, which fails to render in
 * livewire-ui-components 2.1 (its `size()` returns a `Stringable` where
 * `?string` is declared) and doesn't label its stars. When that is fixed,
 * this is the one file to swap.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class RatingInput extends Component
{
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
