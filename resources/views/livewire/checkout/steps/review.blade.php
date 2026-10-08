{{--
    Checkout step: review. What the shopper chose, before placing the
    order.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
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
