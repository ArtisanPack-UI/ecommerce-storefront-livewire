{{--
    Checkout step: payment, the last step. The gateway choice (made for the
    shopper when there is only one) and the gateway's payment driver, which
    gets the one-time action token that places the order: confirming the
    payment places it. A free order, or one whose payment is already
    confirmed, is placed with "Place order".

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-4" data-checkout-payment>
    @if ( ! $paymentRequired || $paymentConfirmed )
        @if ( $paymentRequired )
            <x-artisanpack-alert color="success" icon="o-check-circle" :title="__( 'Your payment is confirmed. Place your order to finish.' )" data-checkout-payment-confirmed />
        @else
            <p data-checkout-payment-free>{{ __( 'Your order is free, so there\'s nothing to pay.' ) }}</p>
        @endif

        <div>
            <x-artisanpack-button
                size="lg"
                color="primary"
                class="w-full sm:w-auto"
                wire:click="placeOrder( '{{ $placeOrderToken }}' )"
                wire:loading.attr="disabled"
                wire:target="placeOrder"
                data-checkout-place-order>{{ __( 'Place order' ) }}</x-artisanpack-button>
        </div>
    @elseif ( $needsAccount )
        <x-artisanpack-alert color="info"
            icon="o-lock-closed"
           
            :title="__( 'Sign in or create an account to pay' )"
            :description="__( 'This store needs an account to place an order. You\'ll come back here afterwards.' )"
            data-checkout-payment-account-needed
        >
            <x-slot:actions>
                @if ( null !== $loginUrl )
                    <x-artisanpack-button size="sm" :href="$loginUrl" color="primary">{{ __( 'Sign in' ) }}</x-artisanpack-button>
                @endif

                @if ( null !== $registerUrl )
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" :href="$registerUrl">{{ __( 'Create an account' ) }}</x-artisanpack-button>
                @endif
            </x-slot:actions>
        </x-artisanpack-alert>
    @elseif ( [] === $gateways )
        <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" :title="__( 'No payment methods are available' )" :description="__( 'Please contact the store to complete your order.' )" data-checkout-no-gateways />
    @else
        <div data-field="gateway">
            @if ( count( $gateways ) > 1 )
                <x-artisanpack-radio-group
                    id="ec-checkout-gateway"
                    :label="__( 'Payment method' )"
                    :options="collect( $gateways )->map( fn ( string $label, string $key ): array => [ 'value' => $key, 'label' => $label ] )->values()->all()"
                    wire:model.live="gateway"
                    data-checkout-gateways
                />
            @else
                <p class="font-semibold" data-checkout-gateway-single>{{ __( 'Pay with :gateway', [ 'gateway' => reset( $gateways ) ] ) }}</p>

                @error( 'gateway' )
                    <p class="text-sm text-[color-mix(in_oklab,var(--color-error)_55%,var(--color-base-content))]">{{ $message }}</p>
                @enderror
            @endif
        </div>

        <div wire:loading wire:target="gateway" class="text-sm text-base-content/70">{{ __( 'Setting up your payment…' ) }}</div>

        @if ( null !== $payment )
            <p class="text-sm text-base-content/70" data-checkout-payment-places-order>{{ __( 'Your order is placed as soon as your payment is confirmed.' ) }}</p>

            <div wire:key="checkout-payment-{{ $payment['reference'] }}" wire:loading.remove wire:target="gateway" data-checkout-payment-driver="{{ $payment['config']['driver'] ?? '' }}">
                @livewire( $payment['component'], [
                    'config'       => $payment['config'],
                    'gateway'      => $payment['gateway'],
                    'gatewayLabel' => $payment['label'],
                    'reference'    => $payment['reference'],
                    'token'        => (string) $placeOrderToken,
                ], key( 'checkout-payment-driver-' . $payment['reference'] ) )
            </div>
        @endif
    @endif

    <div data-field="payment">
        @error( 'payment' )
            <p class="text-sm text-[color-mix(in_oklab,var(--color-error)_55%,var(--color-base-content))]" data-checkout-payment-error>{{ $message }}</p>
        @enderror
    </div>
</div>
