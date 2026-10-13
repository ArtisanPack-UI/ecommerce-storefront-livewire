<?php

/**
 * Related Products block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\Ecommerce\Models\ProductRelation;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\GridColumns;

/**
 * `artisanpack-commerce/related-products`: a product's related products,
 * upsells, or cross-sells (the page's product from `StorefrontContext`, or
 * `productId`). Cross-sells with no product suggest for the cart. Renders
 * Product\RelatedProducts.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class RelatedProductsBlock extends StorefrontBlock
{
    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'related-products';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Related Products' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'Related products, upsells, or cross-sells for the product on the page.' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function attributes(): array
    {
        return [
            'type'      => [
                'type'      => 'string',
                'enum'      => ProductRelation::TYPES,
                'default'   => ProductRelation::RELATED,
                'apControl' => [ 'control' => 'select', 'label' => __( 'Show' ), 'options' => self::options( [
                    ProductRelation::RELATED    => __( 'Related products' ),
                    ProductRelation::UPSELL     => __( 'Upsells' ),
                    ProductRelation::CROSS_SELL => __( 'Cross-sells' ),
                ] ) ],
            ],
            'limit'     => [ 'type' => 'number', 'default' => 4, 'apControl' => [ 'control' => 'range', 'label' => __( 'Number of products' ), 'min' => 1, 'max' => 12 ] ],
            'columns'   => [ 'type' => 'number', 'default' => 4, 'apControl' => [ 'control' => 'range', 'label' => __( 'Columns' ), 'min' => GridColumns::MIN, 'max' => GridColumns::MAX ] ],
            'productId' => self::productIdAttribute(),
            'heading'   => [ 'type' => 'string', 'default' => '', 'apControl' => [ 'control' => 'text', 'label' => __( 'Heading' ) ] ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function icon(): string
    {
        return 'networking';
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function keywords(): array
    {
        return [ __( 'upsells' ), __( 'cross-sells' ) ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function emptyMessage(): string
    {
        return __( 'Add this block to a product template, or set a product ID in the block settings.' );
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

        if ( null === $product && ProductRelation::CROSS_SELL !== $attrs['type'] ) {
            return null;
        }

        return view( 'ecommerce-storefront::blocks.livewire', [
            'component' => 'artisanpack-ecommerce-storefront-related-products',
            'params'    => [
                'product' => $product,
                'type'    => $attrs['type'],
                'limit'   => $attrs['limit'],
                'columns' => $attrs['columns'],
                'heading' => '' === $attrs['heading'] ? null : $attrs['heading'],
                // The editor canvas doesn't run Livewire, so a preview can't lazy-load.
                ...( self::previewing() ? [ 'lazy' => false ] : [] ),
            ],
        ] )->render();
    }
}
