{{--
    A placed order's details, shared by the confirmation page and the order
    detail: the lines, totals, addresses, payment method, and downloads.

    Takes `$order`, and from DescribesOrder `$lines`, `$totals`,
    `$paymentMethod`, and `$downloads`. `$downloadsUrl` links the downloads
    to the shopper's account; without it guests are told the links are in
    their email.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start" data-order-details>
    <div class="flex flex-col gap-6">
        <section class="flex flex-col gap-3" aria-labelledby="ec-order-items-heading">
            <h2 id="ec-order-items-heading" class="text-xl font-bold">{{ __( 'Items' ) }}</h2>

            <ul class="flex flex-col divide-y divide-base-content/10 rounded-box border border-base-content/10" data-order-lines>
                @foreach ( $lines as $line )
                    <li class="flex items-start justify-between gap-4 p-4" wire:key="order-line-{{ $line['id'] }}" data-order-line="{{ $line['id'] }}">
                        <div>
                            <p class="font-semibold">{{ $line['name'] }} <span class="font-normal text-base-content/70">&times; {{ $line['quantity'] }}</span></p>

                            @if ( null !== $line['variant'] )
                                <p class="text-sm text-base-content/70">{{ $line['variant'] }}</p>
                            @endif

                            @if ( null !== $line['sku'] )
                                <p class="text-sm text-base-content/70">{{ __( 'SKU: :sku', [ 'sku' => $line['sku'] ] ) }}</p>
                            @endif
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
        </section>

        <section class="grid gap-6 sm:grid-cols-2" aria-label="{{ __( 'Addresses and payment' ) }}" data-order-addresses>
            @if ( is_array( $order->shipping_address ) )
                <div>
                    <h2 class="font-semibold">{{ __( 'Shipping address' ) }}</h2>
                    <x-artisanpack-ec-address :address="$order->shipping_address" />
                </div>
            @endif

            @if ( is_array( $order->billing_address ) )
                <div>
                    <h2 class="font-semibold">{{ __( 'Billing address' ) }}</h2>
                    <x-artisanpack-ec-address :address="$order->billing_address" />
                </div>
            @endif

            <div>
                <h2 class="font-semibold">{{ __( 'Payment method' ) }}</h2>
                <p data-order-payment-method>{{ $paymentMethod ?? __( 'No payment needed' ) }}</p>
            </div>

            <div>
                <h2 class="font-semibold">{{ __( 'Email' ) }}</h2>
                <p>{{ $order->email }}</p>
            </div>
        </section>

        @if ( [] !== $downloads )
            <section class="flex flex-col gap-3" aria-labelledby="ec-order-downloads-heading" data-order-downloads>
                <h2 id="ec-order-downloads-heading" class="text-xl font-bold">{{ __( 'Downloads' ) }}</h2>

                <ul class="flex flex-col gap-2">
                    @foreach ( $downloads as $download )
                        <li class="flex flex-wrap items-center justify-between gap-2" wire:key="order-download-{{ $download['id'] }}" data-order-download="{{ $download['id'] }}">
                            <span class="font-semibold">{{ $download['name'] }}</span>
                            <span class="text-sm text-base-content/70">
                                {{ null === $download['remaining'] ? __( 'Unlimited downloads' ) : trans_choice( ':count download left|:count downloads left', $download['remaining'], [ 'count' => $download['remaining'] ] ) }}
                                @if ( null !== $download['expires'] )
                                    &middot; {{ __( 'Available until :date', [ 'date' => $download['expires'] ] ) }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>

                @if ( null !== ( $downloadsUrl ?? null ) )
                    <div>
                        <x-artisanpack-button :label="__( 'Go to your downloads' )" :link="$downloadsUrl" icon="o-arrow-down-tray" class="btn-sm" data-order-downloads-link />
                    </div>
                @else
                    <p class="text-sm text-base-content/70" data-order-downloads-email>{{ __( 'We\'ve emailed your download links to :email.', [ 'email' => $order->email ] ) }}</p>
                @endif
            </section>
        @endif

        @if ( '' !== trim( (string) $order->customer_note ) )
            <section aria-labelledby="ec-order-note-heading" data-order-customer-note>
                <h2 id="ec-order-note-heading" class="font-semibold">{{ __( 'Your note' ) }}</h2>
                <p class="whitespace-pre-line">{{ $order->customer_note }}</p>
            </section>
        @endif
    </div>

    <aside class="flex flex-col gap-4 rounded-box border border-base-content/10 p-5" aria-labelledby="ec-order-summary-heading" data-order-summary>
        <h2 id="ec-order-summary-heading" class="text-xl font-bold">{{ __( 'Order summary' ) }}</h2>

        @include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => $totals ] )

        @if ( $totals['refunded'] > 0 )
            <p class="flex justify-between gap-4 text-sm" data-order-refunded>
                <span>{{ __( 'Refunded' ) }}</span>
                <span><span class="sr-only">{{ __( 'Minus' ) }}</span><span aria-hidden="true">&minus;</span><x-artisanpack-ec-money :amount="$totals['refunded']" :currency="$totals['currency']" /></span>
            </p>
        @endif
    </aside>
</div>
