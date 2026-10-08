{{--
    Checkout step: address. Shipping (with "billing is the same"), or
    billing only for carts that don't ship. Signed-in shoppers pick a saved
    address or enter a new one.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceNewAddress = [] === $savedAddresses || 'new' === ( $ships ? $savedShipping : $savedBilling ) )
<form wire:submit="saveAddress" novalidate class="flex flex-col gap-6" data-checkout-address-form>
    @if ( $ships )
        <div class="flex flex-col gap-4" data-checkout-shipping-address>
            @if ( [] !== $savedAddresses )
                <x-artisanpack-radio
                    id="ec-checkout-saved-shipping"
                    :label="__( 'Ship to' )"
                    :options="$savedAddresses"
                    wire:model.live="savedShipping"
                    data-checkout-saved="shipping"
                />
            @endif

            @if ( [] === $savedAddresses || 'new' === $savedShipping )
                <x-artisanpack-ec-address-form
                    model="shipping"
                    :legend="__( 'Shipping address' )"
                    :country="$shipping['country_code'] ?? null"
                    section="shipping"
                    require-name
                />
            @endif
        </div>

        <x-artisanpack-checkbox :label="__( 'Billing address is the same as shipping' )" wire:model.live="billingSameAsShipping" data-checkout-billing-same />
    @else
        <p class="text-sm text-base-content/70" data-checkout-digital-only>{{ __( 'Nothing in your order ships, so we only need your billing address.' ) }}</p>
    @endif

    @if ( ! $ships || ! $billingSameAsShipping )
        <div class="flex flex-col gap-4" data-checkout-billing-address>
            @if ( [] !== $savedAddresses )
                <x-artisanpack-radio
                    id="ec-checkout-saved-billing"
                    :label="__( 'Bill to' )"
                    :options="$savedAddresses"
                    wire:model.live="savedBilling"
                    data-checkout-saved="billing"
                />
            @endif

            @if ( [] === $savedAddresses || 'new' === $savedBilling )
                <x-artisanpack-ec-address-form
                    model="billing"
                    :legend="__( 'Billing address' )"
                    :country="$billing['country_code'] ?? null"
                    section="billing"
                    require-name
                />
            @endif
        </div>
    @endif

    @if ( $ecommerceNewAddress && ! $isGuest )
        <x-artisanpack-checkbox :label="__( 'Save this address to my address book' )" wire:model="saveToAddressBook" data-checkout-save-address />
    @endif

    <div>
        <x-artisanpack-button type="submit" :label="__( 'Continue' )" color="primary" spinner="saveAddress" wire:loading.attr="disabled" wire:target="saveAddress" data-checkout-address-submit />
    </div>
</form>
