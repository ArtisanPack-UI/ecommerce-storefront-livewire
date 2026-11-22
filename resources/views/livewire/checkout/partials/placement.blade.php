{{--
    An order that is placed but not paid yet (#142): the payment needed
    confirming, or was declined.

    One centered Card: a warning clock icon tile, "Finish paying for order
    {number}" (the page's h1 on the checkout page, else an h2), a line saying
    the order is saved, an error Alert with what happened, the order's items,
    shipping and total as a meta list, then the payment driver for the
    order's own payment. Confirming it finishes the order.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommercePlacementItems   = null === $placedOrderModel ? 0 : (int) $placedOrderModel->items->sum( 'quantity' );
    $ecommercePlacementRate    = null === $placedOrderModel ? null : ( $placedOrderModel->meta['shipping_rate']['label'] ?? null );
    $ecommercePlacementHeading = $showTitle ? 'h1' : 'h2';
@endphp
<div class="ec-placement" data-checkout-placement="{{ $placement['order'] }}">
    @include( 'ecommerce-storefront::livewire.checkout.partials.error-summary' )

    <x-artisanpack-card class="ec-placement__card" role="region" aria-labelledby="ec-checkout-placement-heading">
        <x-artisanpack-icon name="clock" variant="tile" color="warning" size="lg" />

        <div class="ec-placement__intro">
            <{{ $ecommercePlacementHeading }}
                id="ec-checkout-placement-heading"
                tabindex="-1"
                class="ec-placement__title"
                @if ( $focusStep ) x-init="$nextTick( () => $el.focus() )" @endif
            >
                {{ __( 'Finish paying for order :number', [ 'number' => $placement['number'] ] ) }}
            </{{ $ecommercePlacementHeading }}>

            <p class="ec-placement__lead">{{ __( 'Your order is saved, and we\'ll hold it while you finish paying.' ) }}</p>
        </div>

        @if ( null !== $paymentProblem )
            <x-artisanpack-alert color="error" icon="circle-alert" :title="$paymentProblem" data-checkout-payment-problem />
        @endif

        @if ( null !== $placedOrderModel )
            <dl class="ec-placement__meta" data-checkout-summary>
                <div class="ec-placement__meta-row">
                    <dt>{{ __( 'Items' ) }}</dt>
                    <dd data-checkout-placement-items>{{ $ecommercePlacementItems }}</dd>
                </div>

                @if ( is_string( $ecommercePlacementRate ) && '' !== $ecommercePlacementRate )
                    <div class="ec-placement__meta-row">
                        <dt>{{ __( 'Shipping' ) }}</dt>
                        <dd data-checkout-placement-shipping>
                            {{ $ecommercePlacementRate }}<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span>@if ( (int) $placedOrderModel->shipping_amount > 0 )<x-artisanpack-ec-money :amount="(int) $placedOrderModel->shipping_amount" :currency="(string) $placedOrderModel->currency" />@else{{ __( 'Free' ) }}@endif
                        </dd>
                    </div>
                @endif

                <div class="ec-placement__meta-row ec-placement__meta-row--total">
                    <dt>{{ __( 'Total' ) }}</dt>
                    <dd><x-artisanpack-ec-money :amount="(int) $placedOrderModel->total_amount" :currency="(string) $placedOrderModel->currency" data-cart-total /></dd>
                </div>
            </dl>
        @endif

        <div class="ec-placement__payment" data-field="gateway">
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
                <x-artisanpack-alert color="warning" icon="triangle-alert" :title="__( 'This payment can\'t be finished here' )" :description="__( 'Please contact the store to complete your order.' )" data-checkout-placement-unavailable />
            @endif

            @error( 'gateway' )
                <p class="ec-placement__error">
                    <x-artisanpack-icon name="circle-alert" size="sm" />
                    <span>{{ $message }}</span>
                </p>
            @enderror
        </div>
    </x-artisanpack-card>
</div>
