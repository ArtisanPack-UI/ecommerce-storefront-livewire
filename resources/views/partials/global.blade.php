{{--
    The storefront's page-wide pieces: the toast container and the cart
    merge prompt shown after sign-in. The slide-out cart drawer joins them
    here (#15). Include it once in any layout storefront pages extend,
    after the content:

        @include( 'ecommerce-storefront::partials.global' )

    It renders once per response even when included twice (for example by
    both a host layout and a page), so a layout can include it freely.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@once
    <div data-ecommerce-storefront-global>
        <x-artisanpack-toast />

        <livewire:artisanpack-ecommerce-storefront-cart-merge-prompt />
    </div>
@endonce
