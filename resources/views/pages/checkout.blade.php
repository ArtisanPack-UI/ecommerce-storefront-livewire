{{--
    The checkout (#136): the checkout component with its title, under the
    layout's slim secure header (`storefront-chrome` is `checkout`).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( __( 'Checkout' ) ) )

@section( 'storefront-chrome', 'checkout' )

@section( 'content' )
    @if ( null !== ( $ecommerceTemplate ?? null ) )
        <x-dynamic-component :component="\ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontTemplates::COMPONENT" :slug="$ecommerceTemplate" />
    @else
        <livewire:artisanpack-ecommerce-storefront-checkout :show-title="true" />
    @endif
@endsection
