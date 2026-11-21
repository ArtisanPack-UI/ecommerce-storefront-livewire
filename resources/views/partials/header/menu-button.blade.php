{{--
    The header's menu button (#129): a ghost icon-only Button before the
    store's mark, below 768px, that opens the store navigation drawer
    (`partials.header.menu`, through `ecommerce-menu-open`). Focus comes back
    to it when the drawer closes (`ecommerce-menu-closed`).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      2.0.0
--}}
<span class="ec-header__menu" x-data x-on:ecommerce-menu-closed.window="$refs.menuButton.focus()">
    <x-artisanpack-button
        variant="ghost"
        color="neutral"
        size="sm"
        icon-only
        leading-icon="menu"
        :aria-label="__( 'Menu' )"
        aria-haspopup="dialog"
        aria-controls="ecommerce-storefront-menu"
        x-ref="menuButton"
        x-on:click="$dispatch( 'ecommerce-menu-open' )"
        data-storefront-menu-open
    />
</span>
