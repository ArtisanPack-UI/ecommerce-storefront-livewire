{{--
    The quantity stepper, "Add to cart" button, and live region shared by
    the purchase forms.

    Expects `$formId`, `$canAdd` (bool), and optionally `$blockedReason`
    (why the button is disabled), `$showQuantity` (default: the form's
    own), and `$buttonLabel` (default: the form's `buttonText`, else "Add
    to cart").

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceShowQuantity = $showQuantity ?? true )
<div class="flex flex-col gap-3">
    <div class="flex flex-wrap items-end gap-3">
        @if ( $ecommerceShowQuantity )
            <x-artisanpack-ec-quantity
                :id="$formId . '-quantity'"
                :label="__( 'Quantity' )"
                wire:model="quantity"
                :error="$errors->first( 'quantity' )"
                :disabled="! $canAdd"
            />
        @endif

        <x-artisanpack-button
            :label="$buttonLabel ?? ( $buttonText ?? null ) ?? __( 'Add to cart' )"
            icon="o-shopping-cart"
            color="primary"
            class="grow sm:grow-0"
            wire:click="addToCart"
            wire:loading.attr="disabled"
            wire:target="addToCart"
            spinner="addToCart"
            :disabled="! $canAdd"
            :aria-describedby="! $canAdd && ! empty( $blockedReason ) ? $formId . '-blocked' : null"
            data-add-to-cart
        />
    </div>

    @if ( ! $ecommerceShowQuantity && $errors->has( 'quantity' ) )
        <p class="text-sm text-error" data-form-error>{{ $errors->first( 'quantity' ) }}</p>
    @endif

    @if ( ! $canAdd && ! empty( $blockedReason ) )
        <p id="{{ $formId }}-blocked" class="text-sm text-base-content/70" data-add-blocked>{{ $blockedReason }}</p>
    @endif

    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-cart-announcement>{{ $announcement }}</p>
</div>
