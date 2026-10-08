{{--
    Header search: the search box with live suggestions (see
    Search\HeaderSearch), hidden on small screens. Include it in a host
    header:

        @include( 'ecommerce-storefront::partials.header.search' )

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<x-artisanpack-ec-search-box class="hidden md:block" />
