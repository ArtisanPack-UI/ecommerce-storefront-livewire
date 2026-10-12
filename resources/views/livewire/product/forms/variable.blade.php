{{--
    Variable product purchase form (the variation picker). See
    Product\Forms\VariableForm.

    A choice can clear another group's (a colour without that size). The
    morph updates each radio's `checked` attribute, not the checked state
    the shopper's click set, so every radio is re-synced from its
    attribute once `choose()` returns.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceFormId = 'ec-product-' . $product->id )
<div class="flex flex-col gap-5" data-purchase-form="variable">
    @foreach ( $groups as $group )
        <x-artisanpack-ec-swatches
            :name="$ecommerceFormId . '-attribute-' . $group['id']"
            :legend="$group['label']"
            :options="$group['options']"
            :id-prefix="$ecommerceFormId . '-attribute-' . $group['id']"
            x-on:change="$wire.choose( {{ (int) $group['id'] }}, $event.target.value ).then( () => $el.closest( '[data-purchase-form]' )?.querySelectorAll( 'input[data-swatch-value]' ).forEach( ( input ) => input.checked = input.hasAttribute( 'checked' ) ) )"
            wire:key="variation-group-{{ $group['id'] }}"
            :aria-describedby="$errors->has( 'variant' ) ? $ecommerceFormId . '-variant-error' : null"
            data-variation-group="{{ $group['id'] }}"
        />
    @endforeach

    <p @class( [ 'text-sm text-base-content/70', 'sr-only' => '' === $selectionNotice ] ) role="status" aria-live="polite" aria-atomic="true" data-selection-notice>{{ $selectionNotice }}</p>

    @error( 'variant' )
        <p id="{{ $ecommerceFormId }}-variant-error" class="text-sm text-[color-mix(in_oklab,var(--color-error)_55%,var(--color-base-content))]" data-variant-error>{{ $message }}</p>
    @enderror

    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [ 'formId' => $ecommerceFormId ] )
</div>
