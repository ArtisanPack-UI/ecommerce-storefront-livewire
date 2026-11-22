{{--
    A placed order's details (#87), shared by the confirmation page, the
    account order page and the guest order view.

    An Items card (list rows: a 64px thumbnail, the name, the options, the
    SKU in the mono face with "2 × $28.00", and the line total), then any
    downloads, then two cards side by side that stack below 768px: Addresses
    and payment (a <dl> grid with the shipping and billing addresses, the
    payment method and email, and the shopper's note on a sunken panel) and
    the Order summary.

    Takes `$order`, and from DescribesOrder `$lines`, `$totals`,
    `$paymentMethod`, and `$downloads`. `$downloadsUrl` links the downloads
    to the shopper's account; without it guests are told the links are in
    their email.

    Each card's heading is an <h2> of its own (the design-system Card's title
    is an <h3>), so the details keep their place in the page's outline.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceItemCount = array_sum( array_column( $lines, 'quantity' ) ) )
@php( $ecommerceSameAddress = is_array( $order->shipping_address ) && is_array( $order->billing_address ) && $order->shipping_address == $order->billing_address )
<div class="ec-order-details" data-order-details>
    <x-artisanpack-card class="ec-order-card ec-order-card--flush" role="region" aria-labelledby="ec-order-items-heading" data-order-items>
        <div class="ec-order-card__head">
            <h2 id="ec-order-items-heading" class="ec-order-card__title">{{ __( 'Items' ) }}</h2>
            <span class="ec-order-card__aside" data-order-item-count>{{ trans_choice( ':count item|:count items', $ecommerceItemCount, [ 'count' => $ecommerceItemCount ] ) }}</span>
        </div>

        <x-artisanpack-list variant="divided" class="ec-order-lines" data-order-lines>
            @foreach ( $lines as $line )
                <x-artisanpack-list-item class="ec-order-line" wire:key="order-line-{{ $line['id'] }}" data-order-line="{{ $line['id'] }}">
                    <x-slot:leading>
                        <span class="ec-order-line__thumb" data-order-line-image>
                            @if ( null !== ( $line['image'] ?? null ) )
                                <img src="{{ $line['image']['url'] }}" @if ( null !== $line['image']['srcset'] ) srcset="{{ $line['image']['srcset'] }}" sizes="64px" @endif alt="" width="64" height="64" loading="lazy" decoding="async" />
                            @else
                                <x-artisanpack-icon name="image" />
                            @endif
                        </span>
                    </x-slot:leading>

                    <x-slot:title>{{ $line['name'] }}</x-slot:title>

                    <x-slot:description>
                        @php( $ecommerceLineOptions = array_values( array_filter( [ ...( ( $line['options'] ?? [] ) ?: [ $line['variant'] ] ), $line['free'] ? __( 'Free gift' ) : null ], static fn ( mixed $text ): bool => null !== $text && '' !== $text ) ) )
                        @if ( [] !== $ecommerceLineOptions )
                            <span class="ec-order-line__options" data-order-line-options>{{ implode( ' · ', $ecommerceLineOptions ) }}</span>
                        @endif

                        <span class="ec-order-line__meta">
                            @if ( null !== $line['sku'] )
                                <span class="ec-order-line__sku">{{ __( 'SKU: :sku', [ 'sku' => $line['sku'] ] ) }}</span><span aria-hidden="true"> &middot; </span>
                            @endif
                            <span data-order-line-quantity><bdi>{{ $line['quantity'] }}</bdi> &times; @if ( $line['free'] ){{ __( 'Free' ) }}@else<x-artisanpack-ec-money :amount="$line['unit']" :currency="$line['currency']" />@endif</span>
                        </span>
                    </x-slot:description>

                    <x-slot:trailing>
                        <span class="ec-order-line__total" data-order-line-total>
                            @if ( $line['free'] )
                                {{ __( 'Free' ) }}
                            @else
                                <x-artisanpack-ec-money :amount="$line['total']" :currency="$line['currency']" />
                            @endif
                        </span>
                    </x-slot:trailing>
                </x-artisanpack-list-item>
            @endforeach
        </x-artisanpack-list>
    </x-artisanpack-card>

    @if ( [] !== $downloads )
        <x-artisanpack-card class="ec-order-card ec-order-card--flush" role="region" aria-labelledby="ec-order-downloads-heading" data-order-downloads>
            <div class="ec-order-card__head">
                <h2 id="ec-order-downloads-heading" class="ec-order-card__title">{{ __( 'Downloads' ) }}</h2>

                @if ( null !== ( $downloadsUrl ?? null ) )
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" :href="$downloadsUrl" leading-icon="download" data-order-downloads-link>{{ __( 'Go to your downloads' ) }}</x-artisanpack-button>
                @endif
            </div>

            <x-artisanpack-list variant="divided" class="ec-order-lines">
                @foreach ( $downloads as $download )
                    <x-artisanpack-list-item class="ec-order-line" wire:key="order-download-{{ $download['id'] }}" data-order-download="{{ $download['id'] }}">
                        <x-slot:title>{{ $download['name'] }}</x-slot:title>
                        <x-slot:description>
                            {{ null === $download['remaining'] ? __( 'Unlimited downloads' ) : trans_choice( ':count download left|:count downloads left', $download['remaining'], [ 'count' => $download['remaining'] ] ) }}@if ( null !== $download['expires'] )<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span>{{ __( 'Available until :date', [ 'date' => $download['expires'] ] ) }}@endif
                        </x-slot:description>
                    </x-artisanpack-list-item>
                @endforeach
            </x-artisanpack-list>

            @if ( null === ( $downloadsUrl ?? null ) )
                <p class="ec-order-card__footnote" data-order-downloads-email>{{ __( 'We\'ve emailed your download links to :email.', [ 'email' => $order->email ] ) }}</p>
            @endif
        </x-artisanpack-card>
    @endif

    <div class="ec-order-details__pair">
        <x-artisanpack-card class="ec-order-card" role="region" aria-labelledby="ec-order-addresses-heading" data-order-addresses>
            <h2 id="ec-order-addresses-heading" class="ec-order-card__title">{{ __( 'Addresses and payment' ) }}</h2>

            <dl class="ec-order-meta">
                @if ( is_array( $order->shipping_address ) )
                    <div class="ec-order-meta__item" data-order-shipping-address>
                        <dt>{{ __( 'Shipping address' ) }}</dt>
                        <dd><x-artisanpack-ec-address :address="$order->shipping_address" /></dd>
                    </div>
                @endif

                @if ( is_array( $order->billing_address ) )
                    <div class="ec-order-meta__item" data-order-billing-address>
                        <dt>{{ __( 'Billing address' ) }}</dt>
                        <dd>
                            @if ( $ecommerceSameAddress )
                                <span data-order-billing-same>{{ __( 'Same as shipping' ) }}</span>
                            @else
                                <x-artisanpack-ec-address :address="$order->billing_address" />
                            @endif
                        </dd>
                    </div>
                @endif

                <div class="ec-order-meta__item">
                    <dt>{{ __( 'Payment method' ) }}</dt>
                    <dd class="ec-order-meta__with-icon" data-order-payment-method>
                        @if ( null !== $paymentMethod )
                            <x-artisanpack-icon name="credit-card" />
                        @endif
                        <span>{{ $paymentMethod ?? __( 'No payment needed' ) }}</span>
                    </dd>
                </div>

                <div class="ec-order-meta__item">
                    <dt>{{ __( 'Email' ) }}</dt>
                    <dd class="ec-order-meta__email"><bdi>{{ $order->email }}</bdi></dd>
                </div>
            </dl>

            @if ( '' !== trim( (string) $order->customer_note ) )
                <div class="ec-order-note" role="group" aria-labelledby="ec-order-note-heading" data-order-customer-note>
                    <h3 id="ec-order-note-heading" class="ec-order-note__label">{{ __( 'Your note' ) }}</h3>
                    <p class="ec-order-note__body">{{ $order->customer_note }}</p>
                </div>
            @endif
        </x-artisanpack-card>

        <x-artisanpack-card class="ec-order-card" role="region" aria-labelledby="ec-order-summary-heading" data-order-summary>
            <h2 id="ec-order-summary-heading" class="ec-order-card__title">{{ __( 'Order summary' ) }}</h2>

            @include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => $totals ] )
        </x-artisanpack-card>
    </div>
</div>
