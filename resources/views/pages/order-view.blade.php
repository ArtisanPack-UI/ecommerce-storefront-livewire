{{--
    A guest's order, opened with a signed order-view link (lookup or
    confirmation email). The account order component shows it read-only
    (#145), under an "Order lookup" breadcrumb, with "Order {number}" as the
    page's title.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( __( 'Your order' ) ) )

@section( 'content' )
    <livewire:artisanpack-ecommerce-storefront-account-order :order="$order" :token="$token" :show-title="true" />
@endsection
