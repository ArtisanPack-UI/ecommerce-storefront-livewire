{{--
    An order in the shopper's account.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Order details' ) )

@section( 'content' )
    <div class="flex flex-col gap-6">
        <h1 class="text-3xl font-bold">{{ __( 'Order details' ) }}</h1>

        @include( 'ecommerce-storefront::partials.screen-pending' )
    </div>
@endsection
