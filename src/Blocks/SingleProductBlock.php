<?php

/**
 * Single Product block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\Ecommerce\Models\Product;

/**
 * `artisanpack-commerce/single-product`: the full product component
 * (gallery, summary, purchase form, details, reviews, related). Renders
 * Product\Show; previews don't count as views.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class SingleProductBlock extends ProductBlock
{
    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'single-product';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Single Product' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'A whole product: images, price, add to cart, details, and reviews.' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    protected function productAttributes(): array
    {
        return [];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function icon(): string
    {
        return 'products';
    }

    /**
     * @since 1.0.0
     *
     * @param  Product               $product  The product.
     * @param  array<string, mixed>  $attrs    Clamped attributes.
     *
     * @return string|null
     */
    protected function productHtml( Product $product, array $attrs ): ?string
    {
        return view( 'ecommerce-storefront::blocks.livewire', [
            'component' => 'artisanpack-ecommerce-storefront-product-show',
            'params'    => [ 'product' => $product, 'recordView' => ! self::previewing() ],
        ] )->render();
    }
}
