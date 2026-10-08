<?php

/**
 * Tag page component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog;

use ArtisanPackUI\Ecommerce\Models\ProductTag;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

/**
 * `<livewire:artisanpack-ecommerce-storefront-tag-show :tag="$tag" />`
 *
 * A tag page (spec §7.1): the tag's name and the catalog scoped to it, with
 * the catalog's filters (less the tag filter) and sorts.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class TagShow extends Component
{
    /**
     * The tag.
     *
     * @since 1.0.0
     *
     * @var ProductTag
     */
    public ProductTag $tag;

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $breadcrumbs = Route::has( 'artisanpack.ecommerce.storefront.catalog' )
            ? [ [ 'label' => __( 'Shop' ), 'link' => route( 'artisanpack.ecommerce.storefront.catalog' ) ], [ 'label' => (string) $this->tag->name ] ]
            : [];

        return view( 'ecommerce-storefront::livewire.catalog.tag-show', [ 'breadcrumbs' => $breadcrumbs ] );
    }
}
