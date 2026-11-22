{{--
    Checkout step: address (#138). "Ship to" and the address form, then
    "Billing address is the same as shipping" (checked); unchecked, "Bill
    to" and a second form. A cart that doesn't ship asks for the billing
    address only, and says why. A signed-in shopper with saved addresses
    picks one from radio cards (or "Use a new address", which opens the
    form), and can save a new one to their address book. Then "Continue".
    Each field shows its own error, and the step's error summary links to
    it.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceHasSaved   = [] !== $savedAddresses;
    $ecommerceNewAddress = ! $ecommerceHasSaved || 'new' === ( $ships ? $savedShipping : $savedBilling );
@endphp
<form wire:submit="saveAddress" novalidate class="ec-checkout-form ec-checkout-form--loose" data-checkout-address-form>
    @if ( $ships )
        <div class="ec-checkout-form__group" data-checkout-shipping-address>
            @if ( $ecommerceHasSaved )
                <x-artisanpack-radio-group
                    id="ec-checkout-saved-shipping"
                    :label="__( 'Ship to' )"
                    variant="cards"
                    class="ec-checkout-choices"
                    :options="\ArtisanPackUI\EcommerceStorefrontLivewire\Support\ControlOptions::from( $savedAddresses )"
                    wire:model.live="savedShipping"
                    data-checkout-saved="shipping"
                />
            @endif

            @if ( ! $ecommerceHasSaved || 'new' === $savedShipping )
                <x-artisanpack-ec-address-form
                    model="shipping"
                    :legend="$ecommerceHasSaved ? __( 'Shipping address' ) : __( 'Ship to' )"
                    :country="$shipping['country_code'] ?? null"
                    section="shipping"
                    require-name
                />
            @endif
        </div>

        <x-artisanpack-checkbox :label="__( 'Billing address is the same as shipping' )" wire:model.live="billingSameAsShipping" data-checkout-billing-same />
    @else
        <p class="ec-checkout-form__note" data-checkout-digital-only>{{ __( 'Nothing in your order ships, so we only need your billing address.' ) }}</p>
    @endif

    @if ( ! $ships || ! $billingSameAsShipping )
        <div class="ec-checkout-form__group" data-checkout-billing-address>
            @if ( $ecommerceHasSaved )
                <x-artisanpack-radio-group
                    id="ec-checkout-saved-billing"
                    :label="__( 'Bill to' )"
                    variant="cards"
                    class="ec-checkout-choices"
                    :options="\ArtisanPackUI\EcommerceStorefrontLivewire\Support\ControlOptions::from( $savedAddresses )"
                    wire:model.live="savedBilling"
                    data-checkout-saved="billing"
                />
            @endif

            @if ( ! $ecommerceHasSaved || 'new' === $savedBilling )
                <x-artisanpack-ec-address-form
                    model="billing"
                    :legend="$ecommerceHasSaved ? __( 'Billing address' ) : __( 'Bill to' )"
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

    <div class="ec-checkout-form__actions">
        <x-artisanpack-button type="submit" color="primary" class="ec-checkout-form__submit" wire:loading.attr="disabled" wire:target="saveAddress" data-checkout-address-submit>{{ __( 'Continue' ) }}</x-artisanpack-button>
    </div>
</form>
