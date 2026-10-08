{{--
    The order history. See Livewire\Account\Orders.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6" data-account-orders>
    <div class="max-w-xs">
        <x-artisanpack-select
            id="ec-account-orders-status"
            :label="__( 'Show' )"
            :options="$filters"
            wire:model.live="status"
            data-account-orders-filter
        />
    </div>

    @if ( $failed )
        <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft" :title="__( 'Your orders couldn\'t be loaded' )" :description="__( 'Try again in a moment.' )" data-account-orders-failed />
    @elseif ( null === $orders || 0 === $orders->total() )
        @if ( '' !== $status )
            <x-artisanpack-ec-empty-state icon="o-funnel" :title="__( 'No orders match this filter' )" data-account-orders-empty="filtered">
                <x-artisanpack-button :label="__( 'Show all orders' )" wire:click="$set( 'status', '' )" />
            </x-artisanpack-ec-empty-state>
        @else
            <x-artisanpack-ec-empty-state icon="o-shopping-bag" :title="__( 'No orders yet' )" :description="__( 'Orders you place will appear here.' )" data-account-orders-empty="none">
                @if ( null !== $catalogUrl )
                    <x-artisanpack-button :label="__( 'Start shopping' )" :link="$catalogUrl" color="primary" />
                @endif
            </x-artisanpack-ec-empty-state>
        @endif
    @else
        <x-artisanpack-table :headers="$headers" :rows="$orders->items()" data-account-orders-table>
            @scope( 'cell_number', $row )
                @if ( null !== $row['url'] )
                    <a href="{{ $row['url'] }}" class="link font-semibold" data-account-order-link="{{ $row['id'] }}">{{ $row['number'] }}</a>
                @else
                    <span class="font-semibold">{{ $row['number'] }}</span>
                @endif
            @endscope

            @scope( 'cell_status', $row )
                <x-artisanpack-badge :value="$row['status']" class="{{ $row['color'] }}" />
            @endscope

            @scope( 'cell_items', $row )
                {{ trans_choice( ':count item|:count items', $row['items'], [ 'count' => $row['items'] ] ) }}
            @endscope

            @scope( 'cell_total', $row )
                <x-artisanpack-ec-money :amount="$row['total']" :currency="$row['currency']" />
            @endscope
        </x-artisanpack-table>

        <x-artisanpack-pagination :rows="$orders" hide-per-page data-account-orders-pagination />
    @endif
</div>
