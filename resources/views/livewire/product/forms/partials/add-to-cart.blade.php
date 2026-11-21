{{--
    The add-to-cart row shared by the purchase forms (#86): the quantity
    stepper (132px), the solid primary "Add to cart" button (flex: 1, at
    least 200px, with the line price when the form knows it), and any extra
    buttons such as a wishlist heart; the row wraps on narrow screens. Then
    the blocked reason and the live regions.

    Expects `$formId`, `$canAdd` (bool), and optionally `$blockedReason`
    (why the button is disabled), `$showQuantity` (default: the form's
    own), `$buttonLabel` (default: the form's `buttonText`, else "Add
    to cart"), and `$priceOnButton` (default true; false when the form shows
    the line price itself, as the bundled form's total does).

    The button is described by the blocked reason and by the form-level
    `variant`, `quantities`, or `options` error (rendered by the form with
    the id `{formId}-{key}-error`). Any error is also announced in an alert
    keyed on the form's `errorSummary`, so each refused add is announced.

    When the button shows a line price the quantity binds live on `change`
    (each stepper press, or leaving the field), so the price follows it.
    Not a debounce: in Livewire 3 a debounced binding holds the value back,
    so a click on "Add to cart" inside the window would add the old one.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceShowQuantity = $showQuantity ?? true;
    $ecommerceLiveQuantity = $canAdd && $ecommerceShowQuantity && $this->showsLinePrice();
    $ecommerceLinePrice    = $canAdd && ( $priceOnButton ?? true ) ? $this->linePrice() : null;
    $ecommerceRowActions   = $this->addToCartActions();
    $ecommerceDescribedBy  = array_values( array_filter( [
        ! $canAdd && ! empty( $blockedReason ) ? $formId . '-blocked' : null,
        ...array_map( static fn ( string $key ): ?string => $errors->has( $key ) ? $formId . '-' . $key . '-error' : null, [ 'variant', 'quantities', 'options' ] ),
    ] ) );
@endphp
<div class="ec-add-to-cart" data-add-to-cart-row>
    <div class="ec-add-to-cart__row">
        @if ( $ecommerceShowQuantity )
            <div class="ec-add-to-cart__quantity">
                @if ( $ecommerceLiveQuantity )
                    <x-artisanpack-ec-quantity
                        :id="$formId . '-quantity'"
                        :label="__( 'Quantity' )"
                        size="lg"
                        wire:model.live.change="quantity"
                        :error="$errors->first( 'quantity' )"
                        :disabled="! $canAdd"
                    />
                @else
                    <x-artisanpack-ec-quantity
                        :id="$formId . '-quantity'"
                        :label="__( 'Quantity' )"
                        size="lg"
                        wire:model="quantity"
                        :error="$errors->first( 'quantity' )"
                        :disabled="! $canAdd"
                    />
                @endif
            </div>
        @endif

        <x-artisanpack-button
            leading-icon="shopping-bag"
            color="primary"
            size="lg"
            class="ec-add-to-cart__button"
            wire:click="addToCart"
            wire:loading.attr="disabled"
            wire:target="addToCart"
            :disabled="! $canAdd"
            :aria-describedby="[] === $ecommerceDescribedBy ? null : implode( ' ', $ecommerceDescribedBy )"
            data-add-to-cart>{{ $buttonLabel ?? ( $buttonText ?? null ) ?? __( 'Add to cart' ) }}@if ( null !== $ecommerceLinePrice )<span class="ec-add-to-cart__price" data-add-to-cart-price> <span aria-hidden="true">·</span> <bdi>{{ $ecommerceLinePrice }}</bdi></span>@endif</x-artisanpack-button>

        @if ( [] !== $ecommerceRowActions )
            <div class="ec-add-to-cart__actions" data-add-to-cart-actions>
                @foreach ( $ecommerceRowActions as $ecommerceActionKey => $ecommerceAction )
                    @if ( $ecommerceAction instanceof \Illuminate\Contracts\Support\Htmlable )
                        {{ $ecommerceAction }}
                    @else
                        @include( $ecommerceAction, [ 'product' => $product, 'formId' => $formId ] )
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    @if ( ! $ecommerceShowQuantity && $errors->has( 'quantity' ) )
        <p class="ec-add-to-cart__error" data-form-error>{{ $errors->first( 'quantity' ) }}</p>
    @endif

    @if ( ! $canAdd && ! empty( $blockedReason ) )
        <p id="{{ $formId }}-blocked" class="ec-add-to-cart__blocked" data-add-blocked>{{ $blockedReason }}</p>
    @endif

    <p class="apui-sr-only" role="status" aria-live="polite" aria-atomic="true" data-cart-announcement>{{ $announcement }}</p>

    @if ( $errors->any() )
        <p class="apui-sr-only" role="alert" wire:key="{{ $formId }}-add-error-{{ $errorSummary ?? 0 }}" data-cart-error-announcement>{{ __( 'Not added to your cart: :reason', [ 'reason' => $errors->first() ] ) }}</p>
    @endif
</div>
