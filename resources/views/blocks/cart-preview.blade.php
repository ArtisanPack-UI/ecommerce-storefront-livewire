{{--
    The Cart Contents block's editor preview: a sample cart that is never
    saved. See Blocks\CartContentsBlock.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6" data-cart-preview>
    <div class="flex flex-col items-start gap-1 rounded-box border border-dashed border-base-content/30 p-3 text-sm" data-commerce-sample>
        <span class="badge badge-neutral badge-sm">{{ __( 'Sample cart' ) }}</span>
        <span>{{ __( 'Shoppers see their own cart here. This preview uses your newest products and isn\'t saved.' ) }}</span>
    </div>

    <div class="grid gap-8 lg:grid-cols-3">
        <ul class="flex flex-col divide-y divide-base-content/10 lg:col-span-2" aria-label="{{ __( 'Items in your cart' ) }}">
            @foreach ( $lines as $line )
                <li class="flex gap-4 py-4 first:pt-0" data-cart-preview-line>
                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-box bg-base-200">
                        @if ( null !== $line['image'] )
                            <img src="{{ $line['image']['url'] }}" @if ( null !== ( $line['image']['srcset'] ?? null ) ) srcset="{{ $line['image']['srcset'] }}" sizes="96px" @endif width="96" height="96" alt="" class="h-full w-full object-cover" loading="lazy" decoding="async">
                        @endif
                    </div>

                    <div class="flex grow flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold">{{ $line['name'] }}</p>
                            <p class="text-sm">{{ __( 'Quantity: :count', [ 'count' => 1 ] ) }}</p>
                        </div>

                        <p class="font-semibold"><x-artisanpack-ec-money :amount="$line['amount']" :currency="$currency" /></p>
                    </div>
                </li>
            @endforeach
        </ul>

        <aside class="flex flex-col gap-4" aria-label="{{ __( 'Order summary' ) }}">
            <p class="text-xl font-bold">{{ __( 'Order summary' ) }}</p>

            @if ( $showCoupon )
                <x-artisanpack-input id="ec-cart-preview-coupon" :label="__( 'Coupon code' )" disabled data-cart-preview-coupon />
            @endif

            <dl class="flex justify-between font-semibold">
                <dt>{{ __( 'Subtotal' ) }}</dt>
                <dd><x-artisanpack-ec-money :amount="$subtotal" :currency="$currency" /></dd>
            </dl>

            <x-artisanpack-button :label="__( 'Checkout' )" color="primary" class="w-full" disabled />
        </aside>
    </div>

    @if ( $showCrossSells )
        <p class="rounded-box border border-dashed border-base-content/30 p-4 text-sm text-base-content/70" data-cart-preview-cross-sells>
            {{ __( 'Cross-sells for the shopper\'s cart appear here.' ) }}
        </p>
    @endif
</div>
