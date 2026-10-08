{{--
    The header cart button. See Cart\HeaderButton.

    Without the cart drawer on the page (or without JavaScript) it is a
    plain link to the cart page.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="inline-flex" data-ecommerce-cart-button>
    @if ( null !== $cartUrl )
        <a
            href="{{ $cartUrl }}"
            class="btn btn-ghost btn-sm"
            aria-label="{{ trans_choice( 'Cart, :count item|Cart, :count items', $count, [ 'count' => $count ] ) }}"
            aria-haspopup="dialog"
            aria-controls="ecommerce-cart-drawer-panel"
            x-data="{ opened: false }"
            x-on:click="if ( document.querySelector( '[data-ecommerce-cart-drawer]' ) ) { $event.preventDefault(); opened = true; $dispatch( 'ecommerce-cart-open' ); }"
            x-on:ecommerce-cart-closed.window="if ( opened ) { opened = false; $nextTick( () => $el.focus() ); }"
            data-header-action="cart"
        >
            <x-artisanpack-icon name="o-shopping-cart" class="h-5 w-5" aria-hidden="true" />
            <span aria-hidden="true">{{ __( 'Cart' ) }}</span>
            @if ( $count > 0 )
                <x-artisanpack-badge :value="(string) $count" class="badge-primary badge-sm" aria-hidden="true" data-cart-count />
            @endif
        </a>
    @endif
</div>
