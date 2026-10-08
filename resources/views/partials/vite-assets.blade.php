{{--
    Loads the host's Vite entries (the Tailwind build that styles the
    storefront), filterable with `ap.ecommerceStorefrontLivewire.layout.viteEntries`;
    return an empty array to load nothing. Skipped when there is neither a
    Vite build nor a running dev server.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceStorefrontViteEntries = (array) applyFilters( 'ap.ecommerceStorefrontLivewire.layout.viteEntries', [ 'resources/css/app.css', 'resources/js/app.js' ] );
    $ecommerceStorefrontLoadsVite   = [] !== $ecommerceStorefrontViteEntries
        && ( \Illuminate\Support\Facades\Vite::isRunningHot() || is_file( public_path( 'build/manifest.json' ) ) );
@endphp
@if ( $ecommerceStorefrontLoadsVite )
    @vite( $ecommerceStorefrontViteEntries )
@endif
