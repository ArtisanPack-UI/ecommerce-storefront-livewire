{{--
    The quantity stepper, "Add to cart" button, and live region shared by
    the purchase forms.

    Expects `$formId`, `$canAdd` (bool), and optionally `$blockedReason`
    (why the button is disabled), `$showQuantity` (default: the form's
    own), and `$buttonLabel` (default: the form's `buttonText`, else "Add
    to cart").

    The button is described by the blocked reason and by the form-level
    `variant`, `quantities`, or `options` error (rendered by the form with
    the id `{formId}-{key}-error`). Any error is also announced in an alert
    keyed on the form's `errorSummary`, so each refused add is announced.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceShowQuantity = $showQuantity ?? true;
    $ecommerceDescribedBy  = array_values( array_filter( [
        ! $canAdd && ! empty( $blockedReason ) ? $formId . '-blocked' : null,
        ...array_map( static fn ( string $key ): ?string => $errors->has( $key ) ? $formId . '-' . $key . '-error' : null, [ 'variant', 'quantities', 'options' ] ),
    ] ) );
@endphp
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
            :aria-describedby="[] === $ecommerceDescribedBy ? null : implode( ' ', $ecommerceDescribedBy )"
            data-add-to-cart
        />
    </div>

    @if ( ! $ecommerceShowQuantity && $errors->has( 'quantity' ) )
        <p class="text-sm text-[color-mix(in_oklab,var(--color-error)_55%,var(--color-base-content))]" data-form-error>{{ $errors->first( 'quantity' ) }}</p>
    @endif

    @if ( ! $canAdd && ! empty( $blockedReason ) )
        <p id="{{ $formId }}-blocked" class="text-sm text-base-content/70" data-add-blocked>{{ $blockedReason }}</p>
    @endif

    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-cart-announcement>{{ $announcement }}</p>

    @if ( $errors->any() )
        <p class="sr-only" role="alert" wire:key="{{ $formId }}-add-error-{{ $errorSummary ?? 0 }}" data-cart-error-announcement>{{ __( 'Not added to your cart: :reason', [ 'reason' => $errors->first() ] ) }}</p>
    @endif
</div>
