{{--
    Star rating input (#109). See RatingInput.

    The design-system Rating's interactive markup (`data-interactive`, lg:
    28px stars) inside a radio-group fieldset whose legend names it. Each
    star is a label holding a native radio named "1 star" … "5 stars", so
    the group is a radiogroup that arrow keys move through, and wire:model
    binds natively. Hover previews the value (stars and words); the chosen
    value shows as "3 of 5", so it isn't shown by color alone. An error
    follows in the radio group's error style, linked with
    `aria-describedby`.

    The markup is written out rather than taken from `x-artisanpack-rating`,
    which names its radios "3 out of 5" and its group with a hidden label.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceHasError = null !== $error && '' !== $error )
<fieldset
    {{ $attributes->whereDoesntStartWith( 'wire:model' )->class( [ 'apui-radio-group ec-rating-input' ] ) }}
    id="{{ $id }}"
    role="radiogroup"
    @if ( $required ) aria-required="true" @endif
    @if ( $ecommerceHasError ) data-invalid aria-describedby="{{ $id }}-error" @endif
    x-data="{ rating: 0, preview: 0 }"
    x-init="$nextTick( () => rating = Number( $el.querySelector( 'input:checked' )?.value ?? 0 ) )"
    x-on:change="rating = Number( $event.target.value )"
    data-rating-input="{{ $id }}"
>
    <legend data-scope="radio-group" data-part="label">{{ $label ?? __( 'Rating' ) }}@if ( $required )<span data-scope="radio-group" data-part="required" aria-hidden="true">*</span>@endif</legend>

    <div class="ec-rating-input__row">
        <div class="apui-rating ec-rating-input__stars" data-size="lg" data-interactive x-on:mouseleave="preview = 0">
            @for ( $i = 1; $i <= 5; $i++ )
                <label data-scope="rating" data-part="item" x-on:mouseenter="preview = {{ $i }}">
                    <input
                        type="radio"
                        data-scope="rating"
                        data-part="control"
                        id="{{ $id }}-{{ $i }}"
                        name="{{ $id }}"
                        value="{{ $i }}"
                        aria-label="{{ trans_choice( ':count star|:count stars', $i, [ 'count' => $i ] ) }}"
                        {{ $attributes->whereStartsWith( 'wire:model' ) }}
                        @if ( $required && 1 === $i ) required @endif
                        @if ( $ecommerceHasError ) aria-invalid="true" @endif
                    >
                    <span data-scope="rating" data-part="star" aria-hidden="true">{{ $starIcon }}</span>
                </label>
            @endfor
        </div>

        <span
            class="ec-rating-input__value"
            aria-hidden="true"
            x-show="( preview || rating ) > 0"
            x-text="@js( __( ':rating of 5' ) ).replace( ':rating', preview || rating )"
            x-cloak
            data-rating-input-value
        ></span>
    </div>

    @if ( $ecommerceHasError )
        <p data-scope="radio-group" data-part="error" id="{{ $id }}-error" data-rating-input-error>{{ $errorIcon }}{{ $error }}</p>
    @endif
</fieldset>
