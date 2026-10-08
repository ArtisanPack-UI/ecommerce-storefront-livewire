{{--
    The account dashboard.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Your account' ) )

@section( 'content' )
    <div class="flex flex-col gap-6">
        <h1 class="text-3xl font-bold">{{ __( 'Your account' ) }}</h1>

        @include( 'ecommerce-storefront::partials.screen-pending' )
    </div>
@endsection
