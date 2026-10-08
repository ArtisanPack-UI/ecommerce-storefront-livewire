{{--
    An order that is placed but not paid yet (the payment needed
    confirming, or was declined): what happened, and the payment driver for
    the order's own payment. Confirming it finishes the order.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start" data-checkout-placement="{{ $placement['order'] }}">
    <div class="flex flex-col gap-6">
        @include( 'ecommerce-storefront::livewire.checkout.partials.error-summary' )

        <section class="flex flex-col gap-4 rounded-box border border-primary p-5" aria-labelledby="ec-checkout-placement-heading">
            <h2
                id="ec-checkout-placement-heading"
                tabindex="-1"
                class="text-lg font-semibold focus:outline-none"
                @if ( $focusStep ) x-init="$nextTick( () => $el.focus() )" @endif
            >
                {{ __( 'Finish paying for order :number', [ 'number' => $placement['number'] ] ) }}
            </h2>

            @if ( null !== $paymentProblem )
                <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft" :title="$paymentProblem" data-checkout-payment-problem />
            @endif

            <div data-field="gateway">
                @if ( null !== $payment )
                    <div wire:key="checkout-placement-payment-{{ $payment['reference'] }}" data-checkout-payment-driver="{{ $payment['config']['driver'] ?? '' }}">
                        @livewire( $payment['component'], [
                            'config'       => $payment['config'],
                            'gateway'      => $payment['gateway'],
                            'gatewayLabel' => $payment['label'],
                            'reference'    => $payment['reference'],
                        ], key( 'checkout-placement-driver-' . $payment['reference'] ) )
                    </div>
                @else
                    <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft" :title="__( 'This payment can\'t be finished here' )" :description="__( 'Please contact the store to complete your order.' )" data-checkout-placement-unavailable />
                @endif

                @error( 'gateway' )
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>
        </section>
    </div>

    @if ( null !== $placedOrderModel )
        <aside class="flex flex-col gap-2 rounded-box border border-base-content/10 p-5" aria-labelledby="ec-checkout-placement-summary" data-checkout-summary>
            <h2 id="ec-checkout-placement-summary" class="text-xl font-bold">{{ __( 'Order summary' ) }}</h2>

            <p class="text-sm text-base-content/70">
                {{ trans_choice( ':count item|:count items', (int) $placedOrderModel->items->sum( 'quantity' ), [ 'count' => (int) $placedOrderModel->items->sum( 'quantity' ) ] ) }}
            </p>

            <p class="flex justify-between gap-4 border-t border-base-content/10 pt-2 text-lg font-bold">
                <span>{{ __( 'Total' ) }}</span>
                <x-artisanpack-ec-money :amount="(int) $placedOrderModel->total_amount" :currency="(string) $placedOrderModel->currency" data-cart-total />
            </p>
        </aside>
    @endif
</div>
