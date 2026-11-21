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
                        :options="collect( $field['options'] )->map( fn ( array $option ): array => [ 'id' => $option['value'], 'name' => $option['label'] ] )->all()"
                        :placeholder="__( 'Choose…' )"
                        wire:model="{{ $ecommerceModel }}"
                        :required="$field['required']"
                        :error-field="$ecommerceModel"
                    />
                    @break

                @case( 'radio' )
                    <x-artisanpack-radio-group
                        :id="$field['id']"
                        :label="$field['label']"
                        :hint="$field['help']"
                        :options="\ArtisanPackUI\EcommerceStorefrontLivewire\Support\ControlOptions::from( $field['options'] )"
                        :required="$field['required']"
                        wire:model="{{ $ecommerceModel }}"
                        :error-field="$ecommerceModel"
                    />
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

    @error( 'options' )
        <p id="{{ $ecommerceFormId }}-options-error" class="ec-purchase-form__error" data-form-error><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
    @enderror

    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [
        'formId'       => $ecommerceFormId,
        'showQuantity' => $showQuantity && ! $separately,
    ] )
</div>
