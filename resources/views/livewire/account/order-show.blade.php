{{--
    One order (#145). See Livewire\Account\OrderShow. Shown inside the
    account, and read-only as a guest's signed order-view link.

    The header: "All orders" (the account) or an "Order lookup" breadcrumb
    (a guest), then "Order {number}" (the page's h1 with `showTitle`, else
    an h2) over "Placed {date} · {n} items · {total}", with the status Badge
    and an outline "Buy again" at the end. Then a Shipment Card per shipment
    (the design-system Steps: Placed, Shipped, In transit, Delivered, with
    their dates, and the carrier, tracking number and "Track package" on a
    sunken panel), the store's messages as a Timeline in a Card, the order
    details, and any refunds.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceOrderHeading = $showTitle ? 'h1' : 'h2';
    $ecommerceOrderItems   = array_sum( array_column( $lines, 'quantity' ) );
@endphp
<div class="ec-order-show" data-account-order="{{ $order->id }}" @if ( $readOnly ) data-account-order-read-only @endif>
    <div class="ec-order-show__header">
        @if ( null !== $ordersUrl )
            <x-artisanpack-link :href="$ordersUrl" standalone leading-icon="arrow-left" :navigate="false" class="ec-order-show__back" data-account-order-back>{{ __( 'All orders' ) }}</x-artisanpack-link>
        @elseif ( null !== $lookupUrl )
            <x-artisanpack-breadcrumbs :navigate="false" data-order-breadcrumbs>
                <x-artisanpack-breadcrumbs-item :label="__( 'Order lookup' )" :href="$lookupUrl" :navigate="false" />
                <x-artisanpack-breadcrumbs-item :label="$order->order_number" />
            </x-artisanpack-breadcrumbs>
        @endif

        <div class="ec-order-show__heading">
            <div class="ec-order-show__titles">
                <{{ $ecommerceOrderHeading }} class="ec-order-show__title" data-order-number>{{ __( 'Order :number', [ 'number' => $order->order_number ] ) }}</{{ $ecommerceOrderHeading }}>
                <p class="ec-order-show__meta">
                    {{ __( 'Placed :date', [ 'date' => $date ] ) }}<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span>{{ trans_choice( ':count item|:count items', $ecommerceOrderItems, [ 'count' => $ecommerceOrderItems ] ) }}<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span><x-artisanpack-ec-money :amount="(int) $order->total_amount" :currency="(string) $order->currency" />
                </p>
            </div>

            <div class="ec-order-show__actions">
                <x-artisanpack-badge :color="$statusColor" :leading-icon="$statusIcon" data-order-status>{{ $status }}</x-artisanpack-badge>

                @if ( $canBuyAgain )
                    <x-artisanpack-button
                        variant="outline"
                        color="neutral"
                        size="sm"
                        leading-icon="rotate-ccw"
                        wire:click="buyAgain"
                        wire:loading.attr="disabled"
                        wire:target="buyAgain"
                        data-account-order-buy-again>{{ __( 'Buy again' ) }}</x-artisanpack-button>
                @endif
            </div>
        </div>
    </div>

    @if ( [] !== $shipments )
        <div class="ec-order-show__shipments" data-order-shipments>
            @foreach ( $shipments as $shipment )
                <x-artisanpack-card class="ec-order-card" role="region" aria-labelledby="ec-order-shipment-{{ $shipment['id'] }}" wire:key="order-shipment-{{ $shipment['id'] }}" data-order-shipment="{{ $shipment['id'] }}">
                    <h2 id="ec-order-shipment-{{ $shipment['id'] }}" class="ec-order-card__title">
                        {{ 1 === count( $shipments ) ? __( 'Shipment' ) : __( 'Shipment :number of :total', [ 'number' => $loop->iteration, 'total' => count( $shipments ) ] ) }}
                    </h2>

                    <x-artisanpack-steps :label="__( 'Shipment progress' )" class="ec-order-shipment__steps" data-order-shipment-steps>
                        @foreach ( $shipment['steps'] as $shipmentStep )
                            <x-artisanpack-step :title="$shipmentStep['label']" :description="$shipmentStep['date']" :state="$shipmentStep['state']" data-order-shipment-step="{{ $shipmentStep['state'] }}" />
                        @endforeach
                    </x-artisanpack-steps>

                    <div class="ec-order-shipment__panel">
                        <div class="ec-order-shipment__details">
                            <p class="ec-order-shipment__carrier">
                                {{ $shipment['carrier'] ?? __( 'Shipment' ) }}<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span>{{ null !== $shipment['shipped'] ? __( 'shipped :date', [ 'date' => $shipment['shipped'] ] ) : $shipment['status'] }}
                            </p>

                            @if ( null !== $shipment['tracking'] )
                                <p class="ec-order-shipment__tracking">{!! str_replace( e( $shipment['tracking'] ), '<span class="ec-order-shipment__tracking-number" data-order-tracking-number>' . e( $shipment['tracking'] ) . '</span>', e( __( 'Tracking number: :number', [ 'number' => $shipment['tracking'] ] ) ) ) !!}</p>
                            @endif
                        </div>

                        @if ( null !== $shipment['url'] )
                            <x-artisanpack-button
                                variant="outline"
                                color="neutral"
                                size="sm"
                                :href="$shipment['url']"
                                trailing-icon="external-link"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                data-order-tracking-link
                            >{{ __( 'Track package' ) }}<span class="apui-sr-only"> {{ __( '(opens in a new tab)' ) }}</span></x-artisanpack-button>
                        @endif
                    </div>
                </x-artisanpack-card>
            @endforeach
        </div>
    @endif

    @if ( [] !== $notes )
        <x-artisanpack-card class="ec-order-card" role="region" aria-labelledby="ec-order-notes-heading" data-order-notes>
            <h2 id="ec-order-notes-heading" class="ec-order-card__title">{{ __( 'Messages from the store' ) }}</h2>

            <x-artisanpack-timeline :label="__( 'Messages from the store' )" class="ec-order-notes">
                @foreach ( $notes as $note )
                    <x-artisanpack-timeline-item icon="mail" :title="$storeName" :time="$note['date']" :datetime="$note['datetime']" :description="$note['body']" wire:key="order-note-{{ $note['id'] }}" data-order-note="{{ $note['id'] }}" />
                @endforeach
            </x-artisanpack-timeline>
        </x-artisanpack-card>
    @endif

    @include( 'ecommerce-storefront::partials.order-details' )

    @if ( [] !== $refunds )
        <x-artisanpack-card class="ec-order-card ec-order-card--flush" role="region" aria-labelledby="ec-order-refunds-heading" data-order-refunds>
            <div class="ec-order-card__head">
                <h2 id="ec-order-refunds-heading" class="ec-order-card__title">{{ __( 'Refunds' ) }}</h2>
            </div>

            <x-artisanpack-list variant="divided" class="ec-order-lines">
                @foreach ( $refunds as $refund )
                    <x-artisanpack-list-item class="ec-order-line" wire:key="order-refund-{{ $refund['id'] }}" data-order-refund="{{ $refund['id'] }}">
                        <x-slot:title>{{ $refund['date'] }}</x-slot:title>

                        @if ( $refund['pending'] )
                            <x-slot:description>{{ __( 'Pending' ) }}</x-slot:description>
                        @endif

                        <x-slot:trailing>
                            <x-artisanpack-ec-money :amount="$refund['amount']" :currency="$refund['currency']" class="ec-order-line__total" />
                        </x-slot:trailing>
                    </x-artisanpack-list-item>
                @endforeach
            </x-artisanpack-list>
        </x-artisanpack-card>
    @endif
</div>
