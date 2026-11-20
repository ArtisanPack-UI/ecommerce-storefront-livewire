{{--
    Rating summary (#77). See RatingSummary.

    The design system's read-only Rating draws the stars (to the nearest
    half); the average follows in label text and the count muted. On product
    cards (`compact`) one star stands in for the row: "4.8 (126)". Everything
    drawn is hidden from screen readers, which hear the whole summary as one
    phrase: the link's name when it links to the reviews, hidden text when it
    doesn't.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( $count > 0 )
    @php( $ecommerceHref = \ArtisanPack\LivewireUiComponents\Support\SafeUrl::href( $href ) )
    @php( $ecommerceLinked = null !== $ecommerceHref && '' !== $ecommerceHref )
    @php( $ecommerceTag = $ecommerceLinked ? 'a' : 'span' )
    <{{ $ecommerceTag }}
        {{ $attributes->class( [ 'ec-rating' ] ) }}
        @if ( $ecommerceLinked ) href="{{ $ecommerceHref }}" aria-label="{{ $spokenLabel() }}" @endif
        @if ( $compact ) data-compact @endif
        data-rating-summary
    >
        @if ( $compact )
            <span class="ec-rating__star" aria-hidden="true" data-rating-star><x-artisanpack-icon name="star" /></span>
        @else
            <span class="ec-rating__stars" aria-hidden="true" data-rating-stars="{{ $starValue() }}">
                <x-artisanpack-rating :value="$starValue()" readonly size="sm" />
            </span>
        @endif

        <span class="ec-rating__average" aria-hidden="true">{{ $formattedRating() }}</span>
        <span class="ec-rating__count" aria-hidden="true">{{ $compact ? __( '(:count)', [ 'count' => $count ] ) : $countText() }}</span>

        @unless ( $ecommerceLinked )
            <span class="apui-sr-only">{{ $spokenLabel() }}</span>
        @endunless
    </{{ $ecommerceTag }}>
@elseif ( ! $hideEmpty )
    <span {{ $attributes->class( [ 'ec-rating', 'ec-rating--empty' ] ) }} data-rating-summary="empty">{{ __( 'No reviews yet' ) }}</span>
@endif
