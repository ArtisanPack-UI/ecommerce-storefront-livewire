{{--
    Moves focus after a line is removed or put back, when the control that
    had it (the line's "Remove", or "Undo") is gone: to the restored line's
    "Remove", else to "Undo", else to `$fallback`. Keyed on the component's
    `lineChanges`, so it runs once per change, and only when focus was
    lost, never taking it from a control the shopper has since moved to.

    Expects `$root` (a selector for the cart's container, which this sits
    in) and `$fallback` (a selector for the last resort).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( $lineChanges > 0 )
    <span
        hidden
        wire:key="cart-focus-{{ $lineChanges }}"
        x-data
        x-init="setTimeout( () => {
            if ( document.activeElement && document.activeElement !== document.body ) {
                return;
            }

            const root = $el.closest( {{ \Illuminate\Support\Js::from( $root ) }} ) ?? document;

            ( root.querySelector( {{ \Illuminate\Support\Js::from( '[data-cart-line="' . ( $restoredLineId ?? 0 ) . '"] [data-cart-line-remove]' ) }} )
                ?? root.querySelector( '[data-cart-undo-button]' )
                ?? root.querySelector( {{ \Illuminate\Support\Js::from( $fallback ) }} ) )?.focus();
        } )"
        data-cart-focus-after-change
    ></span>
@endif
