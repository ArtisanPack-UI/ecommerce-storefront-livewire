{{--
    Quantity stepper. See Quantity.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceHasError = null !== $error && '' !== $error )
<div {{ $attributes->whereDoesntStartWith( [ 'wire:model', 'disabled' ] )->class( [ 'flex flex-col gap-1' ] ) }} x-data data-quantity="{{ $id }}">
    <label for="{{ $id }}" @class( [ 'text-sm font-medium', 'sr-only' => null === $label ] )>{{ $label ?? __( 'Quantity' ) }}</label>

    <div class="join">
        <button
            type="button"
            class="btn join-item btn-square"
            x-on:click="$refs.input.stepDown(); $refs.input.dispatchEvent( new Event( 'input', { bubbles: true } ) ); $refs.input.dispatchEvent( new Event( 'change', { bubbles: true } ) )"
            aria-label="{{ null === $itemName ? __( 'Decrease quantity' ) : __( 'Decrease quantity of :name', [ 'name' => $itemName ] ) }}"
            aria-controls="{{ $id }}"
            @disabled( $attributes->get( 'disabled' ) )
        >
            <x-artisanpack-icon name="o-minus" class="h-4 w-4" aria-hidden="true" />
        </button>

        <input
            id="{{ $id }}"
            x-ref="input"
            type="number"
            inputmode="numeric"
            min="{{ $min }}"
            @if ( null !== $max ) max="{{ $max }}" @endif
            step="1"
            @class( [ 'input join-item w-20 text-center tabular-nums', 'input-error' => $ecommerceHasError ] )
            {{ $attributes->whereStartsWith( 'wire:model' ) }}
            @disabled( $attributes->get( 'disabled' ) )
            @if ( $ecommerceHasError ) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        >

        <button
            type="button"
            class="btn join-item btn-square"
            x-on:click="$refs.input.stepUp(); $refs.input.dispatchEvent( new Event( 'input', { bubbles: true } ) ); $refs.input.dispatchEvent( new Event( 'change', { bubbles: true } ) )"
            aria-label="{{ null === $itemName ? __( 'Increase quantity' ) : __( 'Increase quantity of :name', [ 'name' => $itemName ] ) }}"
            aria-controls="{{ $id }}"
            @disabled( $attributes->get( 'disabled' ) )
        >
            <x-artisanpack-icon name="o-plus" class="h-4 w-4" aria-hidden="true" />
        </button>
    </div>

    @if ( $ecommerceHasError )
        <p id="{{ $id }}-error" class="text-sm text-error" data-quantity-error>{{ $error }}</p>
    @endif
</div>
