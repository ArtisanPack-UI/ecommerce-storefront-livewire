{{--
    The address book (#148): the account shell without a title of its own,
    since the component's "Saved addresses" shares its row with "Add an
    address".

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( __( 'Saved addresses' ) ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell>
        <livewire:artisanpack-ecommerce-storefront-account-addresses :show-title="true" />
    </x-artisanpack-ec-account-shell>
@endsection
