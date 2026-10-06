{{--
    A category page: the category's products, sub-categories included.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', $category->name )

@section( 'content' )
    <div class="flex flex-col gap-6">
        <h1 class="text-3xl font-bold">{{ $category->name }}</h1>

        <livewire:artisanpack-ecommerce-storefront-catalog :category="(int) $category->id" />
    </div>
@endsection
