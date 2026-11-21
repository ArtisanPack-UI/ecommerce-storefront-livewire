{{--
    The checkout's order summary (#136): a design-system Card with the
    lines (a thumbnail carrying the quantity, the name, the options or
    "Free gift", the line total) and the totals, which follow the shipping
    rate and tax as the shopper chooses them. Sticky beside the steps from
    1024px. Below that it sits above the title as a "Show order summary"
    bar with the total, which opens the lines and totals.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<section class="apui-card ec-checkout-summary" data-surface="default" aria-labelledby="ec-checkout-summary-heading" x-data="{ summaryOpen: false }" data-checkout-summary>
    <button
        type="button"
        class="ec-checkout-summary__toggle"
        x-on:click="summaryOpen = ! summaryOpen"
        x-bind:aria-expanded="summaryOpen ? 'true' : 'false'"
        aria-expanded="false"
        aria-controls="ec-checkout-summary-body"
        data-checkout-summary-toggle
    >
        <span class="ec-checkout-summary__toggle-label">
            <x-artisanpack-icon name="shopping-bag" aria-hidden="true" />
            <span x-text="summaryOpen ? @js( __( 'Hide order summary' ) ) : @js( __( 'Show order summary' ) )">{{ __( 'Show order summary' ) }}</span>
        </span>

        @if ( null !== $totals )
            <x-artisanpack-ec-money :amount="$totals['total']" :currency="$totals['currency']" class="ec-checkout-summary__toggle-total" />
        @endif
    </button>

    <div id="ec-checkout-summary-body" class="ec-checkout-summary__body" x-bind:class="{ 'is-open': summaryOpen }" data-checkout-summary-body>
        <div class="ec-checkout-summary__header">
            <h2 id="ec-checkout-summary-heading" class="ec-checkout-summary__title">{{ __( 'Order summary' ) }}</h2>

            @if ( null !== $cartUrl )
                <x-artisanpack-link :href="$cartUrl" :navigate="false" data-checkout-edit-cart>{{ __( 'Edit cart' ) }}</x-artisanpack-link>
            @endif
        </div>

        <ul role="list" class="ec-checkout-summary__lines" data-checkout-lines>
            @foreach ( $lines as $line )
                <li class="ec-checkout-summary__line" wire:key="checkout-line-{{ $line['id'] }}" data-checkout-line="{{ $line['id'] }}">
                    <span class="ec-checkout-summary__thumb">
                        @if ( null !== $line['image'] )
                            <img src="{{ $line['image']['url'] }}" @if ( null !== ( $line['image']['srcset'] ?? null ) ) srcset="{{ $line['image']['srcset'] }}" sizes="56px" @endif width="56" height="56" alt="" loading="lazy" decoding="async">
                        @else
                            <x-artisanpack-icon name="image" aria-hidden="true" />
                        @endif
                        <span class="ec-checkout-summary__quantity" aria-hidden="true">{{ $line['quantity'] }}</span>
                    </span>

                    <span class="ec-checkout-summary__details">
                        <span class="ec-checkout-summary__name">{{ $line['name'] }} <span class="apui-sr-only">{{ __( '× :quantity', [ 'quantity' => $line['quantity'] ] ) }}</span></span>
                        @if ( $line['free'] )
                            <span class="ec-checkout-summary__meta">{{ __( 'Free gift' ) }}</span>
                        @elseif ( [] !== $line['options'] )
                            <span class="ec-checkout-summary__meta">{{ implode( ' · ', $line['options'] ) }}</span>
                        @endif
                    </span>

                    <span class="ec-checkout-summary__amount">
                        @if ( $line['free'] )
                            {{ __( 'Free' ) }}
                        @else
                            <x-artisanpack-ec-money :amount="$line['total']" :currency="$line['currency']" />
                        @endif
                    </span>
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
</section>
