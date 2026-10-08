{{--
    Address form. See AddressForm.

    Component tags compile their attributes, so the deferred and live
    bindings are separate branches rather than a dynamic attribute name.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceAddressRegions = $regions() )
<fieldset {{ $attributes->class( [ 'grid', 'gap-4', 'sm:grid-cols-2' ] ) }} data-address-form="{{ $model }}">
    @if ( null !== $legend )
        <legend class="mb-2 font-semibold sm:col-span-2">{{ $legend }}</legend>
    @endif

    <div class="sm:col-span-2" wire:key="{{ $model }}-country_code" data-field="{{ $model }}.country_code">
        <x-artisanpack-select
            :id="$model . '.country_code'"
            :label="__( 'Country' )"
            :options="$countries()"
            :placeholder="__( 'Select a country' )"
            placeholder-value=""
            :autocomplete="$autocomplete( 'country' )"
            required
            wire:model.live="{{ $model }}.country_code"
        />
    </div>

    @foreach ( $fields() as $field => $definition )
        <div @class( [ 'sm:col-span-2' => $definition['wide'] ] ) wire:key="{{ $model }}-{{ $field }}" data-field="{{ $model }}.{{ $field }}">
            @if ( $live )
                <x-artisanpack-input
                    :id="$model . '.' . $field"
                    :label="$definition['label']"
                    :autocomplete="$autocomplete( $definition['autocomplete'] )"
                    :required="$definition['required']"
                    :type="'phone' === $field ? 'tel' : 'text'"
                    wire:model.live.blur="{{ $model }}.{{ $field }}"
                />
            @else
                <x-artisanpack-input
                    :id="$model . '.' . $field"
                    :label="$definition['label']"
                    :autocomplete="$autocomplete( $definition['autocomplete'] )"
                    :required="$definition['required']"
                    :type="'phone' === $field ? 'tel' : 'text'"
                    wire:model="{{ $model }}.{{ $field }}"
                />
            @endif
        </div>
    @endforeach

    @if ( [] !== $ecommerceAddressRegions )
        <div wire:key="{{ $model }}-region_code-{{ $country }}" data-field="{{ $model }}.region_code">
            <x-artisanpack-select
                :id="$model . '.region_code'"
                :label="$regionLabel()"
                :options="$ecommerceAddressRegions"
                :placeholder="__( 'Select one' )"
                placeholder-value=""
                :autocomplete="$autocomplete( 'address-level1' )"
                required
                wire:model.live="{{ $model }}.region_code"
            />
        </div>
    @else
        <div wire:key="{{ $model }}-region" data-field="{{ $model }}.region">
            @if ( $live )
                <x-artisanpack-input :id="$model . '.region'" :label="$regionLabel()" :autocomplete="$autocomplete( 'address-level1' )" wire:model.live.blur="{{ $model }}.region" />
            @else
                <x-artisanpack-input :id="$model . '.region'" :label="$regionLabel()" :autocomplete="$autocomplete( 'address-level1' )" wire:model="{{ $model }}.region" />
            @endif
        </div>
    @endif

    <div wire:key="{{ $model }}-postal_code-{{ $country }}" data-field="{{ $model }}.postal_code">
        @if ( $live )
            <x-artisanpack-input :id="$model . '.postal_code'" :label="$postcodeLabel()" :hint="$postcodeHint()" :autocomplete="$autocomplete( 'postal-code' )" :required="$postcodeRequired()" wire:model.live.blur="{{ $model }}.postal_code" />
        @else
            <x-artisanpack-input :id="$model . '.postal_code'" :label="$postcodeLabel()" :hint="$postcodeHint()" :autocomplete="$autocomplete( 'postal-code' )" :required="$postcodeRequired()" wire:model="{{ $model }}.postal_code" />
        @endif
    </div>
</fieldset>
