{{--
    The catalog page (#130): the design-system PageHeader (breadcrumbs
    "Shop › All products", the "All products" title and the
    `catalog.description` line), then the product listing (Catalog\Index).
    With a saved `product-archive` template, the template renders instead.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@push( 'head' )
    @include( 'ecommerce-storefront::partials.seo' )
@endpush

@section( 'title', $ecommerceSeo->title( __( 'Shop' ) ) )

@section( 'content' )
    @if ( null !== ( $ecommerceTemplate ?? null ) )
        <x-dynamic-component :component="\ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontTemplates::COMPONENT" :slug="$ecommerceTemplate" />
    @else
        @php
            $ecommerceDescription = config( 'artisanpack.ecommerce-storefront-livewire.catalog.description' );
            $ecommerceDescription = is_string( $ecommerceDescription ) && '' !== trim( $ecommerceDescription ) ? $ecommerceDescription : null;
        @endphp
        <div class="ec-page" data-catalog-page>
            <x-artisanpack-page-header :title="__( 'All products' )" :description="$ecommerceDescription" :level="1" class="ec-page__header">
                <x-slot:breadcrumbs>
                    <x-artisanpack-breadcrumbs
                        :items="[ [ 'label' => __( 'Shop' ), 'link' => route( 'artisanpack.ecommerce.storefront.catalog' ) ], [ 'label' => __( 'All products' ) ] ]"
                        :label="__( 'Breadcrumb' )"
                        :navigate="false"
                    />
                </x-slot:breadcrumbs>
            </x-artisanpack-page-header>

            <livewire:artisanpack-ecommerce-storefront-catalog />
        </div>
    @endif
@endsection
