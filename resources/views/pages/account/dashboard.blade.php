{{--
    The account dashboard (#146): the account shell without a title of its
    own, since the dashboard's greeting is the page's title.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( __( 'Your account' ) ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell>
        <livewire:artisanpack-ecommerce-storefront-account-dashboard :show-title="true" />
    </x-artisanpack-ec-account-shell>
@endsection
