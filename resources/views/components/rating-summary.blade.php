{{--
    Rating summary. See RatingSummary.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( $count > 0 )
    <span {{ $attributes->class( [ 'inline-flex flex-wrap items-center gap-2 text-sm' ] ) }} data-rating-summary>
        {{-- Decorative stars: x-artisanpack-rating is an input and fails to render read-only in livewire-ui-components 2.1. --}}
        <span aria-hidden="true" class="inline-flex">
            @for ( $i = 1; $i <= 5; $i++ )
                <x-artisanpack-icon name="s-star" @class( [ 'h-4 w-4', 'text-warning' => $i <= $stars(), 'text-base-content/20' => $i > $stars() ] ) />
            @endfor
        </span>
        <span class="sr-only">{{ __( 'Rated :rating out of 5', [ 'rating' => $formattedRating() ] ) }}</span>

        @if ( null !== $href && '' !== $href )
            <a href="{{ $href }}" class="link link-hover">{{ trans_choice( ':count review|:count reviews', $count, [ 'count' => $count ] ) }}</a>
        @else
            <span>{{ trans_choice( ':count review|:count reviews', $count, [ 'count' => $count ] ) }}</span>
        @endif
    </span>
@elseif ( ! $hideEmpty )
    <span {{ $attributes->class( [ 'text-sm text-base-content/70' ] ) }} data-rating-summary="empty">{{ __( 'No reviews yet' ) }}</span>
@endif
