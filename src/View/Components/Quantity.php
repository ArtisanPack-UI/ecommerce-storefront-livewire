<?php

/**
 * Quantity stepper component.
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
 * `<x-artisanpack-ec-quantity id="qty" :label="__( 'Quantity' )" wire:model="quantity" :error="$errors->first( 'quantity' )" />`
 *
 * A number input between "Decrease" and "Increase" buttons. The buttons
 * step the input and fire `input`, so `wire:model` (deferred or live)
 * picks the change up. An error is linked to the input with
 * `aria-describedby`.
 *
 * Local stand-in for the library's quantity stepper
 * (livewire-ui-components#120, spec §10 U1); when it ships, this is the one
 * file to swap.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Quantity extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  string       $id        The input id.
     * @param  string|null  $label     The visible label (null for an sr-only "Quantity").
     * @param  int          $min       The lowest value.
     * @param  int|null     $max       The highest value.
     * @param  string|null  $error     The error message to show.
     * @param  string|null  $itemName  Names the item in the buttons' labels ("Increase quantity of Mug").
     */
    public function __construct(
        public string $id,
        public ?string $label = null,
        public int $min = 1,
        public ?int $max = null,
        public ?string $error = null,
        public ?string $itemName = null,
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
        return view( 'ecommerce-storefront::components.quantity' );
    }
}
