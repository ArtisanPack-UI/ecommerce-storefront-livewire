{{--
    The redirect payment driver. See RedirectDriver.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-3" data-payment-driver="redirect">
    @if ( $available )
        <p class="text-sm text-base-content/70">{{ __( 'You\'ll go to :gateway to pay, then come back here to finish your order.', [ 'gateway' => $label ] ) }}</p>

        <div>
            <x-artisanpack-button
                color="primary"
                trailing-icon="o-arrow-top-right-on-square"
                wire:click="confirm"
                wire:loading.attr="disabled"
                wire:target="confirm"
                data-payment-redirect>{{ __( 'Continue to :gateway', [ 'gateway' => $label ] ) }}</x-artisanpack-button>
        </div>
    @else
        <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" :title="__( 'This payment method isn\'t available.' )" data-payment-unavailable />
    @endif
</div>
