{{--
    Downloads and licence keys.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Downloads' ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell :title="__( 'Downloads' )">
        <livewire:artisanpack-ecommerce-storefront-account-downloads />
    </x-artisanpack-ec-account-shell>
@endsection
