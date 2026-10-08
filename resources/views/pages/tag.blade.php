{{--
    A tag page: the products with the tag.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', $tag->name )

@section( 'content' )
    <livewire:artisanpack-ecommerce-storefront-tag-show :tag="$tag" />
@endsection
