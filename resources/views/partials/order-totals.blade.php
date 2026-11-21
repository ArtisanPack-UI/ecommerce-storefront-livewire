{{--
    An order summary's totals (#88): subtotal, each discount, shipping, tax,
    then a Divider and the total. `$totals` is DescribesCart::totals();
    `$shippingPending` and `$taxPending` replace "Calculated at checkout" for
    a shipping cost or tax that isn't known yet.

    Labels are muted and values sit at the end in tabular figures; a discount
    reads in the success tone after a true minus (announced as "Minus"), and
    the total is set large after the Divider. A <dl> can't hold an <hr>, so
    the rows and the total are two lists either side of it. A placed order's
    refunds (`$totals['refunded']`, from DescribesOrder) follow the total.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-totals" data-cart-totals>
    <dl class="ec-totals__list">
        <div class="ec-totals__row">
            <dt>{{ __( 'Subtotal' ) }}</dt>
            <dd><x-artisanpack-ec-money :amount="$totals['subtotal']" :currency="$totals['currency']" data-cart-subtotal /></dd>
        </div>

        @foreach ( $totals['discounts'] as $discount )
            <div class="ec-totals__row" data-cart-discount>
                <dt>{{ $discount['label'] }}</dt>
                <dd class="ec-totals__discount">
                    @if ( $discount['amount'] > 0 )
                        <span class="apui-sr-only">{{ __( 'Minus' ) }}</span><span aria-hidden="true">&minus;</span><x-artisanpack-ec-money :amount="$discount['amount']" :currency="$totals['currency']" />
                    @else
                        {{ __( 'Free shipping' ) }}
                    @endif
                </dd>
            </div>
        @endforeach

        @if ( 'none' !== $totals['shipping']['state'] )
            <div class="ec-totals__row" data-cart-shipping="{{ $totals['shipping']['state'] }}">
                <dt>
                    {{ __( 'Shipping' ) }}@if ( 'chosen' === $totals['shipping']['state'] && '' !== (string) $totals['shipping']['label'] )<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span><span data-cart-shipping-method>{{ $totals['shipping']['label'] }}</span>@endif
                </dt>
                <dd>
                    @switch ( $totals['shipping']['state'] )
                        @case ( 'chosen' )
                            <x-artisanpack-ec-money :amount="$totals['shipping']['amount']" :currency="$totals['currency']" />
                            @break
                        @case ( 'free' )
                            {{ __( 'Free' ) }}
                            @break
                        @default
                            <span class="ec-totals__pending">{{ $shippingPending ?? __( 'Calculated at checkout' ) }}</span>
                    @endswitch
                </dd>
            </div>
        @endif

        <div class="ec-totals__row" data-cart-tax="{{ $totals['tax']['estimated'] ? 'estimated' : 'calculated' }}">
            <dt>
                @if ( $totals['tax']['inclusive'] )
                    {{ $totals['tax']['estimated'] ? __( 'Included tax (estimated)' ) : __( 'Included tax' ) }}
                @else
                    {{ $totals['tax']['estimated'] ? __( 'Tax (estimated)' ) : __( 'Tax' ) }}
                @endif
            </dt>
            <dd>
                @if ( $totals['tax']['estimated'] && 0 === $totals['tax']['amount'] )
                    <span class="ec-totals__pending">{{ $taxPending ?? __( 'Calculated at checkout' ) }}</span>
                @else
                    <x-artisanpack-ec-money :amount="$totals['tax']['amount']" :currency="$totals['currency']" />
                @endif
            </dd>
        </div>
    </dl>

    <x-artisanpack-divider decorative />

    <dl class="ec-totals__list">
        <div class="ec-totals__row ec-totals__total">
            <dt>{{ __( 'Total' ) }}</dt>
            <dd><x-artisanpack-ec-money :amount="$totals['total']" :currency="$totals['currency']" data-cart-total /></dd>
        </div>

        @if ( ( $totals['refunded'] ?? 0 ) > 0 )
            <div class="ec-totals__row" data-order-refunded>
                <dt>{{ __( 'Refunded' ) }}</dt>
                <dd><span class="apui-sr-only">{{ __( 'Minus' ) }}</span><span aria-hidden="true">&minus;</span><x-artisanpack-ec-money :amount="$totals['refunded']" :currency="$totals['currency']" /></dd>
            </div>
        @endif
    </dl>
</div>
