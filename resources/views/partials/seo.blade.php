{{--
    The page's search-engine tags (spec §12): title for social cards,
    description, canonical URL, robots, Open Graph, and JSON-LD. See
    Support\StorefrontSeo.

    Every storefront page pushes this onto the layout's `head` stack:

        @push( 'head' )
            @include( 'ecommerce-storefront::partials.seo' )
        @endpush

    When artisanpack-ui/seo is installed the JSON-LD goes to its schema
    collector instead (printed by the layout's `<x-seo:schema />`).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceSeoInstance = app( \ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontSeo::class );
    $ecommerceSeoMeta     = $ecommerceSeoInstance->meta();
@endphp
@if ( null !== $ecommerceSeoMeta['description'] )
    <meta name="description" content="{{ $ecommerceSeoMeta['description'] }}">
@endif
@if ( null !== $ecommerceSeoMeta['robots'] )
    <meta name="robots" content="{{ $ecommerceSeoMeta['robots'] }}">
@endif
@if ( null !== $ecommerceSeoMeta['canonical'] )
    <link rel="canonical" href="{{ $ecommerceSeoMeta['canonical'] }}">
@endif
@if ( null !== $ecommerceSeoMeta['title'] && null === $ecommerceSeoMeta['robots'] )
    <meta property="og:title" content="{{ $ecommerceSeoMeta['title'] }}">
    <meta property="og:type" content="{{ $ecommerceSeoMeta['type'] }}">
    <meta property="og:site_name" content="{{ config( 'app.name' ) }}">
    @if ( null !== $ecommerceSeoMeta['description'] )
        <meta property="og:description" content="{{ $ecommerceSeoMeta['description'] }}">
    @endif
    @if ( null !== $ecommerceSeoMeta['canonical'] )
        <meta property="og:url" content="{{ $ecommerceSeoMeta['canonical'] }}">
    @endif
    @if ( null !== $ecommerceSeoMeta['image'] )
        <meta property="og:image" content="{{ $ecommerceSeoMeta['image'] }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
@endif
@foreach ( $ecommerceSeoInstance->inlineSchemas() as $ecommerceSchema )
    <script type="application/ld+json">{!! \ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontSeo::json( $ecommerceSchema ) !!}</script>
@endforeach
