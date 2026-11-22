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

use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\DisplayData;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Money\Money;

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

    /**
     * The product's price in the shopper's currency, unless it is a range.
     *
     * @since 2.0.0
     *
     * @return Money|null
     */
    protected function unitPrice(): ?Money
    {
        $cart  = app( StorefrontCart::class );
        $price = app( PriceDisplayResolver::class )->for( DisplayData::for( $this->product ), $cart->currency(), null, $cart->customer() );

        return null === $price || $price->isRange() ? null : $price->price;
    }
}
