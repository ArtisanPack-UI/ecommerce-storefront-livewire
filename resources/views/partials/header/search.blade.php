{{--
    Header search: the search box with live suggestions (see
    Search\HeaderSearch) from 768px, and below that a ghost icon button
    linking to the search page. Include it in a host header:

        @include( 'ecommerce-storefront::partials.header.search' )

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<x-artisanpack-ec-search-box class="ec-header-search" />

@if ( \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.storefront.search' ) )
    <x-artisanpack-button
        variant="ghost"
        color="neutral"
        size="sm"
        icon-only
        leading-icon="search"
        :href="route( 'artisanpack.ecommerce.storefront.search' )"
        class="ec-header-search-link"
        :aria-label="__( 'Search' )"
        data-header-search-link
    />
@endif
