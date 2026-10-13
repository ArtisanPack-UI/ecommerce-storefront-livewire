<?php

/**
 * Reviews block.
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
 * `artisanpack-commerce/product-reviews`: a product's rating summary and
 * approved reviews, with the review form when it is on. Renders
 * Product\Reviews.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class ProductReviewsBlock extends ProductBlock
{
    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'product-reviews';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Reviews' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'A product\'s ratings and reviews, and the form to write one.' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    protected function productAttributes(): array
    {
        return [
            'perPage'  => [ 'type' => 'number', 'default' => 5, 'apControl' => [ 'control' => 'range', 'label' => __( 'Reviews per page' ), 'min' => 1, 'max' => 50 ] ],
            'showForm' => [ 'type' => 'boolean', 'default' => true, 'apControl' => [ 'label' => __( 'Show the review form' ) ] ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function icon(): string
    {
        return 'star-filled';
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function keywords(): array
    {
        return [ __( 'product' ), __( 'ratings' ) ];
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
            'component' => 'artisanpack-ecommerce-storefront-product-reviews',
            'params'    => [
                'product'        => $product,
                'reviewsPerPage' => $attrs['perPage'],
                'allowForm'      => $attrs['showForm'],
            ],
        ] )->render();
    }
}
