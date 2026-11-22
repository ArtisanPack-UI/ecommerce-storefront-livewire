{{--
    Form field (#83). See Field.

    A thin wrapper over the design-system control: the control prints its
    own label (with the required asterisk), hint and error (with the alert
    icon), each with an id it links from `aria-describedby`, and sets
    `data-invalid`, `data-disabled` and `aria-invalid` itself. This adds the
    muted "(optional)" mark and the `.ec-field` block. The error is read
    from `error-field`, else the `wire:model` path. The wrapper carries
    `data-field-key`, which the form's error summary uses to find the field.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceFieldKey     = $errorKey();
    $ecommerceFieldLabel   = $labelContent();
    $ecommerceFieldOptions = 'select' === $control ? $selectOptions() : [];
    $ecommerceFieldHint    = null === $hint || '' === $hint ? null : $hint;
@endphp
<div class="ec-field" data-ec-field="{{ $id }}" @if ( null !== $ecommerceFieldKey ) data-field-key="{{ $ecommerceFieldKey }}" @endif>
    @switch ( $control )
        @case( 'select' )
            <x-artisanpack-select :id="$id" :hint="$ecommerceFieldHint" :options="$ecommerceFieldOptions" :placeholder="$placeholder" :error-field="$ecommerceFieldKey" {{ $attributes }}>
                @if ( null !== $ecommerceFieldLabel )
                    <x-slot:label>{{ $ecommerceFieldLabel }}</x-slot:label>
                @endif
            </x-artisanpack-select>
            @break

        @case( 'textarea' )
            <x-artisanpack-textarea :id="$id" :hint="$ecommerceFieldHint" :placeholder="$placeholder" :max-length="$maxLength" :error-field="$ecommerceFieldKey" {{ $attributes }}>
                @if ( null !== $ecommerceFieldLabel )
                    <x-slot:label>{{ $ecommerceFieldLabel }}</x-slot:label>
                @endif
            </x-artisanpack-textarea>
            @break

        @case( 'password' )
            <x-artisanpack-password :id="$id" :hint="$ecommerceFieldHint" :leading-icon="$leadingIcon" :error-field="$ecommerceFieldKey" {{ $attributes }}>
                @if ( null !== $ecommerceFieldLabel )
                    <x-slot:label>{{ $ecommerceFieldLabel }}</x-slot:label>
                @endif
            </x-artisanpack-password>
            @break

        @case( 'checkbox' )
            <x-artisanpack-checkbox :id="$id" :description="$ecommerceFieldHint" :error-field="$ecommerceFieldKey" {{ $attributes }}>
                @if ( null !== $ecommerceFieldLabel )
                    <x-slot:label>{{ $ecommerceFieldLabel }}</x-slot:label>
                @endif
            </x-artisanpack-checkbox>
            @break

        @default
            <x-artisanpack-input :id="$id" :hint="$ecommerceFieldHint" :placeholder="$placeholder" :leading-icon="$leadingIcon" :error-field="$ecommerceFieldKey" {{ $attributes }}>
                @if ( null !== $ecommerceFieldLabel )
                    <x-slot:label>{{ $ecommerceFieldLabel }}</x-slot:label>
                @endif
            </x-artisanpack-input>
    @endswitch
</div>
