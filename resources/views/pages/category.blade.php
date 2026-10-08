{{--
    A category page: the category's products, sub-categories included.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', $category->name )

@section( 'content' )
    <livewire:artisanpack-ecommerce-storefront-category-show :category="$category" />
@endsection
