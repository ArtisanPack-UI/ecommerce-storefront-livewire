{{--
    Search box with live suggestions. See SearchBox.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div {{ $attributes }} data-search-box>
    @livewire( 'artisanpack-ecommerce-storefront-search-box', [ 'inputId' => $inputId ], key( 'search-box-' . $inputId ) )
</div>
