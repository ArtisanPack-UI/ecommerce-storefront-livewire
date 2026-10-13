<?php

/**
 * Product Catalog block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontContext;

/**
 * `artisanpack-commerce/product-catalog`: the product listing of the page
 * it is on, with filters, sort, and pagination — the category's products
 * on a category page (Catalog\CategoryShow), the tag's on a tag page
 * (Catalog\TagShow), search results on the search page (Search\Index),
 * and the whole catalog anywhere else (Catalog\Index). The default
 * archive, category, tag, and search templates are built on it
 * (spec §11.4).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class ProductCatalogBlock extends StorefrontBlock
{
    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'product-catalog';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Product Catalog' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'The products of the page this block is on (the catalog, a category, a tag, or search results) with filters, sorting, and pages.' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function attributes(): array
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
        return 'archive';
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function keywords(): array
    {
        return [ __( 'catalog' ), __( 'archive' ), __( 'category' ), __( 'search' ) ];
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
        $context = app( StorefrontContext::class );

        [ $component, $params ] = match ( true ) {
            null !== $context->category()  => [ 'artisanpack-ecommerce-storefront-category-show', [ 'category' => $context->category() ] ],
            null !== $context->tag()       => [ 'artisanpack-ecommerce-storefront-tag-show', [ 'tag' => $context->tag() ] ],
            'search' === $context->page()  => [ 'artisanpack-ecommerce-storefront-search', [] ],
            default                        => [ 'artisanpack-ecommerce-storefront-catalog', [] ],
        };

        return view( 'ecommerce-storefront::blocks.livewire', [ 'component' => $component, 'params' => $params ] )->render();
    }
}
