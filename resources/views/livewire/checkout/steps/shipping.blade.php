{{--
    Checkout step: shipping (#139). "Shipping to" the cart's address on one
    line, with "Change address" (back to the address step). Then the rates
    quoted for it as radio cards, one per row: the name and its delivery
    estimate, with the price (or "Free") at the end. The chosen one is
    applied straight away, so the order summary's totals follow it. Then
    "Continue". With no rates for the address, a warning Alert offers
    "Change address" instead.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceRates   = $rates ?? [];
    $ecommerceShipTo  = is_array( $cart?->shipping_address ) ? $cart->shipping_address : [];
    $ecommerceShipTo  = implode( ', ', array_filter( [
        trim( (string) ( $ecommerceShipTo['address1'] ?? '' ) ),
        trim( (string) ( $ecommerceShipTo['city'] ?? '' ) ),
        trim( ( (string) ( ( $ecommerceShipTo['region_code'] ?? '' ) ?: ( $ecommerceShipTo['region'] ?? '' ) ) ) . ' ' . (string) ( $ecommerceShipTo['postal_code'] ?? '' ) ),
    ], static fn ( string $part ): bool => '' !== $part ) );
    [ $ecommerceShipToBefore, $ecommerceShipToAfter ] = explode( ':address', __( 'Shipping to :address' ), 2 ) + [ 1 => '' ];
@endphp
<form wire:submit="saveShipping" novalidate class="ec-checkout-form" data-checkout-shipping-form>
    @if ( '' !== $ecommerceShipTo )
        <div class="ec-checkout-ship-to" data-checkout-ship-to>
            <p class="ec-checkout-ship-to__text">{{ $ecommerceShipToBefore }}<span class="ec-checkout-ship-to__address">{{ $ecommerceShipTo }}</span>{{ $ecommerceShipToAfter }}</p>
            <x-artisanpack-button variant="link" size="sm" wire:click="goTo( 'address' )" data-checkout-change-address>{{ __( 'Change address' ) }}</x-artisanpack-button>
        </div>
    @endif

    @if ( null !== $shippingNotice )
        <x-artisanpack-alert color="warning" icon="triangle-alert" :title="$shippingNotice" data-checkout-shipping-notice />
    @endif

    @if ( null !== $rates && [] === $ecommerceRates )
        <x-artisanpack-alert
            color="warning"
            icon="truck"
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
                variant="cards"
                class="ec-checkout-choices ec-checkout-choices--priced"
                :options="collect( $ecommerceRates )->map( fn ( array $rate ): array => [
                    'value'       => $rate['id'],
                    'label'       => $rate['label'],
                    'description' => $rate['estimate'],
                    'meta'        => $rate['amount'] > 0 ? \ArtisanPackUI\Ecommerce\Support\MoneyFormatter::format( $rate['amount'], $rate['currency'] ) : __( 'Free' ),
                ] )->all()"
                wire:model.live="shippingRate"
                data-checkout-rates
            />
        </div>

        <div class="ec-checkout-form__actions">
            <x-artisanpack-button type="submit" color="primary" class="ec-checkout-form__submit" wire:loading.attr="disabled" wire:target="saveShipping, shippingRate" data-checkout-shipping-submit>{{ __( 'Continue' ) }}</x-artisanpack-button>
        </div>
    @endif
</form>
