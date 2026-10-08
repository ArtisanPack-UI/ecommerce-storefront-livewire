<?php

/**
 * Product Gallery block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\Gallery;
use Illuminate\Support\Facades\Blade;

/**
 * `artisanpack-commerce/product-gallery`: a product's images, with the
 * thumbnails below, beside (start or end), or hidden, and optional
 * click-to-zoom. Renders `<x-artisanpack-ec-gallery>`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class ProductGalleryBlock extends ProductBlock
{
    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'product-gallery';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Product Gallery' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'A product\'s images with thumbnails and zoom.' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    protected function productAttributes(): array
    {
        return [
            'thumbnails' => [
                'type'      => 'string',
                'enum'      => Gallery::THUMBNAIL_POSITIONS,
                'default'   => 'bottom',
                'apControl' => [ 'control' => 'select', 'label' => __( 'Thumbnails' ), 'options' => self::options( [
                    'bottom' => __( 'Below the image' ),
                    'start'  => __( 'Beside the image (start)' ),
                    'end'    => __( 'Beside the image (end)' ),
                    'none'   => __( 'Hidden' ),
                ] ) ],
            ],
            'zoom'       => [ 'type' => 'boolean', 'default' => true, 'apControl' => [ 'label' => __( 'Zoom on click' ) ] ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function icon(): string
    {
        return 'format-gallery';
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
        return Blade::renderComponent( new Gallery(
            ProductImages::withVariants( $product ),
            (string) $product->name,
            0,
            'product-' . $product->id,
            $attrs['thumbnails'],
            $attrs['zoom'],
        ) );
    }
}
