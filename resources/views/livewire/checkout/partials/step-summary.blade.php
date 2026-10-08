{{--
    What a completed checkout step chose, shown under its heading.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@switch ( $summaryStep['key'] )
    @case ( 'contact' )
        <p>{{ $cart->email }}</p>
        @break

    @case ( 'address' )
        <div class="grid gap-4 sm:grid-cols-2">
            @if ( $ships && is_array( $cart->shipping_address ) )
                <div>
                    <p class="font-semibold">{{ __( 'Ship to' ) }}</p>
                    <x-artisanpack-ec-address :address="$cart->shipping_address" />
                </div>
            @endif

            <div>
                <p class="font-semibold">{{ __( 'Bill to' ) }}</p>
                @if ( is_array( $cart->billing_address ) )
                    <x-artisanpack-ec-address :address="$cart->billing_address" />
                @else
                    <p>{{ __( 'Same as shipping' ) }}</p>
                @endif
            </div>
        </div>
        @break

    @case ( 'shipping' )
        @if ( null !== $chosenRate )
            <p>
                {{ $chosenRate['label'] ?? '' }} &middot;
                @if ( (int) $cart->shipping_amount > 0 )
                    <x-artisanpack-ec-money :amount="(int) $cart->shipping_amount" :currency="(string) $cart->currency" />
                @else
                    {{ __( 'Free' ) }}
                @endif
            </p>
        @endif
        @break

    @case ( 'payment' )
        <p>{{ $paymentRequired ? ( $gatewayLabel ?? __( 'Payment confirmed' ) ) : __( 'No payment needed' ) }}</p>
        @break

    @default
        <p>{{ __( 'Done' ) }}</p>
@endswitch
