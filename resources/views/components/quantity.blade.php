{{--
    Quantity stepper (#79). See Quantity.

    A design-system Input whose box holds the minus button, the number and
    the plus button (ghost, icon-only), with the Input's own label (visible
    or hidden; a hidden one names `itemName`, "Quantity, Oxbow mug", so each
    cart line's field is told apart) and field error. `size` follows the context: `lg` on the
    product page, `md` by default, `sm` in the cart and `xs` in the drawer.

    The buttons step the native input and fire its input and change events,
    so `wire:model` (with any modifier) sees the new value; a value the
    server sets back is re-read through `$wire.$watch`. At the minimum
    the minus button is aria-disabled, and so is plus at the maximum: both
    stay focusable and do nothing when pressed.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceDisabled    = (bool) $attributes->get( 'disabled' );
    $ecommerceRequired    = (bool) $attributes->get( 'required' );
    $ecommerceButtonSize  = in_array( $size, [ 'sm', 'xs' ], true ) ? 'xs' : 'sm';
    $ecommerceInputSize   = match ( $size ) {
        'lg'       => 'lg',
        'sm', 'xs' => 'sm',
        default    => 'md',
    };
    $ecommerceRootAttributes = $attributes->whereDoesntStartWith( [ 'wire:model', 'disabled', 'required', 'placeholder' ] );
    $ecommerceModel          = $attributes->whereStartsWith( 'wire:model' )->first();
@endphp
<div
    {{ $ecommerceRootAttributes->class( [ 'ec-quantity' ] ) }}
    x-data="{
        value: null,
        read() {
            const number = this.$refs.input.valueAsNumber;

            this.value = Number.isNaN( number ) ? null : number;
        },
        atMin() {
            return null !== this.value && this.value <= {{ $min }};
        },
        atMax() {
            return {{ null === $max ? 'false' : 'null !== this.value && this.value >= ' . $max }};
        },
        step( direction ) {
            if ( this.$refs.input.disabled || ( direction < 0 ? this.atMin() : this.atMax() ) ) {
                return;
            }

            direction < 0 ? this.$refs.input.stepDown() : this.$refs.input.stepUp();
            this.$refs.input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
            this.$refs.input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        },
    }"
    x-init="$nextTick( () => read() ){{ is_string( $ecommerceModel ) && '' !== $ecommerceModel ? '; $wire.$watch( ' . \Illuminate\Support\Js::from( $ecommerceModel ) . ', () => $nextTick( () => read() ) )' : '' }}"
    x-on:input="read()"
    x-on:change="read()"
    data-quantity="{{ $id }}"
    data-size="{{ $size }}"
>
    <x-artisanpack-input
        :id="$id"
        type="number"
        :size="$ecommerceInputSize"
        :label="$label ?? ( null === $itemName ? __( 'Quantity' ) : __( 'Quantity, :product', [ 'product' => $itemName ] ) )"
        :error="$error"
        :disabled="$ecommerceDisabled"
        :required="$ecommerceRequired"
        :classes="null === $label ? [ 'label' => 'apui-sr-only' ] : []"
        x-ref="input"
        inputmode="numeric"
        min="{{ $min }}"
        :max="$max"
        step="1"
        data-quantity-input
        {{ $attributes->whereStartsWith( [ 'wire:model', 'placeholder' ] ) }}
    >
        <x-slot:prefix>
            <x-artisanpack-button
                variant="ghost"
                color="neutral"
                :size="$ecommerceButtonSize"
                icon-only
                leading-icon="minus"
                :aria-label="null === $itemName ? __( 'Decrease quantity' ) : __( 'Decrease quantity, :name', [ 'name' => $itemName ] )"
                aria-controls="{{ $id }}"
                :disabled="$ecommerceDisabled"
                x-bind:aria-disabled="atMin().toString()"
                x-on:click="step( -1 )"
                data-quantity-decrease
            />
        </x-slot:prefix>

        <x-slot:suffix>
            <x-artisanpack-button
                variant="ghost"
                color="neutral"
                :size="$ecommerceButtonSize"
                icon-only
                leading-icon="plus"
                :aria-label="null === $itemName ? __( 'Increase quantity' ) : __( 'Increase quantity, :name', [ 'name' => $itemName ] )"
                aria-controls="{{ $id }}"
                :disabled="$ecommerceDisabled"
                x-bind:aria-disabled="atMax().toString()"
                x-on:click="step( 1 )"
                data-quantity-increase
            />
        </x-slot:suffix>
    </x-artisanpack-input>
</div>
