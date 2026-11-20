{{--
    The storefront's page-wide pieces: its inline styles (when the layout
    has not already printed them), the toaster (and any toast flashed for
    this page), the slide-out cart drawer, and the cart merge prompt shown
    after sign-in. Include it once in any layout storefront
    pages extend, after the content:

        @include( 'ecommerce-storefront::partials.global' )

    It renders once per response even when included twice (for example by
    both a host layout and a page), so a layout can include it freely.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@once
    @include( 'ecommerce-storefront::partials.styles' )

    <div data-ecommerce-storefront-global>
        <x-artisanpack-toaster />

        @include( 'ecommerce-storefront::partials.flash-toast' )

        <livewire:artisanpack-ecommerce-storefront-cart-drawer />

        <livewire:artisanpack-ecommerce-storefront-cart-merge-prompt />
    </div>
@endonce
