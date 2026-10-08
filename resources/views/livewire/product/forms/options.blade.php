{{--
    Generic purchase form from the product type's option schema. See
    Product\Forms\OptionsForm.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceFormId = 'ec-product-' . $product->id )
<div class="flex flex-col gap-4" data-purchase-form="options">
    @foreach ( $fields as $field )
        @php( $ecommerceModel = 'options.' . $field['name'] )
        <div wire:key="option-field-{{ $field['id'] }}" data-option-field="{{ $field['name'] }}">
            @switch ( $field['type'] )
                @case( 'info' )
                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-semibold">{{ $field['label'] }}</p>

                        @if ( is_array( $field['meta']['items'] ?? null ) )
                            <ul role="list" class="flex flex-col gap-1 text-sm">
                                @foreach ( $field['meta']['items'] as $item )
                                    <li>{{ __( ':quantity × :name', [ 'quantity' => (int) ( $item['quantity'] ?? 1 ), 'name' => (string) ( $item['name'] ?? '' ) ] ) }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ( null !== $field['help'] )
                            <p class="text-sm text-base-content/70">{{ $field['help'] }}</p>
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
                    <x-artisanpack-textarea :id="$field['id']" :label="$field['label']" :hint="$field['help']" wire:model="{{ $ecommerceModel }}" :required="$field['required']" :error-field="$ecommerceModel" />
                    @break

                @case( 'select' )
                    <x-artisanpack-select
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
                    <x-artisanpack-radio
                        :id="$field['id']"
                        :label="$field['label']"
                        :hint="$field['help']"
                        :options="collect( $field['options'] )->map( fn ( array $option ): array => [ 'id' => $option['value'], 'name' => $option['label'] ] )->all()"
                        wire:model="{{ $ecommerceModel }}"
                        :error-field="$ecommerceModel"
                    />
                    @break

                @case( 'checkbox' )
                    <x-artisanpack-checkbox :id="$field['id']" :label="$field['label']" :hint="$field['help']" wire:model="{{ $ecommerceModel }}" :error-field="$ecommerceModel" />
                    @break

                @default
                    <x-artisanpack-input
                        :id="$field['id']"
                        :label="$field['label']"
                        :hint="$field['help']"
                        :type="'number' === $field['type'] ? 'number' : 'text'"
                        wire:model="{{ $ecommerceModel }}"
                        :required="$field['required']"
                        :error-field="$ecommerceModel"
                    />
            @endswitch
        </div>
    @endforeach

    @error( 'options' )
        <p class="text-sm text-error" data-form-error>{{ $message }}</p>
    @enderror

    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [
        'formId'       => $ecommerceFormId,
        'showQuantity' => ! $separately,
    ] )
</div>
