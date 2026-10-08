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
            wire:model.live="selected.{{ $group['id'] }}"
            wire:key="variation-group-{{ $group['id'] }}"
            data-variation-group="{{ $group['id'] }}"
        />
    @endforeach

    <p @class( [ 'text-sm text-base-content/70', 'sr-only' => '' === $selectionNotice ] ) role="status" aria-live="polite" aria-atomic="true" data-selection-notice>{{ $selectionNotice }}</p>

    @error( 'variant' )
        <p class="text-sm text-error" data-variant-error>{{ $message }}</p>
    @enderror

    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [ 'formId' => $ecommerceFormId ] )
</div>
