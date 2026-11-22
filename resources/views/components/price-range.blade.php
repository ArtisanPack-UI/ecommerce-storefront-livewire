{{--
    Price range (#108). See PriceRange.

    The legend, the design-system Range with two thumbs, then the lowest
    and highest price as Inputs with the currency symbol as their prefix and
    "to" between them. The thumbs and inputs stay in sync: dragging a thumb
    rewrites its input, and a typed amount (rounded to the step counted from
    `min`, kept inside the range and on its side of the other bound) moves
    its thumb. A bound from the URL outside the range shows at the range's
    end, without changing the parent's value until the shopper moves it.

    Prices are in minor units on the server and the thumbs, and in major
    units in the inputs. `from` and `to` place them for the first render,
    before Alpine starts. Both bounds are entangled (deferred) and sent when
    a thumb is released or an input changes, so dragging doesn't send a
    request per pixel; a bound at its end of the range is null, which keeps
    it out of the URL.

    Each thumb is a native range input (role "slider") named by the legend
    and "Lowest price" / "Highest price", with the amount as its value text;
    each input has the same name as a hidden label.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<fieldset
    {{ $attributes->class( [ 'ec-price-range' ] ) }}
    x-data="{
        from: $wire.entangle( @js( $fromModel ) ),
        to: $wire.entangle( @js( $toModel ) ),
        bounds: { min: {{ $min }}, max: {{ $max }}, step: {{ $step }}, divisor: {{ $divisor }}, decimals: {{ $decimals }} },
        money( amount ) {
            try {
                return new Intl.NumberFormat( document.documentElement.lang || undefined, { style: 'currency', currency: @js( $currency ) } ).format( amount / this.bounds.divisor );
            } catch ( error ) {
                return String( amount / this.bounds.divisor );
            }
        },
        major( amount ) {
            return ( amount / this.bounds.divisor ).toFixed( this.bounds.decimals );
        },
        low() {
            return Math.min( this.bounds.max, Math.max( this.bounds.min, Number( this.from ?? this.bounds.min ) ) );
        },
        high() {
            return Math.max( this.low(), Math.min( this.bounds.max, Number( this.to ?? this.bounds.max ) ) );
        },
        slider() {
            return Alpine.$data( this.$root.querySelector( '.apui-range' ) );
        },
        sync() {
            const slider = this.slider();

            if ( slider ) {
                slider.value = [ this.low(), this.high() ];
            }
        },
        setFrom( value ) {
            value = Math.max( this.bounds.min, Math.min( Number( value ), this.high() ) );
            this.from = value <= this.bounds.min ? null : value;
        },
        setTo( value ) {
            value = Math.min( this.bounds.max, Math.max( Number( value ), this.low() ) );
            this.to = value >= this.bounds.max ? null : value;
        },
        slid( event ) {
            if ( 'range' !== event.target.dataset.scope || 'control' !== event.target.dataset.part ) {
                return;
            }

            'start' === event.target.dataset.thumb ? this.setFrom( event.target.value ) : this.setTo( event.target.value );
        },
        typed( event, bound ) {
            const amount = Number.parseFloat( String( event.target.value ).replace( ',', '.' ) );

            if ( Number.isFinite( amount ) ) {
                const minor = this.bounds.min + Math.round( ( amount * this.bounds.divisor - this.bounds.min ) / this.bounds.step ) * this.bounds.step;

                'from' === bound ? this.setFrom( minor ) : this.setTo( minor );
            }

            event.target.value = this.major( 'from' === bound ? this.low() : this.high() );
            this.sync();
            this.send();
        },
        send() {
            clearTimeout( this.timer );
            this.timer = setTimeout( () => this.$wire.$refresh(), 400 );
        },
        init() {
            this.$nextTick( () => this.sync() );
            this.$watch( 'from', () => this.sync() );
            this.$watch( 'to', () => this.sync() );
        },
    }"
    x-on:input="slid( $event )"
    data-price-range
>
    @if ( null !== $label )
        <legend id="{{ $idPrefix }}-legend" class="ec-price-range__legend">{{ $label }}</legend>
    @endif

    <x-artisanpack-range
        dual
        show-value="never"
        :min="$min"
        :max="$max"
        :step="$step"
        :value="[ $from ?? $min, $to ?? $max ]"
        :thumb-labels="[ 'start' => __( 'Lowest price' ), 'end' => __( 'Highest price' ) ]"
        :aria-labelledby="null !== $label ? $idPrefix . '-legend' : null"
        :id="$idPrefix . '-range'"
        class="ec-price-range__slider"
        x-bind:aria-valuetext="money( 'start' === $el.dataset.thumb ? low() : high() )"
        x-on:change="send()"
        data-price-slider
    />

    <div class="ec-price-range__inputs">
        <x-artisanpack-input
            :id="$idPrefix . '-min'"
            type="number"
            inputmode="decimal"
            size="sm"
            :prefix="$symbol"
            :min="$min / $divisor"
            :max="$max / $divisor"
            :step="$step / $divisor"
            :aria-label="__( 'Lowest price' )"
            :value="number_format( ( $from ?? $min ) / $divisor, $decimals, '.', '' )"
            class="ec-price-range__input"
            x-bind:value="major( low() )"
            x-on:change="typed( $event, 'from' )"
            data-price-min
        />

        <span class="ec-price-range__to" aria-hidden="true">{{ __( 'to' ) }}</span>

        <x-artisanpack-input
            :id="$idPrefix . '-max'"
            type="number"
            inputmode="decimal"
            size="sm"
            :prefix="$symbol"
            :min="$min / $divisor"
            :max="$max / $divisor"
            :step="$step / $divisor"
            :aria-label="__( 'Highest price' )"
            :value="number_format( ( $to ?? $max ) / $divisor, $decimals, '.', '' )"
            class="ec-price-range__input"
            x-bind:value="major( high() )"
            x-on:change="typed( $event, 'to' )"
            data-price-max
        />
    </div>
</fieldset>
