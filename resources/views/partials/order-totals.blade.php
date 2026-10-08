{{--
    An order summary's totals: subtotal, each discount, shipping, tax, and
    total. `$totals` is DescribesCart::totals(); `$shippingPending` and
    `$taxPending` replace "Calculated at checkout" for a shipping cost or tax
    that isn't known yet.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<dl class="flex flex-col gap-2 border-t border-base-content/10 pt-4" data-cart-totals>
    <div class="flex justify-between gap-4">
        <dt>{{ __( 'Subtotal' ) }}</dt>
        <dd><x-artisanpack-ec-money :amount="$totals['subtotal']" :currency="$totals['currency']" data-cart-subtotal /></dd>
    </div>

    @foreach ( $totals['discounts'] as $discount )
        <div class="flex justify-between gap-4 text-success" data-cart-discount>
            <dt>{{ $discount['label'] }}</dt>
            <dd>
                @if ( $discount['amount'] > 0 )
                    <span class="sr-only">{{ __( 'Minus' ) }}</span><span aria-hidden="true">&minus;</span><x-artisanpack-ec-money :amount="$discount['amount']" :currency="$totals['currency']" />
                @else
                    {{ __( 'Free shipping' ) }}
                @endif
            </dd>
        </div>
    @endforeach

    @if ( 'none' !== $totals['shipping']['state'] )
        <div class="flex justify-between gap-4" data-cart-shipping="{{ $totals['shipping']['state'] }}">
            <dt>
                {{ __( 'Shipping' ) }}
                @if ( 'chosen' === $totals['shipping']['state'] && '' !== $totals['shipping']['label'] )
                    <span class="block text-sm text-base-content/70">{{ $totals['shipping']['label'] }}</span>
                @endif
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
                        <span class="text-sm text-base-content/70">{{ $shippingPending ?? __( 'Calculated at checkout' ) }}</span>
                @endswitch
            </dd>
        </div>
    @endif

    <div class="flex justify-between gap-4" data-cart-tax="{{ $totals['tax']['estimated'] ? 'estimated' : 'calculated' }}">
        <dt>
            @if ( $totals['tax']['inclusive'] )
                {{ $totals['tax']['estimated'] ? __( 'Included tax (estimated)' ) : __( 'Included tax' ) }}
            @else
                {{ $totals['tax']['estimated'] ? __( 'Tax (estimated)' ) : __( 'Tax' ) }}
            @endif
        </dt>
        <dd>
            @if ( $totals['tax']['estimated'] && 0 === $totals['tax']['amount'] )
                <span class="text-sm text-base-content/70">{{ $taxPending ?? __( 'Calculated at checkout' ) }}</span>
            @else
                <x-artisanpack-ec-money :amount="$totals['tax']['amount']" :currency="$totals['currency']" />
            @endif
        </dd>
    </div>

    <div class="flex justify-between gap-4 border-t border-base-content/10 pt-2 text-lg font-bold">
        <dt>{{ __( 'Total' ) }}</dt>
        <dd><x-artisanpack-ec-money :amount="$totals['total']" :currency="$totals['currency']" data-cart-total /></dd>
    </div>
</dl>
