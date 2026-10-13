<?php

/**
 * Bundled product purchase form.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms;

use ArtisanPackUI\Ecommerce\Models\ProductChild;
use Illuminate\Contracts\View\View;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-form-bundled :product="$product" />`
 *
 * A bundle is bought as one line at the bundle's own price (from the
 * engine, shown in the page summary). The form lists what it includes and
 * how many of each, then the quantity and "Add to cart". Stock follows the
 * bundle's members (the engine's `StockStatus`).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class BundledForm extends PurchaseForm
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

        $items = $this->product->children()->with( [ 'product', 'variant' ] )->get()
            ->filter( static fn ( ProductChild $child ): bool => null !== $child->product )
            ->map( static fn ( ProductChild $child ): array => [
                'id'       => (int) $child->id,
                'name'     => null !== $child->variant?->name ? $child->product->name . ' — ' . $child->variant->name : (string) $child->product->name,
                'quantity' => max( 1, (int) $child->quantity ),
            ] )
            ->values()
            ->all();

        return view( 'ecommerce-storefront::livewire.product.forms.bundled', [
            'items'         => $items,
            'canAdd'        => null === $blockedReason,
            'blockedReason' => $blockedReason,
        ] );
    }
}
