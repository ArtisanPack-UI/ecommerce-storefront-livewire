{{--
    A product page.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', $product->name )

@section( 'content' )
    <livewire:artisanpack-ecommerce-storefront-product-show :product="$product" />
@endsection
