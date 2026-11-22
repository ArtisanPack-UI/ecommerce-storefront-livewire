{{--
    Checkout step: payment (#141), the last step.

    "Payment method": the gateways as radio cards, each with its name, an
    optional description and icon (the
    `ap.ecommerceStorefrontLivewire.checkout.paymentMethod` filter). The
    chosen card opens to hold the gateway's payment driver, which gets the
    one-time action token that places the order: confirming the payment
    places it, as the line under the cards says. With one gateway it is
    chosen for the shopper, and its card has no radio.

    The cards follow the design-system RadioGroup's markup contract
    (`fieldset.apui-radio-group[data-variant="cards"]`, its label, items,
    box, control and indicator parts) rather than the component, because a
    card holds the driver's own form, and a form can't sit inside the
    radio's `label`. So the radio's label is the card's header, and the
    card around it carries the selected styles.

    A free order is placed with "Place order", and one whose payment is
    already confirmed with "Place order · {total}". A guest who needs an account is asked to sign
    in or register; with no gateway, a warning says to contact the store.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommercePlaceOrder = null === $totals || $totals['total'] <= 0
        ? __( 'Place order' )
        : __( 'Place order · :total', [ 'total' => \ArtisanPackUI\Ecommerce\Support\MoneyFormatter::format( $totals['total'], $totals['currency'] ) ] );
    $ecommerceSingle = 1 === count( $paymentMethods );
@endphp
<div class="ec-checkout-form" data-checkout-payment>
    @if ( ! $paymentRequired || $paymentConfirmed )
        @if ( $paymentRequired )
            <x-artisanpack-alert color="success" icon="circle-check" :title="__( 'Your payment is confirmed. Place your order to finish.' )" data-checkout-payment-confirmed />
        @else
            <p class="ec-checkout-form__note" data-checkout-payment-free>{{ __( 'Your order is free, so there\'s nothing to pay.' ) }}</p>
        @endif

        <div class="ec-checkout-form__actions">
            <x-artisanpack-button
                color="primary"
                class="ec-checkout-form__submit"
                wire:click="placeOrder( '{{ $placeOrderToken }}' )"
                wire:loading.attr="disabled"
                wire:target="placeOrder"
                data-checkout-place-order>{{ $ecommercePlaceOrder }}</x-artisanpack-button>
        </div>
    @elseif ( $needsAccount )
        <x-artisanpack-alert
            color="info"
            icon="lock"
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
    @elseif ( [] === $paymentMethods )
        <x-artisanpack-alert color="warning" icon="triangle-alert" :title="__( 'No payment methods are available' )" :description="__( 'Please contact the store to complete your order.' )" data-checkout-no-gateways />
    @else
        <div data-field="gateway">
            <fieldset
                class="apui-radio-group ec-payment-methods"
                id="ec-checkout-gateway"
                data-orientation="vertical"
                data-variant="cards"
                @error( 'gateway' ) data-invalid aria-describedby="ec-checkout-gateway-error" @enderror
                @if ( ! $ecommerceSingle ) data-checkout-gateways @endif
            >
                <legend data-scope="radio-group" data-part="label">{{ __( 'Payment method' ) }}</legend>

                <div data-scope="radio-group" data-part="items">
                    @foreach ( $paymentMethods as $methodKey => $method )
                        @php
                            $ecommerceMethodId       = 'ec-checkout-gateway-' . $loop->index;
                            $ecommerceMethodSelected = $ecommerceSingle || $gateway === $methodKey;
                            $ecommerceMethodDriver   = $ecommerceMethodSelected && null !== $payment && $payment['gateway'] === $methodKey;
                        @endphp
                        <div
                            class="ec-payment-method"
                            wire:key="checkout-gateway-{{ $methodKey }}"
                            @if ( $ecommerceMethodSelected ) data-selected @endif
                            data-checkout-gateway="{{ $methodKey }}"
                        >
                            @if ( $ecommerceSingle )
                                <div class="ec-payment-method__header" data-checkout-gateway-single>
                                    <span class="ec-payment-method__text">{{ __( 'Pay with :gateway', [ 'gateway' => $method['label'] ] ) }}</span>
                                    @if ( null !== $method['description'] )
                                        <span class="ec-payment-method__description">{{ $method['description'] }}</span>
                                    @endif
                                    @if ( null !== $method['icon'] )
                                        <x-artisanpack-icon :name="$method['icon']" class="ec-payment-method__icon" aria-hidden="true" />
                                    @endif
                                </div>
                            @else
                                <label data-scope="radio-group" data-part="item" class="ec-payment-method__header" for="{{ $ecommerceMethodId }}">
                                    <span data-scope="radio-group" data-part="box">
                                        <input
                                            type="radio"
                                            data-scope="radio-group"
                                            data-part="control"
                                            id="{{ $ecommerceMethodId }}"
                                            name="ec-checkout-gateway"
                                            value="{{ $methodKey }}"
                                            wire:model.live="gateway"
                                            @if ( $ecommerceMethodSelected ) checked @endif
                                            @error( 'gateway' ) aria-invalid="true" @enderror
                                            @if ( null !== $method['description'] ) aria-describedby="{{ $ecommerceMethodId }}-description" @endif
                                        />
                                        <span data-scope="radio-group" data-part="indicator" aria-hidden="true"></span>
                                    </span>
                                    <span data-scope="radio-group" data-part="item-text">{{ $method['label'] }}</span>
                                    @if ( null !== $method['description'] )
                                        <span data-scope="radio-group" data-part="item-description" id="{{ $ecommerceMethodId }}-description">{{ $method['description'] }}</span>
                                    @endif
                                    @if ( null !== $method['icon'] )
                                        <x-artisanpack-icon :name="$method['icon']" class="ec-payment-method__icon" aria-hidden="true" />
                                    @endif
                                </label>
                            @endif

                            @if ( $ecommerceMethodDriver )
                                <div
                                    class="ec-payment-method__panel"
                                    wire:key="checkout-payment-{{ $payment['reference'] }}"
                                    wire:loading.remove
                                    wire:target="gateway"
                                    data-checkout-payment-driver="{{ $payment['config']['driver'] ?? '' }}"
                                >
                                    @livewire( $payment['component'], [
                                        'config'       => $payment['config'],
                                        'gateway'      => $payment['gateway'],
                                        'gatewayLabel' => $payment['label'],
                                        'reference'    => $payment['reference'],
                                        'token'        => (string) $placeOrderToken,
                                    ], key( 'checkout-payment-driver-' . $payment['reference'] ) )
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @error( 'gateway' )
                    <p data-scope="radio-group" data-part="error" id="ec-checkout-gateway-error">
                        <x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}
                    </p>
                @enderror
            </fieldset>
        </div>

        <p class="ec-checkout-form__note" wire:loading wire:target="gateway" data-checkout-payment-loading>{{ __( 'Setting up your payment…' ) }}</p>

        @if ( null !== $payment )
            <p class="ec-checkout-form__note" wire:loading.remove wire:target="gateway" data-checkout-payment-places-order>{{ __( 'Your order is placed as soon as your payment is confirmed.' ) }}</p>
        @endif
    @endif

    <div data-field="payment">
        @error( 'payment' )
            <p class="ec-checkout-form__error" data-checkout-payment-error>
                <x-artisanpack-icon name="circle-alert" aria-hidden="true" />
                <span>{{ $message }}</span>
            </p>
        @enderror
    </div>
</div>
