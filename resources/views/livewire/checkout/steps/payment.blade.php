{{--
    Checkout step: payment. The gateway choice (made for the shopper when
    there is only one) and the gateway's payment driver.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-4" data-checkout-payment>
    @if ( ! $paymentRequired )
        <p data-checkout-payment-free>{{ __( 'Your order is free, so there\'s nothing to pay.' ) }}</p>

        <div>
            <x-artisanpack-button :label="__( 'Continue' )" color="primary" wire:click="continueWithoutPayment" data-checkout-payment-continue />
        </div>
    @elseif ( $needsAccount )
        <x-artisanpack-alert
            icon="o-lock-closed"
            class="alert-info alert-soft"
            :title="__( 'Sign in or create an account to pay' )"
            :description="__( 'This store needs an account to place an order. You\'ll come back here afterwards.' )"
            data-checkout-payment-account-needed
        >
            <x-slot:actions>
                @if ( null !== $loginUrl )
                    <x-artisanpack-button :label="__( 'Sign in' )" :link="$loginUrl" class="btn-sm" color="primary" />
                @endif

                @if ( null !== $registerUrl )
                    <x-artisanpack-button :label="__( 'Create an account' )" :link="$registerUrl" class="btn-sm" />
                @endif
            </x-slot:actions>
        </x-artisanpack-alert>
    @elseif ( [] === $gateways )
        <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft" :title="__( 'No payment methods are available' )" :description="__( 'Please contact the store to complete your order.' )" data-checkout-no-gateways />
    @else
        <div data-field="gateway">
            @if ( count( $gateways ) > 1 )
                <x-artisanpack-radio
                    id="ec-checkout-gateway"
                    :label="__( 'Payment method' )"
                    :options="collect( $gateways )->map( fn ( string $label, string $key ): array => [ 'id' => $key, 'name' => $label ] )->values()->all()"
                    wire:model.live="gateway"
                    data-checkout-gateways
                />
            @else
                <p class="font-semibold" data-checkout-gateway-single>{{ __( 'Pay with :gateway', [ 'gateway' => reset( $gateways ) ] ) }}</p>

                @error( 'gateway' )
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror
            @endif
        </div>

        <div wire:loading wire:target="gateway" class="text-sm text-base-content/70">{{ __( 'Setting up your payment…' ) }}</div>

        @if ( null !== $payment )
            <div wire:key="checkout-payment-{{ $payment['reference'] }}" wire:loading.remove wire:target="gateway" data-checkout-payment-driver="{{ $payment['config']['driver'] ?? '' }}">
                @livewire( $payment['component'], [
                    'config'       => $payment['config'],
                    'gateway'      => $payment['gateway'],
                    'gatewayLabel' => $payment['label'],
                    'reference'    => $payment['reference'],
                ], key( 'checkout-payment-driver-' . $payment['reference'] ) )
            </div>
        @endif
    @endif
</div>
