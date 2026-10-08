{{--
    The address book.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Addresses' ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell :title="__( 'Addresses' )">
        @include( 'ecommerce-storefront::partials.screen-pending' )
    </x-artisanpack-ec-account-shell>
@endsection
