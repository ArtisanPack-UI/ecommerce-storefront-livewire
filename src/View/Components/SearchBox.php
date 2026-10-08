<?php

/**
 * Search box component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-search-box />`
 *
 * The header search box with live suggestions (Search\HeaderSearch), for
 * host layouts. Give each box on a page its own `input-id`; other
 * attributes (such as `class`) go on the wrapper.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class SearchBox extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  string  $inputId  The input's id.
     */
    public function __construct(
        public string $inputId = 'ecommerce-header-search',
    ) {
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-storefront::components.search-box' );
    }
}
