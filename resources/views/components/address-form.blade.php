{{--
    Address form (#82). See AddressForm.

    A design-system Fieldset holding a four-track grid: country first (it
    binds live, because it decides how the region and postcode are labelled
    and whether they are required), first and last name side by side, the
    street lines across, then city, region and postcode at 2fr 1fr 1fr, and
    the phone last. Below 768px every field takes the whole row. Optional
    fields say "(optional)"; required ones get the control's asterisk.

    Component tags compile their attributes, so the deferred and live
    bindings are separate branches rather than a dynamic attribute name.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceAddressRegions = $regions();
    $ecommerceAddressFields  = $fields();
    $ecommerceAddressPhone   = $ecommerceAddressFields['phone'] ?? null;
    unset( $ecommerceAddressFields['phone'] );
@endphp
<x-artisanpack-fieldset
    variant="plain"
    :title="$legend"
    {{ $attributes->class( [ 'ec-address-form' ] ) }}
    data-address-form="{{ $model }}"
>
    <div class="ec-address-form__grid">
        <div class="ec-address-form__cell" data-span="full" wire:key="{{ $model }}-country_code" data-field="{{ $model }}.country_code">
            <x-artisanpack-ec-field
                control="select"
                :id="$model . '.country_code'"
                :label="__( 'Country' )"
                :hint="__( 'Fields change to match the country\'s address format.' )"
                :options="$countries()"
                :placeholder="__( 'Select a country' )"
                :autocomplete="$autocomplete( 'country' )"
                required
                wire:model.live="{{ $model }}.country_code"
            />
        </div>

        @foreach ( $ecommerceAddressFields as $field => $definition )
            <div class="ec-address-form__cell" data-span="{{ $definition['wide'] ? 'full' : 'half' }}" wire:key="{{ $model }}-{{ $field }}" data-field="{{ $model }}.{{ $field }}">
                @if ( $live )
                    <x-artisanpack-ec-field
                        :id="$model . '.' . $field"
                        :label="$definition['label']"
                        :autocomplete="$autocomplete( $definition['autocomplete'] )"
                        :required="$definition['required']"
                        :optional="! $definition['required']"
                        wire:model.live.blur="{{ $model }}.{{ $field }}"
                    />
                @else
                    <x-artisanpack-ec-field
                        :id="$model . '.' . $field"
                        :label="$definition['label']"
                        :autocomplete="$autocomplete( $definition['autocomplete'] )"
                        :required="$definition['required']"
                        :optional="! $definition['required']"
                        wire:model="{{ $model }}.{{ $field }}"
                    />
                @endif
            </div>
        @endforeach

        @if ( [] !== $ecommerceAddressRegions )
            <div class="ec-address-form__cell" data-span="quarter" wire:key="{{ $model }}-region_code-{{ $country }}" data-field="{{ $model }}.region_code">
                <x-artisanpack-ec-field
                    control="select"
                    :id="$model . '.region_code'"
                    :label="$regionLabel()"
                    :options="$ecommerceAddressRegions"
                    :placeholder="__( 'Select one' )"
                    :autocomplete="$autocomplete( 'address-level1' )"
                    required
                    wire:model.live="{{ $model }}.region_code"
                />
            </div>
        @else
            <div class="ec-address-form__cell" data-span="quarter" wire:key="{{ $model }}-region" data-field="{{ $model }}.region">
                @if ( $live )
                    <x-artisanpack-ec-field :id="$model . '.region'" :label="$regionLabel()" :autocomplete="$autocomplete( 'address-level1' )" optional wire:model.live.blur="{{ $model }}.region" />
                @else
                    <x-artisanpack-ec-field :id="$model . '.region'" :label="$regionLabel()" :autocomplete="$autocomplete( 'address-level1' )" optional wire:model="{{ $model }}.region" />
                @endif
            </div>
        @endif

        <div class="ec-address-form__cell" data-span="quarter" wire:key="{{ $model }}-postal_code-{{ $country }}" data-field="{{ $model }}.postal_code">
            @if ( $live )
                <x-artisanpack-ec-field :id="$model . '.postal_code'" :label="$postcodeLabel()" :hint="$postcodeHint()" :autocomplete="$autocomplete( 'postal-code' )" :required="$postcodeRequired()" :optional="! $postcodeRequired()" wire:model.live.blur="{{ $model }}.postal_code" />
            @else
                <x-artisanpack-ec-field :id="$model . '.postal_code'" :label="$postcodeLabel()" :hint="$postcodeHint()" :autocomplete="$autocomplete( 'postal-code' )" :required="$postcodeRequired()" :optional="! $postcodeRequired()" wire:model="{{ $model }}.postal_code" />
            @endif
        </div>

        @if ( null !== $ecommerceAddressPhone )
            <div class="ec-address-form__cell" data-span="full" wire:key="{{ $model }}-phone" data-field="{{ $model }}.phone">
                @if ( $live )
                    <x-artisanpack-ec-field :id="$model . '.phone'" type="tel" :label="$ecommerceAddressPhone['label']" :hint="__( 'Only used if the carrier needs to reach you.' )" :autocomplete="$autocomplete( $ecommerceAddressPhone['autocomplete'] )" :required="$ecommerceAddressPhone['required']" :optional="! $ecommerceAddressPhone['required']" wire:model.live.blur="{{ $model }}.phone" />
                @else
                    <x-artisanpack-ec-field :id="$model . '.phone'" type="tel" :label="$ecommerceAddressPhone['label']" :hint="__( 'Only used if the carrier needs to reach you.' )" :autocomplete="$autocomplete( $ecommerceAddressPhone['autocomplete'] )" :required="$ecommerceAddressPhone['required']" :optional="! $ecommerceAddressPhone['required']" wire:model="{{ $model }}.phone" />
                @endif
            </div>
        @endif
    </div>
</x-artisanpack-fieldset>
