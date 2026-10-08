{{--
    The account dashboard.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Your account' ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell :title="__( 'Your account' )">
        <livewire:artisanpack-ecommerce-storefront-account-dashboard />
    </x-artisanpack-ec-account-shell>
@endsection
