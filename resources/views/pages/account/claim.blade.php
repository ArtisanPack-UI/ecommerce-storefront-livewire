{{--
    Claiming a guest order.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Claim an order' ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell :title="__( 'Claim an order' )">
        @include( 'ecommerce-storefront::partials.screen-pending' )
    </x-artisanpack-ec-account-shell>
@endsection
