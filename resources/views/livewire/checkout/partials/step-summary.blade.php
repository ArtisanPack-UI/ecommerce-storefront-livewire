{{--
    What a finished checkout step chose (#136), under its title: the email,
    the ship-to and bill-to addresses side by side (from 640px), the
    shipping option and its price, what the review settled (#140: terms
    accepted, an account to create, a note), or the payment.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@switch ( $summaryStep['key'] )
    @case ( 'contact' )
        <p class="ec-step-summary__text">{{ $cart->email }}</p>
        @break

    @case ( 'address' )
        <div class="ec-step-summary__addresses">
            @if ( $ships && is_array( $cart->shipping_address ) )
                <div>
                    <p class="ec-step-summary__label">{{ __( 'Ship to' ) }}</p>
                    <x-artisanpack-ec-address :address="$cart->shipping_address" />
                </div>
            @endif

            <div>
                <p class="ec-step-summary__label">{{ __( 'Bill to' ) }}</p>
                @if ( is_array( $cart->billing_address ) )
                    <x-artisanpack-ec-address :address="$cart->billing_address" />
                @else
                    <p class="ec-step-summary__text">{{ __( 'Same as shipping' ) }}</p>
                @endif
            </div>
        </div>
        @break

    @case ( 'shipping' )
        @if ( null !== $chosenRate )
            <p class="ec-step-summary__text">
                {{ $chosenRate['label'] ?? '' }} &middot;
                @if ( (int) $cart->shipping_amount > 0 )
                    <x-artisanpack-ec-money :amount="(int) $cart->shipping_amount" :currency="(string) $cart->currency" />
                @else
                    {{ __( 'Free' ) }}
                @endif
            </p>
        @endif
        @break

    @case ( 'review' )
        @php
            $ecommerceReviewParts = array_values( array_filter( [
                ( $reviewSummary['terms'] ?? false ) ? __( 'Terms accepted' ) : null,
                ( $reviewSummary['account'] ?? false ) ? __( 'Account will be created' ) : null,
                ( $reviewSummary['note'] ?? false ) ? __( 'Note added' ) : null,
            ] ) );
        @endphp
        <p class="ec-step-summary__text">{{ [] === $ecommerceReviewParts ? __( 'Done' ) : implode( ' · ', $ecommerceReviewParts ) }}</p>
        @break

    @case ( 'payment' )
        <p class="ec-step-summary__text">{{ $paymentRequired ? ( $gatewayLabel ?? __( 'Payment confirmed' ) ) : __( 'No payment needed' ) }}</p>
        @break

    @default
        <p class="ec-step-summary__text">{{ __( 'Done' ) }}</p>
@endswitch
