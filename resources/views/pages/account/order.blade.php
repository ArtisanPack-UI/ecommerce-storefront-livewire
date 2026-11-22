{{--
    An order in the shopper's account (#145): the account shell without a
    title of its own, since the order's "Order {number}" heading is the
    page's title.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( __( 'Order details' ) ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell>
        <livewire:artisanpack-ecommerce-storefront-account-order :order="$order" :show-title="true" />
    </x-artisanpack-ec-account-shell>
@endsection
