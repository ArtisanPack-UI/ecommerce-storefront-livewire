{{--
    Shows a toast left for the next page by an action that reloaded it (see
    SendsToasts::flashToastSuccess()) on the storefront toaster
    (`<x-artisanpack-ec-toaster>`), once Livewire and Alpine are ready, and
    removes it from the session. The toast keeps its variant, icon and a
    "url" action; a "call" action needs the component that sent it, so it
    is dropped (ToastPayload::script()). The inline script carries the CSP
    nonce Vite was given (`Vite::useCspNonce()`), if any.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceFlashedToast = app()->bound( 'session' ) ? session()->pull( \ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload::SESSION_KEY ) : null;
    $ecommerceCspNonce     = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp
@if ( is_array( $ecommerceFlashedToast ) )
    <script @if ( null !== $ecommerceCspNonce && '' !== $ecommerceCspNonce ) nonce="{{ $ecommerceCspNonce }}" @endif data-ecommerce-flash-toast>
        document.addEventListener( 'livewire:initialized', () => {
            setTimeout( () => {
                {!! \ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload::script( $ecommerceFlashedToast ) !!}
            }, 150 );
        } );
    </script>
@endif
