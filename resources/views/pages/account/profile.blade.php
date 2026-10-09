{{--
    The profile and notification preferences.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', __( 'Profile' ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell :title="__( 'Profile' )">
        <livewire:artisanpack-ecommerce-storefront-account-profile />
    </x-artisanpack-ec-account-shell>
@endsection
