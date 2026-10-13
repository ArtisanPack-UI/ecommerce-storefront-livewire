<?php

/**
 * Simple product purchase form.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms;

use Illuminate\Contracts\View\View;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-form-simple :product="$product" />`
 *
 * Quantity and "Add to cart" for a `simple` product. When the product is out
 * of stock or unpriced, the button is disabled and says why.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class SimpleForm extends PurchaseForm
{
    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $blockedReason = $this->blockedReason();

        return view( 'ecommerce-storefront::livewire.product.forms.simple', [
            'canAdd'        => null === $blockedReason,
            'blockedReason' => $blockedReason,
        ] );
    }
}
