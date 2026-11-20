{{--
    Form field. See Field.

    A thin wrapper over the library control: the control prints its own
    label, hint and error, each with an id it links from
    `aria-describedby`, and marks itself invalid. The error is read from
    `error-field`, else the `wire:model` path. The wrapper carries
    `data-field-key`, which the form's error summary uses to find the field.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceFieldKey     = $errorKey();
    $ecommerceFieldOptions = 'select' === $control ? $selectOptions() : [];
    $ecommerceFieldHint    = null === $hint || '' === $hint ? null : $hint;
@endphp
<div class="w-full" data-ec-field="{{ $id }}" @if ( null !== $ecommerceFieldKey ) data-field-key="{{ $ecommerceFieldKey }}" @endif>
    @switch ( $control )
        @case( 'select' )
            <x-artisanpack-select :id="$id" :label="$label" :hint="$ecommerceFieldHint" :options="$ecommerceFieldOptions" :placeholder="$placeholder" :error-field="$ecommerceFieldKey" {{ $attributes }} />
            @break

        @case( 'textarea' )
            <x-artisanpack-textarea :id="$id" :label="$label" :hint="$ecommerceFieldHint" :error-field="$ecommerceFieldKey" {{ $attributes }} />
            @break

        @case( 'password' )
            <x-artisanpack-password :id="$id" :label="$label" :hint="$ecommerceFieldHint" :error-field="$ecommerceFieldKey" {{ $attributes }} />
            @break

        @case( 'checkbox' )
            <x-artisanpack-checkbox :id="$id" :label="$label" :description="$ecommerceFieldHint" :error-field="$ecommerceFieldKey" {{ $attributes }} />
            @break

        @default
            <x-artisanpack-input :id="$id" :label="$label" :hint="$ecommerceFieldHint" :error-field="$ecommerceFieldKey" {{ $attributes }} />
    @endswitch
</div>
