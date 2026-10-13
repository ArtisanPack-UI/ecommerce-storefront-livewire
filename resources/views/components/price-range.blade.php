{{--
    Price range. See PriceRange.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<fieldset
    {{ $attributes->class( [ 'flex flex-col gap-3' ] ) }}
    x-data="{
        from: $wire.entangle( @js( $fromModel ) ),
        to: $wire.entangle( @js( $toModel ) ),
        min: {{ $min }},
        max: {{ $max }},
        format( amount ) {
            return new Intl.NumberFormat( document.documentElement.lang || undefined, { style: 'currency', currency: @js( $currency ) } ).format( amount / {{ $divisor }} );
        },
        setFrom( value ) {
            value = Math.min( Number( value ), this.to ?? this.max );
            this.from = value <= this.min ? null : value;
        },
        setTo( value ) {
            value = Math.max( Number( value ), this.from ?? this.min );
            this.to = value >= this.max ? null : value;
        },
    }"
    data-price-range
>
    @if ( null !== $label )
        <legend class="mb-2 text-sm font-semibold">{{ $label }}</legend>
    @endif

    <p class="text-sm tabular-nums" aria-hidden="true">
        <span x-text="format( from ?? min )"></span> &ndash; <span x-text="format( to ?? max )"></span>
    </p>

    <div class="flex flex-col gap-1">
        <label for="{{ $idPrefix }}-min" class="text-xs text-base-content/70">{{ __( 'Lowest price' ) }}</label>
        <input
            id="{{ $idPrefix }}-min"
            type="range"
            class="range range-sm"
            min="{{ $min }}"
            max="{{ $max }}"
            step="{{ $step }}"
            :value="from ?? min"
            x-bind:aria-valuetext="format( from ?? min )"
            x-on:input="setFrom( $event.target.value )"
            x-on:change.debounce.400ms="$wire.$refresh()"
            data-price-min
        >
    </div>

    <div class="flex flex-col gap-1">
        <label for="{{ $idPrefix }}-max" class="text-xs text-base-content/70">{{ __( 'Highest price' ) }}</label>
        <input
            id="{{ $idPrefix }}-max"
            type="range"
            class="range range-sm"
            min="{{ $min }}"
            max="{{ $max }}"
            step="{{ $step }}"
            :value="to ?? max"
            x-bind:aria-valuetext="format( to ?? max )"
            x-on:input="setTo( $event.target.value )"
            x-on:change.debounce.400ms="$wire.$refresh()"
            data-price-max
        >
    </div>
</fieldset>
