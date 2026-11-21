{{--
    The search page (#133): the search component with its title.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( '' === $term ? __( 'Search' ) : __( 'Search results for ":term"', [ 'term' => $term ] ) ) )

@section( 'content' )
    @if ( null !== ( $ecommerceTemplate ?? null ) )
        <x-dynamic-component :component="\ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontTemplates::COMPONENT" :slug="$ecommerceTemplate" />
    @else
        <livewire:artisanpack-ecommerce-storefront-search :show-title="true" />
    @endif
@endsection
