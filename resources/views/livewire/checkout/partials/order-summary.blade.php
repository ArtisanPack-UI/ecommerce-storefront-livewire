{{--
    The checkout's order summary: the lines and the totals, which follow
    the shipping rate and tax as the shopper chooses them.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<aside class="flex flex-col gap-4 rounded-box border border-base-content/10 p-5 lg:sticky lg:top-24" aria-labelledby="ec-checkout-summary-heading" data-checkout-summary>
    <div class="flex items-center justify-between gap-4">
        <h2 id="ec-checkout-summary-heading" class="text-xl font-bold">{{ __( 'Order summary' ) }}</h2>

        @if ( null !== $cartUrl )
            <a href="{{ $cartUrl }}" class="link text-sm" data-checkout-edit-cart>{{ __( 'Edit cart' ) }}</a>
        @endif
    </div>

    <ul class="flex flex-col gap-3" data-checkout-lines>
        @foreach ( $lines as $line )
            <li class="flex items-start justify-between gap-4 text-sm" wire:key="checkout-line-{{ $line['id'] }}" data-checkout-line="{{ $line['id'] }}">
                <div>
                    <p class="font-semibold">{{ $line['name'] }} <span class="font-normal text-base-content/70">&times; {{ $line['quantity'] }}</span></p>

                    @foreach ( $line['options'] as $option )
                        <p class="text-base-content/70">{{ $option }}</p>
                    @endforeach
                </div>

                <p>
                    @if ( $line['free'] )
                        {{ __( 'Free' ) }}
                    @else
                        <x-artisanpack-ec-money :amount="$line['total']" :currency="$line['currency']" />
                    @endif
                </p>
            </li>
        @endforeach
    </ul>

    @if ( null !== $totals )
        @include( 'ecommerce-storefront::partials.order-totals', [
            'totals'          => $totals,
            'shippingPending' => __( 'Calculated at the shipping step' ),
            'taxPending'      => __( 'Calculated once we have your address' ),
        ] )
    @endif
</aside>
