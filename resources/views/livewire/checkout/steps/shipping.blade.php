{{--
    Checkout step: shipping. The rates quoted for the cart's address; the
    chosen one is applied straight away so the totals show it.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceRates = $rates ?? [] )
<form wire:submit="saveShipping" novalidate class="flex flex-col gap-4" data-checkout-shipping-form>
    @if ( null !== $shippingNotice )
        <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft" :title="$shippingNotice" data-checkout-shipping-notice />
    @endif

    @if ( null !== $rates && [] === $ecommerceRates )
        <x-artisanpack-alert
            icon="o-truck"
            class="alert-warning alert-soft"
            :title="__( 'We can\'t ship to this address' )"
            :description="__( 'Check the address, or use a different one.' )"
            data-checkout-no-rates
        >
            <x-slot:actions>
                <x-artisanpack-button :label="__( 'Change address' )" class="btn-sm" wire:click="goTo( 'address' )" data-checkout-change-address />
            </x-slot:actions>
        </x-artisanpack-alert>
    @elseif ( [] !== $ecommerceRates )
        <div data-field="shippingRate">
            <x-artisanpack-radio
                id="ec-checkout-shipping-rate"
                :label="__( 'Shipping option' )"
                :options="collect( $ecommerceRates )->map( fn ( array $rate ): array => [
                    'id'   => $rate['id'],
                    'name' => $rate['label'],
                    'hint' => \ArtisanPackUI\Ecommerce\Support\MoneyFormatter::format( $rate['amount'], $rate['currency'] ) . ( null === $rate['estimate'] ? '' : ' · ' . $rate['estimate'] ),
                ] )->all()"
                wire:model.live="shippingRate"
                data-checkout-rates
            />
        </div>

        <div>
            <x-artisanpack-button type="submit" :label="__( 'Continue' )" color="primary" spinner="saveShipping" wire:loading.attr="disabled" wire:target="saveShipping, shippingRate" data-checkout-shipping-submit />
        </div>
    @endif
</form>
