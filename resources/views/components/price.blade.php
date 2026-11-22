{{--
    Price (#74). See Price.

    Design-system text styles only (ec-price in resources/css/storefront.css)
    plus a soft error Badge for the percentage off. Color is never the only
    signal: the sale and original prices carry visually hidden labels, and a
    free price says "Free".

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( null === $price )
    <span {{ $attributes->class( [ 'ec-price' ] ) }} data-size="{{ $size }}" data-price="unavailable">{{ __( 'Price unavailable' ) }}</span>
@else
    <span {{ $attributes->class( [ 'ec-price' ] ) }} data-size="{{ $size }}" data-price @if ( $price->onSale() ) data-on-sale @endif>
        @if ( $price->isRange() )
            @if ( $from )
                {{-- The sentence is escaped first; only the amount's own markup goes in raw. --}}
                <span class="ec-price__from">{!! str_replace( ':price', '<span class="ec-price__amount"' . ( $price->onSale() ? ' data-tone="sale"' : '' ) . '>' . e( $format( $price->minPrice ) ) . '</span>', e( __( 'From :price' ) ) ) !!}</span>
            @else
                <span class="apui-sr-only">{{ __( 'From :min to :max', [ 'min' => $format( $price->minPrice ), 'max' => $format( $price->maxPrice ) ] ) }}</span>
                <span class="ec-price__amount" @if ( $price->onSale() ) data-tone="sale" @endif aria-hidden="true">{{ $format( $price->minPrice ) }} &ndash; {{ $format( $price->maxPrice ) }}</span>
            @endif

            @if ( $price->onSale() )
                <span class="apui-sr-only">{{ __( 'On sale' ) }}</span>
            @endif
        @elseif ( $price->onSale() )
            <ins class="ec-price__amount" data-tone="sale"><span class="apui-sr-only">{{ __( 'Sale price' ) }} </span>{{ $format( $price->price ) }}</ins>
            <del class="ec-price__was"><span class="apui-sr-only">{{ __( 'Original price' ) }} </span>{{ $format( $price->compareAt ) }}</del>

            @if ( $saleBadge && null !== ( $ecommercePercentOff = $percentOff() ) )
                <x-artisanpack-badge color="error" size="sm" data-price-discount>{{ __( ':percent% off', [ 'percent' => $ecommercePercentOff ] ) }}</x-artisanpack-badge>
            @endif
        @elseif ( $isFree() )
            <span class="ec-price__amount" data-tone="free">{{ __( 'Free' ) }}</span>
        @else
            <span class="ec-price__amount">{{ $format( $price->price ) }}</span>
        @endif

        @if ( null !== ( $taxNote = $taxNote() ) )
            <span class="ec-price__note">{{ $taxNote }}</span>
        @endif
    </span>
@endif
