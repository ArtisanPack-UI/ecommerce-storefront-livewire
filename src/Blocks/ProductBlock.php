<?php

/**
 * Product block base.
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
 * The base for blocks about one product (spec §11.3, S36): on a product
 * template they show the page's product; anywhere else, the product in
 * their `productId` attribute. See {@see StorefrontBlock::product()}.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
abstract class ProductBlock extends StorefrontBlock
{
    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function attributes(): array
    {
        return [ 'productId' => self::productIdAttribute(), ...$this->productAttributes() ];
    }

    /**
     * The block's own attributes; `productId` is added.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    abstract protected function productAttributes(): array;

    /**
     * The block's HTML for a product.
     *
     * @since 1.0.0
     *
     * @param  Product               $product  The product.
     * @param  array<string, mixed>  $attrs    Clamped attributes.
     *
     * @return string|null
     */
    abstract protected function productHtml( Product $product, array $attrs ): ?string;

    /**
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function keywords(): array
    {
        return [ __( 'product' ) ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function emptyMessage(): string
    {
        return __( 'There are no products to show yet. Add a product, or set a product ID in the block settings.' );
    }

    /**
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $attrs  Clamped attributes.
     *
     * @return string|null
     */
    protected function html( array $attrs ): ?string
    {
        $product = $this->product( $attrs );

        return null === $product ? null : $this->productHtml( $product, $attrs );
    }
}
