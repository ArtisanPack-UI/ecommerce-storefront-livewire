{{--
    The search page.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', '' === $term ? __( 'Search' ) : __( 'Search results for ":term"', [ 'term' => $term ] ) )

@section( 'content' )
    @if ( null !== ( $ecommerceTemplate ?? null ) )
        <x-dynamic-component :component="\ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontTemplates::COMPONENT" :slug="$ecommerceTemplate" />
    @else
        <div class="flex flex-col gap-6">
            <h1 class="text-3xl font-bold">{{ __( 'Search' ) }}</h1>

            <livewire:artisanpack-ecommerce-storefront-search />
        </div>
    @endif
@endsection
