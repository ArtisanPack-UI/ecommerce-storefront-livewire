{{--
    Stands in for a screen whose Livewire component hasn't shipped yet, so
    the route and page exist (and can be linked) from the start. Each
    screen's issue replaces this include with its component.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<x-artisanpack-ec-empty-state
    icon="o-wrench-screwdriver"
    :title="__( 'This page is not available yet' )"
    :description="__( 'Please check back soon.' )"
    data-screen-pending
>
    @if ( \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.storefront.catalog' ) )
        <x-artisanpack-button :href="route( 'artisanpack.ecommerce.storefront.catalog' )" color="primary">{{ __( 'Continue shopping' ) }}</x-artisanpack-button>
    @endif
</x-artisanpack-ec-empty-state>
