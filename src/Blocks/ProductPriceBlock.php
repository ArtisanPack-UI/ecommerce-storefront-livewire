<?php

/**
 * Price block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\Price;
use Illuminate\Support\Facades\Blade;

/**
 * `artisanpack-commerce/product-price`: a product's display price in the
 * shopper's currency (sale and from-prices included). Renders
 * `<x-artisanpack-ec-price>`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class ProductPriceBlock extends ProductBlock
{
    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'product-price';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Price' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'A product\'s price, with any sale price.' );
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
        return 'money-alt';
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
        $price = app( PriceDisplayResolver::class )->for( $product, app( StorefrontCart::class )->currency() );

        return Blade::renderComponent( ( new Price( $price ) )->withAttributes( [ 'class' => 'text-xl' ] ) );
    }
}
