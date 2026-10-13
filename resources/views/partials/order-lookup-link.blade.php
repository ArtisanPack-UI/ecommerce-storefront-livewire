{{--
    "Ordered as a guest?" link to the guest order lookup (spec §7.6), for
    the host's sign-in screen, which the storefront doesn't own:

        @include( 'ecommerce-storefront::partials.order-lookup-link' )

    Renders nothing when the lookup route isn't registered.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.storefront.lookup' ) )
    <p class="text-sm" data-order-lookup-link>
        {{ __( 'Ordered as a guest?' ) }}
        <a href="{{ route( 'artisanpack.ecommerce.storefront.lookup' ) }}" class="link link-primary">{{ __( 'Find your order' ) }}</a>
    </p>
@endif
