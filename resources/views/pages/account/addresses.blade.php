{{--
    The address book.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', __( 'Addresses' ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell :title="__( 'Addresses' )">
        <livewire:artisanpack-ecommerce-storefront-account-addresses />
    </x-artisanpack-ec-account-shell>
@endsection
