<?php

/**
 * Product Grid block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\ProductGrid;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\GridColumns;

/**
 * `artisanpack-commerce/product-grid`: products from a source (newest,
 * featured, on sale, a category, a tag, or hand-picked ids), sorted and
 * limited, in a grid. Renders Catalog\ProductGrid.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class ProductGridBlock extends StorefrontBlock
{
    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'product-grid';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Product Grid' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'A grid of products: newest, featured, on sale, from a category or tag, or hand-picked.' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function attributes(): array
    {
        return [
            'source'        => [
                'type'      => 'string',
                'enum'      => ProductGrid::SOURCES,
                'default'   => 'newest',
                'apControl' => [ 'control' => 'select', 'label' => __( 'Products' ), 'options' => self::options( [
                    'newest'      => __( 'Newest' ),
                    'featured'    => __( 'Featured' ),
                    'on_sale'     => __( 'On sale' ),
                    'category'    => __( 'From a category' ),
                    'tag'         => __( 'With a tag' ),
                    'hand_picked' => __( 'Hand-picked' ),
                ] ) ],
            ],
            'category'      => [ 'type' => 'string', 'default' => '', 'apControl' => [ 'control' => 'text', 'label' => __( 'Category slug' ), 'help' => __( 'For "From a category". Sub-categories are included.' ) ] ],
            'tag'           => [ 'type' => 'string', 'default' => '', 'apControl' => [ 'control' => 'text', 'label' => __( 'Tag slug' ), 'help' => __( 'For "With a tag".' ) ] ],
            'ids'           => [ 'type' => 'string', 'default' => '', 'apControl' => [ 'control' => 'text', 'label' => __( 'Product IDs' ), 'help' => __( 'For "Hand-picked": IDs separated by commas, in the order to show them.' ) ] ],
            'sort'          => [
                'type'      => 'string',
                'enum'      => ProductGrid::SORTS,
                'default'   => 'newest',
                'apControl' => [ 'control' => 'select', 'label' => __( 'Sort by' ), 'options' => self::options( [
                    'newest'     => __( 'Newest' ),
                    'price'      => __( 'Price: low to high' ),
                    '-price'     => __( 'Price: high to low' ),
                    'popularity' => __( 'Most popular' ),
                    'rating'     => __( 'Top rated' ),
                    'name'       => __( 'Name' ),
                ] ) ],
            ],
            'limit'         => [ 'type' => 'number', 'default' => 8, 'apControl' => [ 'control' => 'range', 'label' => __( 'Number of products' ), 'min' => 1, 'max' => ProductGrid::MAX_LIMIT ] ],
            'columns'       => [ 'type' => 'number', 'default' => 4, 'apControl' => [ 'control' => 'range', 'label' => __( 'Columns' ), 'min' => GridColumns::MIN, 'max' => GridColumns::MAX ] ],
            'showPrice'     => [ 'type' => 'boolean', 'default' => true, 'apControl' => [ 'label' => __( 'Show price' ) ] ],
            'showRating'    => [ 'type' => 'boolean', 'default' => true, 'apControl' => [ 'label' => __( 'Show rating' ) ] ],
            'showAddToCart' => [ 'type' => 'boolean', 'default' => true, 'apControl' => [ 'label' => __( 'Show "Add to cart"' ) ] ],
            'heading'       => [ 'type' => 'string', 'default' => '', 'apControl' => [ 'control' => 'text', 'label' => __( 'Heading' ) ] ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function icon(): string
    {
        return 'grid-view';
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function keywords(): array
    {
        return [ __( 'products' ), __( 'featured' ), __( 'sale' ) ];
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
        return view( 'ecommerce-storefront::blocks.livewire', [
            'component' => 'artisanpack-ecommerce-storefront-product-grid',
            'params'    => [
                'source'        => $attrs['source'],
                'category'      => $attrs['category'],
                'tag'           => $attrs['tag'],
                'ids'           => array_map( 'intval', array_filter( array_map( 'trim', explode( ',', (string) $attrs['ids'] ) ), 'ctype_digit' ) ),
                'sort'          => $attrs['sort'],
                'limit'         => $attrs['limit'],
                'columns'       => $attrs['columns'],
                'showPrice'     => $attrs['showPrice'],
                'showRating'    => $attrs['showRating'],
                'showAddToCart' => $attrs['showAddToCart'],
                'heading'       => '' === $attrs['heading'] ? null : $attrs['heading'],
            ],
        ] )->render();
    }
}
