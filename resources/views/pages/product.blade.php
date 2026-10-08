{{--
    A product page.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', $product->name )

@section( 'content' )
    <div class="flex flex-col gap-6">
        <h1 class="text-3xl font-bold">{{ $product->name }}</h1>

        @include( 'ecommerce-storefront::partials.screen-pending' )
    </div>
@endsection
