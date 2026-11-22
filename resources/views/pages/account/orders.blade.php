{{--
    The order history (#147): the account shell without a title of its
    own, since the component's "Orders" shares its row with the filter.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( __( 'Orders' ) ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell>
        <livewire:artisanpack-ecommerce-storefront-account-orders :show-title="true" />
    </x-artisanpack-ec-account-shell>
@endsection
