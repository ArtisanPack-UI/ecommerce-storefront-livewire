{{--
    A tag page: the products with the tag.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( $tag->name ) )

@section( 'content' )
    @if ( null !== ( $ecommerceTemplate ?? null ) )
        <x-dynamic-component :component="\ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontTemplates::COMPONENT" :slug="$ecommerceTemplate" />
    @else
        <livewire:artisanpack-ecommerce-storefront-tag-show :tag="$tag" />
    @endif
@endsection
