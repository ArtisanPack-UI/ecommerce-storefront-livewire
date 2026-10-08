{{--
    Star rating input. See RatingInput.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceHasError = null !== $error && '' !== $error )
<fieldset {{ $attributes->whereDoesntStartWith( 'wire:model' )->class( [ 'flex flex-col gap-1' ] ) }} @if ( $ecommerceHasError ) aria-describedby="{{ $id }}-error" @endif data-rating-input="{{ $id }}">
    <legend class="mb-1 text-sm font-medium">
        {{ $label ?? __( 'Rating' ) }}
        @if ( $required )
            <span aria-hidden="true">*</span>
        @endif
    </legend>

    <div class="rating rating-lg gap-1">
        @for ( $i = 1; $i <= 5; $i++ )
            <input
                id="{{ $id }}-{{ $i }}"
                type="radio"
                name="{{ $id }}"
                value="{{ $i }}"
                class="mask mask-star-2 bg-warning"
                aria-label="{{ trans_choice( ':count star|:count stars', $i, [ 'count' => $i ] ) }}"
                {{ $attributes->whereStartsWith( 'wire:model' ) }}
                @if ( $required && 1 === $i ) required @endif
                @if ( $ecommerceHasError ) aria-invalid="true" @endif
            >
        @endfor
    </div>

    @if ( $ecommerceHasError )
        <p id="{{ $id }}-error" class="text-sm text-error" data-rating-input-error>{{ $error }}</p>
    @endif
</fieldset>
