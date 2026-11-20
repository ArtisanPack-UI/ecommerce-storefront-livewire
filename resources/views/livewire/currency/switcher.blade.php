{{--
    The currency switcher. See Currency\Switcher.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-ecommerce-currency-switcher>
    @if ( $show )
        <x-artisanpack-select
            placeholder=""
            id="ecommerce-currency-switcher"
            :label="__( 'Currency' )"
            :options="\ArtisanPackUI\EcommerceStorefrontLivewire\Support\ControlOptions::from( $options )"
            wire:model.live="currency"
            wire:loading.attr="disabled"
            size="sm"
        />
    @endif
</div>
