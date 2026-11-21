{{--
    Search box with live suggestions (#92). See SearchBox.

    Wraps the header search Livewire component; up to 440px wide (the
    `ec-search-box` class), and attributes such as `class` land here.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'ec-search-box' ] ) }} data-search-box>
    @livewire( 'artisanpack-ecommerce-storefront-search-box', [ 'inputId' => $inputId ], key( 'search-box-' . $inputId ) )
</div>
