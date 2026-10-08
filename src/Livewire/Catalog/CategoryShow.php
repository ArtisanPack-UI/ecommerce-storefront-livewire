<?php

/**
 * Category page component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog;

use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\SafeHtml;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

/**
 * `<livewire:artisanpack-ecommerce-storefront-category-show :category="$category" />`
 *
 * A category page (spec §7.1): breadcrumbs from the category tree, a header
 * with the name, description (through `kses()` in safe mode), and image, sub-category
 * chips, and the catalog scoped to the category and its sub-categories,
 * with the catalog's filters and sorts.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class CategoryShow extends Component
{
    /**
     * The category.
     *
     * @since 1.0.0
     *
     * @var ProductCategory
     */
    public ProductCategory $category;

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $paths = app( CategoryPaths::class );
        $id    = (int) $this->category->id;

        return view( 'ecommerce-storefront::livewire.catalog.category-show', [
            'breadcrumbs'   => $this->breadcrumbs( $paths, $id ),
            'subcategories' => array_map( static fn ( array $node ): array => [
                'id'   => $node['id'],
                'name' => $node['name'],
                'url'  => $paths->url( $node['id'] ),
            ], $paths->children( $id ) ),
            'description'   => SafeHtml::clean( $this->category->description ),
            'image'         => ProductImages::media( null === $this->category->image_media_id ? null : (int) $this->category->image_media_id, (string) $this->category->name ),
        ] );
    }

    /**
     * The breadcrumb trail: the shop, the category's ancestors, then the
     * category itself (unlinked).
     *
     * @since 1.0.0
     *
     * @param  CategoryPaths  $paths  Category paths.
     * @param  int            $id     Category id.
     *
     * @return array<int, array{label: string, link?: string}>
     */
    protected function breadcrumbs( CategoryPaths $paths, int $id ): array
    {
        $items = [];

        if ( Route::has( 'artisanpack.ecommerce.storefront.catalog' ) ) {
            $items[] = [ 'label' => __( 'Shop' ), 'link' => route( 'artisanpack.ecommerce.storefront.catalog' ) ];
        }

        foreach ( $paths->ancestry( $id ) as $node ) {
            $url = $node['id'] === $id ? null : $paths->url( $node['id'] );

            $items[] = null === $url ? [ 'label' => $node['name'] ] : [ 'label' => $node['name'], 'link' => $url ];
        }

        return $items;
    }
}
