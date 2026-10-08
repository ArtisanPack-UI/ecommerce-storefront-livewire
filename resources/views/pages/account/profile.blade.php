{{--
    The profile and notification preferences.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Profile' ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell :title="__( 'Profile' )">
        @include( 'ecommerce-storefront::partials.screen-pending' )
    </x-artisanpack-ec-account-shell>
@endsection
