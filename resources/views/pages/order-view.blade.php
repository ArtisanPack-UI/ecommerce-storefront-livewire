{{--
    A guest's order, opened with a signed order-view link (lookup or
    confirmation email). The account order component shows it read-only.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Your order' ) )

@section( 'content' )
    <div class="flex flex-col gap-6">
        <h1 class="text-3xl font-bold">{{ __( 'Your order' ) }}</h1>

        <livewire:artisanpack-ecommerce-storefront-account-order :order="$order" :token="$token" />
    </div>
@endsection
