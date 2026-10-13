<?php

/**
 * Category Grid block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\GridColumns;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\CategoryGrid;
use Illuminate\Support\Facades\Blade;

/**
 * `artisanpack-commerce/category-grid`: the top-level categories, or a
 * parent's sub-categories, as tiles with images and product counts.
 * Renders `<x-artisanpack-ec-category-grid>`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class CategoryGridBlock extends StorefrontBlock
{
    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'category-grid';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Category Grid' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'Shop by category: category tiles with images and product counts.' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function attributes(): array
    {
        return [
            'parent'     => [ 'type' => 'string', 'default' => '', 'apControl' => [ 'control' => 'text', 'label' => __( 'Parent category slug' ), 'help' => __( 'Show this category\'s sub-categories. Leave empty for the top-level categories.' ) ] ],
            'limit'      => [ 'type' => 'number', 'default' => 6, 'apControl' => [ 'control' => 'range', 'label' => __( 'Number of categories' ), 'min' => 1, 'max' => CategoryGrid::MAX_LIMIT ] ],
            'columns'    => [ 'type' => 'number', 'default' => 3, 'apControl' => [ 'control' => 'range', 'label' => __( 'Columns' ), 'min' => GridColumns::MIN, 'max' => GridColumns::MAX ] ],
            'showCounts' => [ 'type' => 'boolean', 'default' => true, 'apControl' => [ 'label' => __( 'Show product counts' ) ] ],
            'showImages' => [ 'type' => 'boolean', 'default' => true, 'apControl' => [ 'label' => __( 'Show images' ) ] ],
            'heading'    => [ 'type' => 'string', 'default' => '', 'apControl' => [ 'control' => 'text', 'label' => __( 'Heading' ) ] ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function icon(): string
    {
        return 'category';
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function keywords(): array
    {
        return [ __( 'categories' ) ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function emptyMessage(): string
    {
        return __( 'No categories to show. Check the parent category slug.' );
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
        $grid = new CategoryGrid(
            '' === $attrs['parent'] ? null : $attrs['parent'],
            $attrs['limit'],
            $attrs['columns'],
            $attrs['showCounts'],
            $attrs['showImages'],
            '' === $attrs['heading'] ? null : $attrs['heading'],
        );

        if ( [] === $grid->tiles ) {
            return null;
        }

        return Blade::renderComponent( $grid );
    }
}
