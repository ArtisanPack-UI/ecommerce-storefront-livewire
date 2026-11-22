{{--
    The order history (#147). See Livewire\Account\Orders.

    "Orders" (the page's h1 with `showTitle`) with the "Show" filter at the
    end, its label beside the select. From 768px the orders are a Table
    (order number linking to the order, placed date, status Badge, item
    count, total and a "View" link) whose footer holds the "Showing 1 to 10
    of 14" summary and the Pagination; below 768px the same orders are a
    divided List in a Card ("Order {number}" over "date · items · total",
    the status Badge at the end), with the Pagination under it. No orders is
    the EmptyState in a Card with "Start shopping" (or "Show all orders"
    when the filter matched nothing); a failure is a warning Alert with
    "Try again" above the heading.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-orders" data-account-orders>
    @if ( $failed )
        <x-artisanpack-alert color="warning" icon="circle-alert" :title="__( 'Your orders couldn\'t load.' )" :description="__( 'Try again in a moment.' )" data-account-orders-failed>
            <x-slot:actions>
                <x-artisanpack-button variant="outline" color="neutral" size="sm" wire:click="$refresh" wire:loading.attr="disabled" data-account-orders-retry>{{ __( 'Try again' ) }}</x-artisanpack-button>
            </x-slot:actions>
        </x-artisanpack-alert>
    @endif

    <div @class( [ 'ec-orders__head', 'ec-orders__head--untitled' => ! $showTitle ] )>
        @if ( $showTitle )
            <h1 class="ec-orders__title">{{ __( 'Orders' ) }}</h1>
        @endif

        <x-artisanpack-select
            placeholder=""
            id="ec-account-orders-status"
            size="sm"
            class="ec-orders__filter"
            :label="__( 'Show' )"
            :options="\ArtisanPackUI\EcommerceStorefrontLivewire\Support\ControlOptions::from( $filters )"
            wire:model.live="status"
            data-account-orders-filter
        />
    </div>

    @if ( $failed )
        {{-- The Alert above says what happened. --}}
    @elseif ( null === $orders || 0 === $orders->total() )
        @if ( '' !== $status )
            <x-artisanpack-ec-empty-state card icon="funnel" :title="__( 'No orders match this filter' )" data-account-orders-empty="filtered">
                <x-artisanpack-button variant="outline" color="neutral" wire:click="$set( 'status', '' )">{{ __( 'Show all orders' ) }}</x-artisanpack-button>
            </x-artisanpack-ec-empty-state>
        @else
            <x-artisanpack-ec-empty-state card icon="package" :title="__( 'No orders yet' )" :description="__( 'Orders you place will appear here.' )" data-account-orders-empty="none">
                @if ( null !== $catalogUrl )
                    <x-artisanpack-button :href="$catalogUrl" color="primary">{{ __( 'Start shopping' ) }}</x-artisanpack-button>
                @endif
            </x-artisanpack-ec-empty-state>
        @endif
    @else
        <x-artisanpack-table
            :headers="$headers"
            :rows="$orders"
            paginated
            :label="__( 'Orders' )"
            :sticky-header="false"
            class="ec-orders__table"
            data-account-orders-table
        >
            @scope( 'cell_number', $row )
                @if ( null !== $row['url'] )
                    <x-artisanpack-link :href="$row['url']" :navigate="false" class="ec-orders__number" data-account-order-link="{{ $row['id'] }}">{{ $row['number'] }}</x-artisanpack-link>
                @else
                    <span class="ec-orders__number">{{ $row['number'] }}</span>
                @endif
            @endscope

            @scope( 'cell_status', $row )
                <x-artisanpack-badge :color="$row['color']" size="sm" data-order-status>{{ $row['status'] }}</x-artisanpack-badge>
            @endscope

            @scope( 'cell_items', $row )
                {{ trans_choice( ':count item|:count items', $row['items'], [ 'count' => $row['items'] ] ) }}
            @endscope

            @scope( 'cell_total', $row )
                <x-artisanpack-ec-money :amount="$row['total']" :currency="$row['currency']" />
            @endscope

            @scope( 'actions', $row )
                @if ( null !== $row['url'] )
                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" :href="$row['url']" data-account-order-view="{{ $row['id'] }}">{{ __( 'View' ) }}<span class="apui-sr-only">: {{ __( 'Order :number', [ 'number' => $row['number'] ] ) }}</span></x-artisanpack-button>
                @endif
            @endscope
        </x-artisanpack-table>

        <div class="ec-orders__compact" data-account-orders-list>
            <x-artisanpack-card class="ec-order-card ec-order-card--flush">
                <x-artisanpack-list variant="divided" :aria-label="__( 'Orders' )">
                    @foreach ( $orders as $row )
                        <x-artisanpack-list-item class="ec-orders__row" wire:key="account-order-row-{{ $row['id'] }}" data-account-order-row="{{ $row['id'] }}">
                            <x-slot:title>
                                @if ( null !== $row['url'] )
                                    <a href="{{ $row['url'] }}" class="ec-orders__row-link">{{ __( 'Order :number', [ 'number' => $row['number'] ] ) }}</a>
                                @else
                                    {{ __( 'Order :number', [ 'number' => $row['number'] ] ) }}
                                @endif
                            </x-slot:title>

                            <x-slot:description>
                                {{ $row['date'] }}<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span>{{ trans_choice( ':count item|:count items', $row['items'], [ 'count' => $row['items'] ] ) }}<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span><x-artisanpack-ec-money :amount="$row['total']" :currency="$row['currency']" />
                            </x-slot:description>

                            <x-slot:trailing>
                                <x-artisanpack-badge :color="$row['color']" size="sm">{{ $row['status'] }}</x-artisanpack-badge>
                            </x-slot:trailing>
                        </x-artisanpack-list-item>
                    @endforeach
                </x-artisanpack-list>
            </x-artisanpack-card>

            @if ( $orders->hasPages() )
                <x-artisanpack-pagination :rows="$orders" data-account-orders-pagination />
            @endif
        </div>
    @endif
</div>
