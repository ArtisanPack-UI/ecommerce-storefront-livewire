{{--
    The checkout's order summary: the lines and the totals, which follow
    the shipping rate and tax as the shopper chooses them. On small screens
    it is collapsed behind a "Show order summary" toggle with the total.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<aside class="flex flex-col gap-4 rounded-box border border-base-content/10 p-5 lg:sticky lg:top-24" aria-labelledby="ec-checkout-summary-heading" x-data="{ summaryOpen: false }" data-checkout-summary>
    <button
        type="button"
        class="flex w-full items-center justify-between gap-4 lg:hidden"
        x-on:click="summaryOpen = ! summaryOpen"
        x-bind:aria-expanded="summaryOpen ? 'true' : 'false'"
        aria-expanded="false"
        aria-controls="ec-checkout-summary-body"
        data-checkout-summary-toggle
    >
        <span class="flex items-center gap-2 font-semibold">
            <x-artisanpack-icon name="o-shopping-bag" class="h-5 w-5" aria-hidden="true" />
            <span x-text="summaryOpen ? @js( __( 'Hide order summary' ) ) : @js( __( 'Show order summary' ) )">{{ __( 'Show order summary' ) }}</span>
        </span>

        @if ( null !== $totals )
            <x-artisanpack-ec-money :amount="$totals['total']" :currency="$totals['currency']" class="font-bold" />
        @endif
    </button>

    <div id="ec-checkout-summary-body" class="hidden flex-col gap-4 lg:flex" x-bind:class="{ 'hidden': ! summaryOpen, 'flex': summaryOpen }" data-checkout-summary-body>
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
    </div>
</aside>
