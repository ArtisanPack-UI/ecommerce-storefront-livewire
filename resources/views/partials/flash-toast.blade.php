{{--
    Shows a toast left for the next page by an action that reloaded it (see
    SendsToasts::flashToastSuccess()), once Livewire and Alpine are ready,
    and removes it from the session.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceFlashedToast = app()->bound( 'session' ) ? session()->pull( \ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload::SESSION_KEY ) : null )
@if ( is_array( $ecommerceFlashedToast ) )
    <script data-ecommerce-flash-toast>
        document.addEventListener( 'livewire:initialized', () => {
            setTimeout( () => {
                if ( typeof toast === 'function' ) {
                    toast( {!! \ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload::encode( $ecommerceFlashedToast ) !!} );
                }
            }, 150 );
        } );
    </script>
@endif
