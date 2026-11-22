{{--
    The Cart Contents block's editor preview (#114): a sample cart that is
    never saved. See Blocks\CartContentsBlock.

    The commerce block frame's dashed sample notice (#91: a soft info
    "Sample cart" badge and one line of help) over the cart page's layout:
    the lines in a Card (a 96px image, the name, "Quantity: 1" and the
    price, divided by rules), beside an "Order summary" Card with the
    subtotal, shipping "Calculated at checkout", a disabled coupon field
    and a disabled full-width "Checkout". The summary drops below the lines
    when the block is narrow. A dashed note stands in for the cross-sells.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-cart-preview" data-cart-preview>
    <div class="ec-block-notice ec-block-notice--sample" data-commerce-sample>
        <x-artisanpack-badge color="info" leading-icon="info">{{ __( 'Sample cart' ) }}</x-artisanpack-badge>
        <p class="ec-block-notice__message">{{ __( 'Shoppers see their own cart here. This preview uses your newest products and isn\'t saved.' ) }}</p>
    </div>

    <div class="ec-cart-preview__layout">
        <x-artisanpack-card class="ec-cart-preview__lines">
            <ul role="list" class="ec-cart-preview__list" aria-label="{{ __( 'Items in your cart' ) }}">
                @foreach ( $lines as $line )
                    <li class="ec-cart-preview__line" data-cart-preview-line>
                        <div class="ec-cart-preview__media" aria-hidden="true">
                            @if ( null !== $line['image'] )
                                <img src="{{ $line['image']['url'] }}" @if ( null !== ( $line['image']['srcset'] ?? null ) ) srcset="{{ $line['image']['srcset'] }}" sizes="96px" @endif width="96" height="96" alt="" loading="lazy" decoding="async">
                            @else
                                <x-artisanpack-icon name="image" />
                            @endif
                        </div>

                        <div class="ec-cart-preview__details">
                            <p class="ec-cart-preview__name">{{ $line['name'] }}</p>
                            <p class="ec-cart-preview__quantity">{{ __( 'Quantity: :count', [ 'count' => 1 ] ) }}</p>
                        </div>

                        <p class="ec-cart-preview__amount"><x-artisanpack-ec-money :amount="$line['amount']" :currency="$currency" /></p>
                    </li>
                @endforeach
            </ul>
        </x-artisanpack-card>

        <x-artisanpack-card class="ec-cart-preview__summary" :title="__( 'Order summary' )">
            @if ( $showCoupon )
                <x-artisanpack-input id="ec-cart-preview-coupon" :label="__( 'Coupon code' )" disabled data-cart-preview-coupon />
            @endif

            <dl class="ec-cart-preview__totals">
                <div class="ec-cart-preview__total">
                    <dt>{{ __( 'Subtotal' ) }}</dt>
                    <dd><x-artisanpack-ec-money :amount="$subtotal" :currency="$currency" /></dd>
                </div>

                <div class="ec-cart-preview__total">
                    <dt>{{ __( 'Shipping' ) }}</dt>
                    <dd>{{ __( 'Calculated at checkout' ) }}</dd>
                </div>
            </dl>

            <x-artisanpack-button color="primary" class="ec-cart-preview__checkout" disabled>{{ __( 'Checkout' ) }}</x-artisanpack-button>
        </x-artisanpack-card>
    </div>

    @if ( $showCrossSells )
        <p class="ec-block-notice ec-block-notice__message ec-cart-preview__cross-sells" data-cart-preview-cross-sells>
            {{ __( 'Cross-sells for the shopper\'s cart appear here.' ) }}
        </p>
    @endif
</div>
