{{--
    Display price. See Price.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( null === $price )
    <span {{ $attributes->class( [ 'text-base-content/70' ] ) }} data-price="unavailable">{{ __( 'Price unavailable' ) }}</span>
@else
    <span {{ $attributes->class( [ 'inline-flex flex-wrap items-baseline gap-x-2 gap-y-1' ] ) }} data-price @if ( $price->onSale() ) data-on-sale @endif>
        @if ( $price->isRange() )
            <span class="sr-only">{{ __( 'From :min to :max', [ 'min' => $format( $price->minPrice ), 'max' => $format( $price->maxPrice ) ] ) }}</span>
            <span class="font-semibold tabular-nums" aria-hidden="true">{{ $format( $price->minPrice ) }} &ndash; {{ $format( $price->maxPrice ) }}</span>

            @if ( $price->onSale() )
                <span class="sr-only">{{ __( 'On sale' ) }}</span>
            @endif
        @elseif ( $price->onSale() )
            <span class="sr-only">{{ __( 'Sale price: was :was, now :now', [ 'was' => $format( $price->compareAt ), 'now' => $format( $price->price ) ] ) }}</span>
            <del class="tabular-nums text-base-content/70" aria-hidden="true">{{ $format( $price->compareAt ) }}</del>
            <ins class="font-semibold tabular-nums no-underline" aria-hidden="true">{{ $format( $price->price ) }}</ins>
        @else
            <span class="font-semibold tabular-nums">{{ $format( $price->price ) }}</span>
        @endif

        @if ( $saleBadge && $price->onSale() )
            <x-artisanpack-badge :value="__( 'Sale' )" class="badge-error badge-sm" aria-hidden="true" />
        @endif

        @if ( null !== ( $taxNote = $taxNote() ) )
            <span class="text-sm text-base-content/70">{{ $taxNote }}</span>
        @endif
    </span>
@endif
