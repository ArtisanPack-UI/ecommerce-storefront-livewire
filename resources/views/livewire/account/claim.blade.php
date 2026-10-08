{{--
    Claiming guest orders. See Livewire\Account\Claim.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex max-w-xl flex-col gap-6" data-account-claim>
    <p>{{ __( 'Ordered before you had an account? Enter the order number and the postcode it was shipped to, and we\'ll add your guest orders placed with this email address to your account.' ) }}</p>

    <div role="alert" aria-live="assertive">
        @if ( null !== $failure )
            <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-error alert-soft" :title="$failure" data-account-claim-failure />
        @endif
    </div>

    <form wire:submit="claim" class="flex flex-col gap-4" novalidate data-account-claim-form>
        <x-artisanpack-input id="orderNumber" :label="__( 'Order number' )" autocomplete="off" required wire:model="orderNumber" />
        <x-artisanpack-input id="postalCode" :label="__( 'Shipping postcode' )" autocomplete="postal-code" required wire:model="postalCode" />

        <div>
            <x-artisanpack-button type="submit" :label="__( 'Claim orders' )" color="primary" spinner="claim" wire:loading.attr="disabled" wire:target="claim" data-account-claim-submit />
        </div>
    </form>
</div>
