{{--
    The currency switcher. See Currency\Switcher.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-ecommerce-currency-switcher>
    @if ( $show )
        <x-artisanpack-select
            id="ecommerce-currency-switcher"
            :label="__( 'Currency' )"
            :options="$options"
            wire:model.live="currency"
            wire:loading.attr="disabled"
            class="select-sm"
            inline
        />
    @endif
</div>
