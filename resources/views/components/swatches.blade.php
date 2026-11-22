{{--
    Swatch selector (#78). See Swatches.

    A fieldset of native radios (one value, as on the product page) or
    checkboxes (many, as in the catalog filters), each drawn as a selectable
    chip (resources/css/storefront.css, ec-swatch): 40px tall, a 24px round
    color or image dot before the value's name, or the name alone, centered,
    for text values such as sizes; an option's `meta` (a price difference)
    follows the name, muted. The checked chip takes the selected
    background and border. An unavailable value stays in place, faded and
    struck through, with its reason in hidden text. A sold-out value that
    stays selectable (`soldOut` without `disabled`, while back-in-stock
    signups are on) is struck through too, but at full strength and
    clickable. With one value chosen,
    the legend names it ("Glaze: Stoneware"). A `required` group has a "*"
    after the legend (hidden from screen readers, which hear the radios'
    `required`), and an `error` shows under the chips with the error icon,
    linked to the group and marking its inputs invalid. A group with an
    error can take focus (`tabindex="-1"`), so a form can move the shopper
    to it even when every option is disabled.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceModel       = $attributes->whereStartsWith( 'wire:model' );
    $ecommerceChosen      = $multiple ? null : $chosenLabel();
    $ecommerceDescribedBy = trim( implode( ' ', array_filter( [ $attributes->get( 'aria-describedby' ), null !== $error ? $errorId() : null ] ) ) );
@endphp
<fieldset
    {{ $attributes->whereDoesntStartWith( 'wire:model' )->except( 'aria-describedby' )->class( [ 'ec-swatches' ] ) }}
    data-swatches="{{ $name }}"
    @if ( '' !== $ecommerceDescribedBy ) aria-describedby="{{ $ecommerceDescribedBy }}" @endif
    @if ( null !== $error ) data-invalid tabindex="-1" @endif
>
    <legend class="ec-swatches__legend">{{ $legend }}@if ( null !== $ecommerceChosen )<span class="ec-swatches__chosen" data-swatches-chosen>: {{ $ecommerceChosen }}</span>@endif{!! $required ? '<span class="ec-swatches__required" aria-hidden="true">*</span>' : '' !!}</legend>

    <div class="ec-swatches__options">
        @foreach ( $items as $index => $item )
            @php( $ecommerceInputId = $idPrefix . '-' . $index )
            @php( $ecommerceHasDot = null !== $item['color'] || null !== $item['image'] )
            <label
                for="{{ $ecommerceInputId }}"
                class="ec-swatch"
                wire:key="{{ $ecommerceInputId }}"
                @if ( $ecommerceHasDot ) data-dot @endif
                @if ( $item['soldOut'] ) data-sold-out @endif
                @if ( null !== $item['reason'] ) title="{{ $item['reason'] }}" @endif
            >
                <input
                    id="{{ $ecommerceInputId }}"
                    type="{{ $multiple ? 'checkbox' : 'radio' }}"
                    name="{{ $multiple ? $name . '[]' : $name }}"
                    value="{{ $item['value'] }}"
                    class="ec-swatch__input"
                    {{ $ecommerceModel }}
                    @checked( $item['selected'] )
                    @disabled( $item['disabled'] )
                    @if ( $required && ! $multiple ) required @endif
                    @if ( null !== $error ) aria-invalid="true" @endif
                    data-swatch-value="{{ $item['value'] }}"
                >

                @if ( null !== $item['color'] )
                    <span class="ec-swatch__dot" style="background-color: {{ $item['color'] }}" aria-hidden="true"></span>
                @elseif ( null !== $item['image'] )
                    <img src="{{ $item['image'] }}" alt="" class="ec-swatch__dot" aria-hidden="true">
                @endif

                <span class="ec-swatch__label">{{ $item['label'] }}</span>

                @if ( null !== $item['count'] )
                    <span class="ec-swatch__count">({{ $item['count'] }})</span>
                @endif

                @if ( null !== $item['meta'] )
                    <span class="ec-swatch__count" data-swatch-meta>{{ $item['meta'] }}</span>
                @endif

                @if ( null !== $item['reason'] )
                    <span class="apui-sr-only">({{ $item['reason'] }})</span>
                @endif
            </label>
        @endforeach
    </div>

    @if ( null !== $error )
        <p id="{{ $errorId() }}" class="ec-swatches__error" data-swatches-error><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $error }}</p>
    @endif
</fieldset>
