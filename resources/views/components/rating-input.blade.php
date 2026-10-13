{{--
    Star rating input. See RatingInput.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceHasError = null !== $error && '' !== $error )
<fieldset
    {{ $attributes->whereDoesntStartWith( 'wire:model' )->class( [ 'flex flex-col gap-1' ] ) }}
    @if ( $ecommerceHasError ) aria-describedby="{{ $id }}-error" @endif
    x-data="{ rating: 0 }"
    x-init="$nextTick( () => rating = Number( $el.querySelector( 'input:checked' )?.value ?? 0 ) )"
    x-on:change="rating = Number( $event.target.value )"
    data-rating-input="{{ $id }}"
>
    <legend class="mb-1 text-sm font-medium">
        {{ $label ?? __( 'Rating' ) }}
        @if ( $required )
            <span aria-hidden="true">*</span>
        @endif
    </legend>

    {{--
        A visible ring while a star has keyboard focus (daisyUI only scales
        it), unchosen stars in the text colour at half strength rather than
        a faint yellow (non-text contrast), and the choice in words, so it
        isn't shown by colour alone.
    --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="rating rating-lg gap-1 rounded-box has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-4 has-[:focus-visible]:outline-primary [&>input]:bg-base-content [&>input]:opacity-50 [&>input:checked]:bg-warning [&>input:checked]:opacity-100 [&>input:has(~input:checked)]:bg-warning [&>input:has(~input:checked)]:opacity-100">
            @for ( $i = 1; $i <= 5; $i++ )
                <input
                    id="{{ $id }}-{{ $i }}"
                    type="radio"
                    name="{{ $id }}"
                    value="{{ $i }}"
                    class="mask mask-star-2"
                    aria-label="{{ trans_choice( ':count star|:count stars', $i, [ 'count' => $i ] ) }}"
                    {{ $attributes->whereStartsWith( 'wire:model' ) }}
                    @if ( $required && 1 === $i ) required @endif
                    @if ( $ecommerceHasError ) aria-invalid="true" @endif
                >
            @endfor
        </div>

        <span class="text-sm tabular-nums" aria-hidden="true" x-show="rating > 0" x-text="@js( __( ':rating out of 5' ) ).replace( ':rating', rating )" x-cloak data-rating-input-value></span>
    </div>

    @if ( $ecommerceHasError )
        <p id="{{ $id }}-error" class="text-sm text-[color-mix(in_oklab,var(--color-error)_55%,var(--color-base-content))]" data-rating-input-error>{{ $error }}</p>
    @endif
</fieldset>
