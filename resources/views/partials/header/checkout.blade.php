{{--
    The checkout's slim secure header (#136), in place of the store header
    on the checkout page (see layouts/app). The store's mark and name
    (linking to `$homeUrl`), "Secure checkout" with a lock (from 640px),
    and at the end "Back to cart", a muted standalone Link whose arrow
    points back in either direction. It is the page's one banner landmark.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      2.0.0
--}}
<header class="ec-checkout-header" data-checkout-header>
    <div class="ec-checkout-header__inner">
        <a href="{{ $homeUrl }}" class="ec-brand">
            <span class="ec-brand__mark" aria-hidden="true">{{ \ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontNavigation::mark( $storeName ) }}</span>
            <span class="ec-brand__name">{{ $storeName }}</span>
        </a>

        <span class="ec-checkout-header__secure">
            <x-artisanpack-icon name="lock" aria-hidden="true" />
            <span>{{ __( 'Secure checkout' ) }}</span>
        </span>

        @if ( \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.storefront.cart' ) )
            <x-artisanpack-link :href="route( 'artisanpack.ecommerce.storefront.cart' )" tone="muted" standalone leading-icon="arrow-left" :navigate="false" class="ec-checkout-header__back" data-checkout-back-to-cart>{{ __( 'Back to cart' ) }}</x-artisanpack-link>
        @endif
    </div>
</header>
