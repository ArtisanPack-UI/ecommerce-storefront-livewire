{{--
    An order in the shopper's account.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Order details' ) )

@section( 'content' )
    <x-artisanpack-ec-account-shell :title="__( 'Order details' )">
        <livewire:artisanpack-ecommerce-storefront-account-order :order="$order" />
    </x-artisanpack-ec-account-shell>
@endsection
