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
     * The sizes: `lg` on the product page, `md`, `sm` in the cart and `xs`
     * (extra-small buttons) in the drawer.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const SIZES = [ 'lg', 'md', 'sm', 'xs' ];

    /**
     * @since 1.0.0
     *
     * @param  string       $id        The input id.
     * @param  string|null  $label     The visible label (null for an sr-only "Quantity", or "Quantity, {itemName}").
     * @param  int          $min       The lowest value.
     * @param  int|null     $max       The highest value.
     * @param  string|null  $error     The error message to show.
     * @param  string|null  $itemName  Names the item in the buttons' labels ("Increase quantity, Mug") and the hidden field label.
     * @param  string       $size      `lg` (product page), `md`, `sm` (cart) or `xs` (drawer).
     */
    public function __construct(
        public string $id,
        public ?string $label = null,
        public int $min = 1,
        public ?int $max = null,
        public ?string $error = null,
        public ?string $itemName = null,
        public string $size = 'md',
    ) {
        $this->size = in_array( $size, self::SIZES, true ) ? $size : 'md';
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
