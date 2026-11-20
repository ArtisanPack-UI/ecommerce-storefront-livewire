{{--
    Stock status (#76). See StockStatus.

    A design-system Badge: soft, with the state's icon and label; on product
    cards (`compact`), a ghost badge with a dot that leaves out "In stock".
    The label always carries the meaning; color and icon only reinforce it.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( $visible() )
    @php( $ecommerceStockState = $state() )
    @if ( $compact )
        <x-artisanpack-badge variant="ghost" :color="$ecommerceStockState['color']" dot {{ $attributes->class( [ 'ec-stock' ] ) }} data-stock-status="{{ $stock->status }}">{{ $label() }}</x-artisanpack-badge>
    @else
        <x-artisanpack-badge variant="soft" :color="$ecommerceStockState['color']" :leading-icon="$ecommerceStockState['icon']" {{ $attributes->class( [ 'ec-stock' ] ) }} data-stock-status="{{ $stock->status }}">{{ $label() }}</x-artisanpack-badge>
    @endif
@endif
