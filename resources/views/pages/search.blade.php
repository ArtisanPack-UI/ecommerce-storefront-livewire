{{--
    The search page.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@extends( $ecommerceStorefrontLayout )

@section( 'title', '' === $term ? __( 'Search' ) : __( 'Search results for ":term"', [ 'term' => $term ] ) )

@section( 'content' )
    <div class="flex flex-col gap-6">
        <h1 class="text-3xl font-bold">{{ __( 'Search' ) }}</h1>

        <livewire:artisanpack-ecommerce-storefront-search />
    </div>
@endsection
