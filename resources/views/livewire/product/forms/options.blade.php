{{--
    Generic purchase form from the product type's option schema. See
    Product\Forms\OptionsForm.

    Under the product summary (#104): each field as its design-system
    control (Input, Textarea, Select, RadioGroup, Checkbox, or the quantity
    stepper), an `info` field as a short muted note, then the shared
    add-to-cart row. A text field with a `max:N` rule has a "12 of 20
    characters" count under it that follows the typing; the count describes
    the control but isn't a live region, so it doesn't talk over every
    keystroke. Characters are counted (and capped) as code points, as the
    server's `max` rule counts them, rather than with a native `maxlength`,
    which counts an emoji as two.

    Since 2.0.0 the richer engine schema: a `swatch` field as swatches
    (color or image dots), a radio with `meta.display = cards` as choice
    cards (image, name, hint), a choice's price difference after its name
    ("+$5.00"), and disabled choices (with "Not available with Oak" when
    another choice rules them out). Only visible fields are passed in. When
    a choice can change the form (`$reactive`), each field's `change`
    refreshes it, which sends the deferred value (a text field's on blur).
    With priced choices the quoted price, "$40.00 including options", sits
    above the add-to-cart row in a polite live region, with "Updating
    price…" while a refresh is running.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceFormId = 'ec-product-' . $product->id;
@endphp
<div class="ec-purchase-form" data-purchase-form="options">
    @foreach ( $fields as $field )
        @php
            $ecommerceModel     = 'options.' . $field['name'];
            $ecommerceMaxLength = $field['maxLength'];
            $ecommerceCountId   = null === $ecommerceMaxLength ? null : $field['id'] . '-count';
            $ecommerceLength    = null === $ecommerceMaxLength ? 0 : mb_strlen( (string) data_get( $options, $field['name'], '' ) );
        @endphp
        <div
            class="ec-option-field"
            wire:key="option-field-{{ $field['id'] }}"
            data-option-field="{{ $field['name'] }}"
            @if ( $reactive && 'info' !== $field['type'] ) x-on:change="$wire.$refresh()" @endif
            @if ( null !== $ecommerceMaxLength )
                x-data="{
                    length: {{ $ecommerceLength }},
                    max: {{ $ecommerceMaxLength }},
                    template: @js( __( ':count of :max characters', [ 'max' => $ecommerceMaxLength ] ) ),
                    count( event ) {
                        const characters = [ ...event.target.value ];

                        if ( characters.length > this.max ) {
                            event.target.value = characters.slice( 0, this.max ).join( '' );
                            event.target.dispatchEvent( new Event( 'input', { bubbles: true } ) );
                        }

                        this.length = Math.min( characters.length, this.max );
                    },
                }"
                x-on:input="count( $event )"
            @endif
        >
            @switch ( $field['type'] )
                @case( 'info' )
                    <div class="ec-option-info">
                        <p class="ec-option-info__label">{{ $field['label'] }}</p>

                        @if ( is_array( $field['meta']['items'] ?? null ) )
                            <ul role="list" class="ec-option-info__list">
                                @foreach ( $field['meta']['items'] as $item )
                                    <li>{{ __( ':quantity × :name', [ 'quantity' => (int) ( $item['quantity'] ?? 1 ), 'name' => (string) ( $item['name'] ?? '' ) ] ) }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ( null !== $field['help'] )
                            <p class="ec-option-info__help">{{ $field['help'] }}</p>
                        @endif
                    </div>
                    @break

                @case( 'quantity' )
                    <x-artisanpack-ec-quantity
                        :id="$field['id']"
                        :label="$field['label']"
                        :min="0"
                        :item-name="$field['label']"
                        wire:model="{{ $ecommerceModel }}"
                        :error="$errors->first( $ecommerceModel )"
                    />
                    @break

                @case( 'textarea' )
                    <x-artisanpack-ec-field
                        control="textarea"
                        :id="$field['id']"
                        :label="$field['label']"
                        :hint="$field['help']"
                        wire:model="{{ $ecommerceModel }}"
                        :required="$field['required']"
                        :aria-describedby="$ecommerceCountId"
                        :error-field="$ecommerceModel"
                    />
                    @break

                @case( 'select' )
                    <x-artisanpack-ec-field
                        control="select"
                        :id="$field['id']"
                        :label="$field['label']"
                        :hint="$field['help']"
                        :options="collect( $field['options'] )->map( fn ( array $option ): array => [
                            'id'       => $option['value'],
                            'name'     => implode( ' ', array_filter( [
                                null === $option['delta'] ? $option['label'] : __( ':choice · :delta', [ 'choice' => $option['label'], 'delta' => $option['delta'] ] ),
                                null === $option['reason'] ? null : __( '(:reason)', [ 'reason' => $option['reason'] ] ),
                            ] ) ),
                            'disabled' => $option['disabled'],
                        ] )->all()"
                        :placeholder="__( 'Choose…' )"
                        wire:model="{{ $ecommerceModel }}"
                        :required="$field['required']"
                        :error-field="$ecommerceModel"
                    />
                    @break

                @case( 'radio' )
                    @if ( $field['cards'] )
                        <x-artisanpack-ec-choice-cards
                            :name="$field['id']"
                            :id-prefix="$field['id']"
                            :legend="$field['label']"
                            :hint="$field['help']"
                            :options="array_map( static fn ( array $option ): array => [ ...$option, 'meta' => $option['delta'], 'selected' => (string) data_get( $options, $field['name'] ) === $option['value'] ], $field['options'] )"
                            :required="$field['required']"
                            :error="$errors->first( $ecommerceModel )"
                            wire:model="{{ $ecommerceModel }}"
                        />
                    @else
                        <x-artisanpack-radio-group
                            :id="$field['id']"
                            :label="$field['label']"
                            :hint="$field['help']"
                            :options="array_map( static fn ( array $option ): array => array_filter( [
                                'value'       => $option['value'],
                                'label'       => $option['label'],
                                'description' => $option['reason'] ?? $option['hint'],
                                'meta'        => $option['delta'],
                                'disabled'    => $option['disabled'],
                            ], static fn ( mixed $value ): bool => null !== $value ), $field['options'] )"
                            :required="$field['required']"
                            wire:model="{{ $ecommerceModel }}"
                            :error-field="$ecommerceModel"
                        />
                    @endif
                    @break

                @case( 'swatch' )
                    <x-artisanpack-ec-swatches
                        :name="$field['id']"
                        :id-prefix="$field['id']"
                        :legend="$field['label']"
                        :options="array_map( static fn ( array $option ): array => [
                            'value'    => $option['value'],
                            'label'    => $option['label'],
                            'swatch'   => $option['swatch'],
                            'meta'     => $option['delta'],
                            'selected' => (string) data_get( $options, $field['name'] ) === $option['value'],
                            'disabled' => $option['disabled'],
                            'reason'   => $option['reason'] ?? $option['hint'],
                        ], $field['options'] )"
                        :required="$field['required']"
                        :error="$errors->first( $ecommerceModel )"
                        :aria-describedby="null === $field['help'] ? null : $field['id'] . '-help'"
                        wire:model="{{ $ecommerceModel }}"
                    />

                    @if ( null !== $field['help'] )
                        <p id="{{ $field['id'] }}-help" class="ec-option-field__help">{{ $field['help'] }}</p>
                    @endif
                    @break

                @case( 'checkbox' )
                    <x-artisanpack-ec-field control="checkbox" :id="$field['id']" :label="$field['label']" :hint="$field['help']" wire:model="{{ $ecommerceModel }}" :error-field="$ecommerceModel" />
                    @break

                @default
                    <x-artisanpack-ec-field
                        :id="$field['id']"
                        :label="$field['label']"
                        :hint="$field['help']"
                        :type="'number' === $field['type'] ? 'number' : 'text'"
                        wire:model="{{ $ecommerceModel }}"
                        :required="$field['required']"
                        :aria-describedby="$ecommerceCountId"
                        :error-field="$ecommerceModel"
                    />
            @endswitch

            @if ( null !== $ecommerceMaxLength )
                <p id="{{ $ecommerceCountId }}" class="ec-option-field__count" x-text="template.replace( ':count', length )" data-character-count>{{ __( ':count of :max characters', [ 'count' => $ecommerceLength, 'max' => $ecommerceMaxLength ] ) }}</p>
            @endif
        </div>
    @endforeach

    @if ( $showsQuote )
        <p class="ec-option-price" data-quoted-price>
            <span role="status" aria-live="polite" aria-atomic="true" data-quoted-price-value>@if ( null !== $quotedPrice )<strong class="ec-option-price__amount"><bdi>{{ $quotedPrice }}</bdi></strong> {{ __( 'including options' ) }}@endif</span>
            <span class="ec-option-price__loading" wire:loading.delay wire:target="$refresh" data-quoted-price-loading>{{ __( 'Updating price…' ) }}</span>
        </p>
    @endif

    @error( 'options' )
        <p id="{{ $ecommerceFormId }}-options-error" class="ec-purchase-form__error" data-form-error><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
    @enderror

    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [
        'formId'       => $ecommerceFormId,
        'showQuantity' => $showQuantity && ! $separately,
    ] )
</div>
