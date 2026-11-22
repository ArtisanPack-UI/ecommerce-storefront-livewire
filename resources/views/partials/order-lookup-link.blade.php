{{--
    "Ordered as a guest?" link to the guest order lookup (spec §7.6), for
    the host's sign-in screen, which the storefront doesn't own:

        @include( 'ecommerce-storefront::partials.order-lookup-link' )

    Muted body-small text with the design-system Link in its muted tone
    (#98), so it sits quietly under a sign-in form in light, dark and
    right-to-left layouts. The link is a full page load, not wire:navigate:
    the lookup page uses the storefront layout, not the host's.

    Renders nothing when the lookup route isn't registered.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.storefront.lookup' ) )
    <p class="ec-order-lookup-link" data-order-lookup-link>
        {{ __( 'Ordered as a guest?' ) }}
        <x-artisanpack-link :href="route( 'artisanpack.ecommerce.storefront.lookup' )" tone="muted" :navigate="false">{{ __( 'Find your order' ) }}</x-artisanpack-link>
    </p>
@endif
