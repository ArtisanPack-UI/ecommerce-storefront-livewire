{{--
    The header cart button (#93). See Cart\HeaderButton.

    A design-system ghost neutral Button holding the bag icon and a solid
    primary sm Badge with the unit count, hidden while the cart is empty.
    The count is part of the accessible name ("Cart, 2 items"), so the
    badge is hidden from assistive technology.

    Without the cart drawer on the page (or without JavaScript) it is a
    plain link to the cart page.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-cart-button" data-ecommerce-cart-button>
    @if ( null !== $cartUrl )
        <a
            href="{{ $cartUrl }}"
            class="apui-button" data-variant="ghost" data-color="neutral" data-size="sm"
            aria-label="{{ trans_choice( 'Cart, :count item|Cart, :count items', $count, [ 'count' => $count ] ) }}"
            aria-haspopup="dialog"
            aria-controls="ecommerce-cart-drawer-content"
            x-data="{ opened: false }"
            x-on:click="if ( document.querySelector( '[data-ecommerce-cart-drawer]' ) ) { $event.preventDefault(); opened = true; $dispatch( 'ecommerce-cart-open' ); }"
            x-on:ecommerce-cart-closed.window="if ( opened ) { opened = false; $nextTick( () => $el.focus() ); }"
            data-header-action="cart"
        >
            <x-artisanpack-icon name="shopping-bag" size="lg" />
            @if ( $count > 0 )
                <x-artisanpack-badge variant="solid" color="primary" size="sm" aria-hidden="true" data-cart-count>{{ $count }}</x-artisanpack-badge>
            @endif
        </a>
    @endif
</div>
