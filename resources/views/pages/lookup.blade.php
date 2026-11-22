{{--
    The guest order lookup (#144), with its "Find your order" title.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( __( 'Find your order' ) ) )

@section( 'content' )
    <livewire:artisanpack-ecommerce-storefront-order-lookup :show-title="true" />
@endsection
