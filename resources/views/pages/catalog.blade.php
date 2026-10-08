{{--
    The catalog page.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', __( 'Shop' ) )

@section( 'content' )
    <div class="flex flex-col gap-6">
        <h1 class="text-3xl font-bold">{{ __( 'Shop' ) }}</h1>

        <livewire:artisanpack-ecommerce-storefront-catalog />
    </div>
@endsection
