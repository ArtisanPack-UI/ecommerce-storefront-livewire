{{--
    Swatch selector (#78). See Swatches.

    A fieldset of native radios (one value, as on the product page) or
    checkboxes (many, as in the catalog filters), each drawn as a selectable
    chip (resources/css/storefront.css, ec-swatch): 40px tall, a 24px round
    color or image dot before the value's name, or the name alone, centered,
    for text values such as sizes. The checked chip takes the selected
    background and border. An unavailable value stays in place, faded and
    struck through, with its reason in hidden text. With one value chosen,
    the legend names it ("Glaze: Stoneware").

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceModel = $attributes->whereStartsWith( 'wire:model' ) )
@php( $ecommerceChosen = $multiple ? null : $chosenLabel() )
<fieldset {{ $attributes->whereDoesntStartWith( 'wire:model' )->class( [ 'ec-swatches' ] ) }} data-swatches="{{ $name }}">
    <legend class="ec-swatches__legend">{{ $legend }}@if ( null !== $ecommerceChosen )<span class="ec-swatches__chosen" data-swatches-chosen>: {{ $ecommerceChosen }}</span>@endif</legend>

    <div class="ec-swatches__options">
        @foreach ( $items as $index => $item )
            @php( $ecommerceInputId = $idPrefix . '-' . $index )
            @php( $ecommerceHasDot = null !== $item['color'] || null !== $item['image'] )
            <label
                for="{{ $ecommerceInputId }}"
                class="ec-swatch"
                wire:key="{{ $ecommerceInputId }}"
                @if ( $ecommerceHasDot ) data-dot @endif
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

                @if ( null !== $item['reason'] )
                    <span class="apui-sr-only">({{ $item['reason'] }})</span>
                @endif
            </label>
        @endforeach
    </div>
</fieldset>
