{{--
    One of the shopper's orders. See Livewire\Account\OrderShow.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-8" data-account-order="{{ $order->id }}" @if ( $readOnly ) data-account-order-read-only @endif>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
            <div>
                <dt class="text-base-content/70">{{ __( 'Order number' ) }}</dt>
                <dd class="text-lg font-semibold" data-order-number>{{ $order->order_number }}</dd>
            </div>

            <div>
                <dt class="text-base-content/70">{{ __( 'Placed' ) }}</dt>
                <dd>{{ $date }}</dd>
            </div>

            <div>
                <dt class="text-base-content/70">{{ __( 'Status' ) }}</dt>
                <dd><x-artisanpack-badge :value="$status" class="{{ $statusColor }}" data-order-status /></dd>
            </div>
        </dl>

        <div class="flex flex-wrap gap-2">
            @if ( null !== $ordersUrl )
                <x-artisanpack-button :label="__( 'All orders' )" :link="$ordersUrl" icon="o-arrow-left" class="btn-ghost btn-sm" data-account-order-back />
            @endif

            @if ( $canBuyAgain )
                <x-artisanpack-button
                    :label="__( 'Buy again' )"
                    icon="o-arrow-path"
                    color="primary"
                    class="btn-sm"
                    wire:click="buyAgain"
                    spinner="buyAgain"
                    wire:loading.attr="disabled"
                    wire:target="buyAgain"
                    data-account-order-buy-again
                />
            @endif
        </div>
    </div>

    @if ( [] !== $shipments )
        <section class="flex flex-col gap-3" aria-labelledby="ec-order-shipments-heading" data-order-shipments>
            <h2 id="ec-order-shipments-heading" class="text-xl font-bold">{{ __( 'Shipments' ) }}</h2>

            <ul class="flex flex-col gap-3">
                @foreach ( $shipments as $shipment )
                    <li class="flex flex-wrap items-center justify-between gap-4 rounded-box border border-base-content/10 p-4" wire:key="order-shipment-{{ $shipment['id'] }}" data-order-shipment="{{ $shipment['id'] }}">
                        <div>
                            <p class="font-semibold">{{ $shipment['carrier'] ?? __( 'Shipment' ) }} &middot; {{ $shipment['status'] }}</p>

                            @if ( null !== $shipment['shipped'] )
                                <p class="text-sm text-base-content/70">{{ __( 'Shipped :date', [ 'date' => $shipment['shipped'] ] ) }}</p>
                            @endif

                            @if ( null !== $shipment['tracking'] )
                                <p class="text-sm">{{ __( 'Tracking number: :number', [ 'number' => $shipment['tracking'] ] ) }}</p>
                            @endif
                        </div>

                        @if ( null !== $shipment['url'] )
                            <a href="{{ $shipment['url'] }}" class="btn btn-sm" target="_blank" rel="noopener noreferrer nofollow" data-order-tracking-link>
                                {{ __( 'Track package' ) }}
                                <span class="sr-only">{{ __( '(opens in a new tab)' ) }}</span>
                                <x-artisanpack-icon name="o-arrow-top-right-on-square" class="h-4 w-4" aria-hidden="true" />
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @include( 'ecommerce-storefront::partials.order-details' )

    @if ( [] !== $refunds )
        <section class="flex flex-col gap-3" aria-labelledby="ec-order-refunds-heading" data-order-refunds>
            <h2 id="ec-order-refunds-heading" class="text-xl font-bold">{{ __( 'Refunds' ) }}</h2>

            <ul class="flex flex-col gap-2">
                @foreach ( $refunds as $refund )
                    <li class="flex flex-wrap justify-between gap-4" wire:key="order-refund-{{ $refund['id'] }}" data-order-refund="{{ $refund['id'] }}">
                        <span>{{ $refund['date'] }} @if ( $refund['pending'] ) &middot; {{ __( 'Pending' ) }} @endif</span>
                        <x-artisanpack-ec-money :amount="$refund['amount']" :currency="$refund['currency']" class="font-semibold" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ( [] !== $notes )
        <section class="flex flex-col gap-3" aria-labelledby="ec-order-notes-heading" data-order-notes>
            <h2 id="ec-order-notes-heading" class="text-xl font-bold">{{ __( 'Messages from the store' ) }}</h2>

            <ul class="flex flex-col gap-3">
                @foreach ( $notes as $note )
                    <li class="rounded-box border border-base-content/10 p-4" wire:key="order-note-{{ $note['id'] }}" data-order-note="{{ $note['id'] }}">
                        <p class="text-sm text-base-content/70">{{ $note['date'] }}</p>
                        <p class="whitespace-pre-line">{{ $note['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
