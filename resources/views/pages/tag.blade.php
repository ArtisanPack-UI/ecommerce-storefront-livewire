{{--
    A tag page: the products with the tag.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', $tag->name )

@section( 'content' )
    <div class="flex flex-col gap-6">
        <h1 class="text-3xl font-bold">{{ $tag->name }}</h1>

        <livewire:artisanpack-ecommerce-storefront-catalog :tag="(int) $tag->id" />
    </div>
@endsection
