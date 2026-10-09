{{--
    The checkout.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', __( 'Checkout' ) )

@section( 'content' )
    @if ( null !== ( $ecommerceTemplate ?? null ) )
        <x-dynamic-component :component="\ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontTemplates::COMPONENT" :slug="$ecommerceTemplate" />
    @else
        <div class="flex flex-col gap-6">
            <h1 class="text-3xl font-bold">{{ __( 'Checkout' ) }}</h1>

            <livewire:artisanpack-ecommerce-storefront-checkout />
        </div>
    @endif
@endsection
