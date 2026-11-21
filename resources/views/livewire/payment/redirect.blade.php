{{--
    The redirect payment driver (#111). See RedirectDriver.

    A Card: an external-link icon tile beside "Pay with {gateway}" and one
    line saying the shopper finishes paying on the gateway's site and comes
    back, then a full-width solid neutral "Continue to {gateway}" with a
    trailing arrow (mirrored in right-to-left layouts). Without a usable
    redirect URL, a warning Alert says the method isn't available.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-redirect" data-payment-driver="redirect">
    @if ( $available )
        <x-artisanpack-card class="ec-redirect__card">
            <div class="ec-redirect__intro">
                <x-artisanpack-icon name="external-link" variant="tile" />

                <div class="ec-redirect__text">
                    <p class="ec-redirect__title">{{ __( 'Pay with :gateway', [ 'gateway' => $label ] ) }}</p>
                    <p class="ec-redirect__description">{{ __( 'You\'ll finish paying on :gateway\'s site, then come back here.', [ 'gateway' => $label ] ) }}</p>
                </div>
            </div>

            <x-artisanpack-button
                color="neutral"
                trailing-icon="arrow-right"
                class="ec-redirect__continue"
                wire:click="confirm"
                wire:loading.attr="disabled"
                wire:target="confirm"
                data-payment-redirect>{{ __( 'Continue to :gateway', [ 'gateway' => $label ] ) }}</x-artisanpack-button>
        </x-artisanpack-card>
    @else
        <x-artisanpack-alert color="warning" icon="triangle-alert" :title="__( 'This payment method isn\'t available.' )" data-payment-unavailable />
    @endif
</div>
