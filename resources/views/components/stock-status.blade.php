{{--
    Stock status. See StockStatus.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceStockState = $state() )
<span {{ $attributes->class( [ 'inline-flex items-center gap-1 text-sm' ] ) }} data-stock-status="{{ $stock->status }}">
    <x-artisanpack-icon :name="$ecommerceStockState['icon']" class="h-4 w-4 shrink-0 {{ $ecommerceStockState['tint'] }}" aria-hidden="true" />
    <span>{{ $label() }}</span>
</span>
