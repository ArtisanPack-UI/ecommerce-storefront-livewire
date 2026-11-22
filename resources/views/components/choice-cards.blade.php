{{--
    Choice cards. See ChoiceCards.

    A fieldset of native radios, each drawn as a card (resources/css/
    storefront.css, ec-choice-card): a 48px image when the option has one,
    the name with its hint under it, and the price difference at the end.
    Cards sit two to a row from 480px. The checked card takes the selected
    background and border, and a check icon so the choice isn't shown by
    color alone. An unavailable option stays in place, faded, with its
    reason under the name. The image is decorative when it has no alt text.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      2.0.0
--}}
@php
    $ecommerceModel       = $attributes->whereStartsWith( 'wire:model' );
    $ecommerceDescribedBy = trim( implode( ' ', array_filter( [ $attributes->get( 'aria-describedby' ), null !== $hint ? $hintId() : null, null !== $error ? $errorId() : null ] ) ) );
@endphp
<fieldset
    {{ $attributes->whereDoesntStartWith( 'wire:model' )->except( 'aria-describedby' )->class( [ 'ec-choice-cards' ] ) }}
    data-choice-cards="{{ $name }}"
    @if ( '' !== $ecommerceDescribedBy ) aria-describedby="{{ $ecommerceDescribedBy }}" @endif
    @if ( null !== $error ) data-invalid tabindex="-1" @endif
>
    <legend class="ec-choice-cards__legend">{{ $legend }}{!! $required ? '<span class="ec-choice-cards__required" aria-hidden="true">*</span>' : '' !!}</legend>

    @if ( null !== $hint )
        <p id="{{ $hintId() }}" class="ec-choice-cards__hint">{{ $hint }}</p>
    @endif

    <div class="ec-choice-cards__options">
        @foreach ( $items as $index => $item )
            @php( $ecommerceInputId = $idPrefix . '-' . $index )
            @php( $ecommerceNoteId = null !== ( $item['reason'] ?? $item['hint'] ) ? $ecommerceInputId . '-note' : null )
            <label for="{{ $ecommerceInputId }}" class="ec-choice-card" wire:key="{{ $ecommerceInputId }}" data-choice-card="{{ $item['value'] }}">
                <input
                    id="{{ $ecommerceInputId }}"
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $item['value'] }}"
                    class="ec-choice-card__input"
                    {{ $ecommerceModel }}
                    @checked( $item['selected'] )
                    @disabled( $item['disabled'] )
                    @if ( $required ) required @endif
                    @if ( null !== $error ) aria-invalid="true" @endif
                    @if ( null !== $ecommerceNoteId ) aria-describedby="{{ $ecommerceNoteId }}" @endif
                >

                @if ( null !== $item['image'] )
                    <img
                        src="{{ $item['image']['url'] }}"
                        @if ( null !== $item['image']['srcset'] ) srcset="{{ $item['image']['srcset'] }}" sizes="48px" @endif
                        alt="{{ $item['image']['alt'] }}"
                        class="ec-choice-card__image"
                        width="48"
                        height="48"
                        loading="lazy"
                        decoding="async"
                    >
                @endif

                <span class="ec-choice-card__text">
                    <span class="ec-choice-card__label">{{ $item['label'] }}</span>
                    @if ( null !== $ecommerceNoteId )
                        <span id="{{ $ecommerceNoteId }}" class="ec-choice-card__hint">{{ $item['reason'] ?? $item['hint'] }}</span>
                    @endif
                </span>

                @if ( null !== $item['meta'] )
                    <span class="ec-choice-card__meta" data-choice-card-meta><bdi>{{ $item['meta'] }}</bdi></span>
                @endif

                <x-artisanpack-icon name="check" class="ec-choice-card__check" aria-hidden="true" />
            </label>
        @endforeach
    </div>

    @if ( null !== $error )
        <p id="{{ $errorId() }}" class="ec-choice-cards__error" data-choice-cards-error><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $error }}</p>
    @endif
</fieldset>
