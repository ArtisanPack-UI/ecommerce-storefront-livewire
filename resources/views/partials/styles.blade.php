{{--
    Inlines the storefront's own stylesheet (resources/css/storefront.css):
    the token-only styles the design system has no component for, such as
    the product card, prices and the account navigation. Inlined, so a host
    needs no build step for them. Once per page; the standalone layout puts
    it in `<head>` and `partials.global` includes it for host layouts.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      2.0.0
--}}
@once
    @php( $ecommerceStylesNonce = \Illuminate\Support\Facades\Vite::cspNonce() )
    <style data-ecommerce-storefront-styles @if ( null !== $ecommerceStylesNonce && '' !== $ecommerceStylesNonce ) nonce="{{ $ecommerceStylesNonce }}" @endif>{!! \ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontStyles::css() !!}</style>
@endonce
