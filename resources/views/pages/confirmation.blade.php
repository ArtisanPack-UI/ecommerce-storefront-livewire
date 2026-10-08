{{--
    An order's confirmation page.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Order confirmation' ) )

@section( 'content' )
    <div class="flex flex-col gap-6">
        <h1 class="text-3xl font-bold">{{ __( 'Order confirmation' ) }}</h1>

        <livewire:artisanpack-ecommerce-storefront-order-confirmation :order="$order" :token="$token" />
    </div>
@endsection
