{{--
    An order's confirmation page (#143): the confirmation component, whose
    "Thank you for your order" is the page's title.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( __( 'Order confirmation' ) ) )

@section( 'content' )
    <livewire:artisanpack-ecommerce-storefront-order-confirmation :order="$order" :token="$token" :show-title="true" />
@endsection
