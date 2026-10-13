<?php

/**
 * Product display data.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\Product;

/**
 * A copy of a product with what its display price and stock state read
 * already loaded (prices, stock rows, variants with theirs — the engine's
 * `Product::displayRelations()`), so pricing a product with many variants
 * runs the same queries as one with a few (spec §12).
 *
 * Components keep their public `Product` property as it is: loading the
 * relations onto it would make Livewire reload them on every update
 * request. They price and stock the copy instead.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class DisplayData
{
    /**
     * A copy of `$product` with its display relations loaded.
     *
     * @since 1.0.0
     *
     * @param  Product  $product  The product.
     *
     * @return Product
     */
    public static function for( Product $product ): Product
    {
        return ( clone $product )->loadMissing( Product::displayRelations() );
    }
}
