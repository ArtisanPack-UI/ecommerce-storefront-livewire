{{--
    Variable product purchase form (the variation picker). See
    Product\Forms\VariableForm.

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
            x-on:change="$wire.choose( {{ (int) $group['id'] }}, $event.target.value )"
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
