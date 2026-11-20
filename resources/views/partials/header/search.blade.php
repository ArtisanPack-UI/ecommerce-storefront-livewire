{{--
    Header search: the search box with live suggestions (see
    Search\HeaderSearch), hidden on small screens, where an icon link to
    the search page shows instead. Include it in a host header:

        @include( 'ecommerce-storefront::partials.header.search' )

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<x-artisanpack-ec-search-box class="hidden md:block" />

@if ( \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.storefront.search' ) )
    <x-artisanpack-button
        variant="ghost"
        color="neutral"
        size="sm"
        icon-only
        leading-icon="o-magnifying-glass"
        :href="route( 'artisanpack.ecommerce.storefront.search' )"
        class="md:hidden"
        :aria-label="__( 'Search' )"
        data-header-search-link
    />
@endif
