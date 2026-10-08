{{--
    The order history.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Orders' ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell :title="__( 'Orders' )">
        <livewire:artisanpack-ecommerce-storefront-account-orders />
    </x-artisanpack-ec-account-shell>
@endsection
