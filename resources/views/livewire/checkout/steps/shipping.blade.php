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
        <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" :title="$shippingNotice" data-checkout-shipping-notice />
    @endif

    @if ( null !== $rates && [] === $ecommerceRates )
        <x-artisanpack-alert color="warning"
            icon="o-truck"
           
            :title="__( 'We can\'t ship to this address' )"
            :description="__( 'Check the address, or use a different one.' )"
            data-checkout-no-rates
        >
            <x-slot:actions>
                <x-artisanpack-button variant="outline" color="neutral" size="sm" wire:click="goTo( 'address' )" data-checkout-change-address>{{ __( 'Change address' ) }}</x-artisanpack-button>
            </x-slot:actions>
        </x-artisanpack-alert>
    @elseif ( [] !== $ecommerceRates )
        <div data-field="shippingRate">
            <x-artisanpack-radio-group
                id="ec-checkout-shipping-rate"
                :label="__( 'Shipping option' )"
                :options="collect( $ecommerceRates )->map( fn ( array $rate ): array => [
                    'value'       => $rate['id'],
                    'label'       => $rate['label'],
                    'description' => \ArtisanPackUI\Ecommerce\Support\MoneyFormatter::format( $rate['amount'], $rate['currency'] ) . ( null === $rate['estimate'] ? '' : ' · ' . $rate['estimate'] ),
                ] )->all()"
                wire:model.live="shippingRate"
                data-checkout-rates
            />
        </div>

        <div>
            <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" wire:target="saveShipping, shippingRate" data-checkout-shipping-submit>{{ __( 'Continue' ) }}</x-artisanpack-button>
        </div>
    @endif
</form>
