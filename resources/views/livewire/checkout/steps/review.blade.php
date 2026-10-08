{{--
    Checkout step: review. What the shopper is buying and where it goes,
    an optional note, the terms checkbox (when a terms page is set), the
    optional account for guests, and "Place order". The button carries a
    one-time action token, so a double click places one order.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<form wire:submit="placeOrder( '{{ $placeOrderToken }}' )" novalidate class="flex flex-col gap-6" data-checkout-review-form>
    <dl class="grid gap-4 text-sm sm:grid-cols-2" data-checkout-review>
        <div>
            <dt class="font-semibold">{{ __( 'Contact' ) }}</dt>
            <dd>{{ $cart->email }}</dd>
        </div>

        @if ( $ships && is_array( $cart->shipping_address ) )
            <div>
                <dt class="font-semibold">{{ __( 'Ship to' ) }}</dt>
                <dd><x-artisanpack-ec-address :address="$cart->shipping_address" /></dd>
            </div>
        @endif

        <div>
            <dt class="font-semibold">{{ __( 'Bill to' ) }}</dt>
            <dd>
                @if ( is_array( $cart->billing_address ) )
                    <x-artisanpack-ec-address :address="$cart->billing_address" />
                @elseif ( is_array( $cart->shipping_address ) )
                    {{ __( 'Same as shipping' ) }}
                @endif
            </dd>
        </div>

        @if ( $ships && null !== $chosenRate )
            <div>
                <dt class="font-semibold">{{ __( 'Shipping' ) }}</dt>
                <dd>{{ $chosenRate['label'] ?? '' }}</dd>
            </div>
        @endif

        <div>
            <dt class="font-semibold">{{ __( 'Payment' ) }}</dt>
            <dd>{{ $paymentRequired ? ( $gatewayLabel ?? '' ) : __( 'No payment needed' ) }}</dd>
        </div>
    </dl>

    <section class="flex flex-col gap-3" aria-labelledby="ec-checkout-review-items" data-checkout-review-lines>
        <h3 id="ec-checkout-review-items" class="font-semibold">{{ __( 'Items' ) }}</h3>

        <ul class="flex flex-col gap-2 text-sm">
            @foreach ( $lines as $line )
                <li class="flex items-start justify-between gap-4" wire:key="checkout-review-line-{{ $line['id'] }}">
                    <span>
                        {{ $line['name'] }} <span class="text-base-content/70">&times; {{ $line['quantity'] }}</span>
                        @foreach ( $line['options'] as $option )
                            <span class="block text-base-content/70">{{ $option }}</span>
                        @endforeach
                    </span>

                    @if ( $line['free'] )
                        <span>{{ __( 'Free' ) }}</span>
                    @else
                        <x-artisanpack-ec-money :amount="$line['total']" :currency="$line['currency']" />
                    @endif
                </li>
            @endforeach
        </ul>

        @if ( null !== $totals )
            @include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => $totals ] )
        @endif
    </section>

    <div data-field="orderNote">
        <x-artisanpack-textarea
            :label="__( 'Order note (optional)' )"
            :hint="__( 'Anything the store should know about your order. Up to :max characters.', [ 'max' => $noteMaxLength ] )"
            rows="3"
            maxlength="{{ $noteMaxLength }}"
            wire:model="orderNote"
            data-checkout-order-note
        />
    </div>

    @if ( $offersAccount )
        <div class="flex flex-col gap-3" data-checkout-account>
            <x-artisanpack-checkbox :label="__( 'Create an account with this email' )" :hint="__( 'Track this order and check out faster next time.' )" wire:model.live="createAccount" data-checkout-create-account />

            @if ( $createAccount )
                <div data-field="password">
                    <x-artisanpack-password :label="__( 'Password' )" autocomplete="new-password" wire:model="password" data-checkout-password />
                </div>
            @endif
        </div>
    @endif

    @if ( null !== $termsUrl )
        <div class="flex flex-col gap-1" data-field="acceptTerms">
            <x-artisanpack-checkbox :label="__( 'I accept the terms and conditions' )" wire:model="acceptTerms" data-checkout-terms />
            <a href="{{ $termsUrl }}" class="link text-sm" target="_blank" rel="noopener" data-checkout-terms-link>
                {{ __( 'Read the terms and conditions' ) }}<span class="sr-only"> {{ __( '(opens in a new tab)' ) }}</span>
            </a>
        </div>
    @endif

    <div class="flex flex-col gap-2">
        <x-artisanpack-button
            type="submit"
            :label="__( 'Place order' )"
            color="primary"
            class="btn-lg w-full sm:w-auto"
            spinner="placeOrder"
            wire:loading.attr="disabled"
            wire:target="placeOrder"
            data-checkout-place-order
        />

        @if ( null !== $totals && $paymentRequired )
            <p class="text-sm text-base-content/70">{{ __( 'You\'ll pay :total.', [ 'total' => \ArtisanPackUI\Ecommerce\Support\MoneyFormatter::format( $totals['total'], $totals['currency'] ) ] ) }}</p>
        @endif
    </div>
</form>
