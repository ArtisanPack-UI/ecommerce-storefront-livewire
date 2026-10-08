{{--
    Header action: a link to the cart with the number of items in it.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.storefront.cart' ) )
    @php( $ecommerceCartCount = app( \ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart::class )->count() )
    <a
        href="{{ route( 'artisanpack.ecommerce.storefront.cart' ) }}"
        class="btn btn-ghost btn-sm"
        aria-label="{{ trans_choice( 'Cart, :count item|Cart, :count items', $ecommerceCartCount, [ 'count' => $ecommerceCartCount ] ) }}"
        data-header-action="cart"
    >
        <x-artisanpack-icon name="o-shopping-cart" class="h-5 w-5" aria-hidden="true" />
        <span aria-hidden="true">{{ __( 'Cart' ) }}</span>
        @if ( $ecommerceCartCount > 0 )
            <x-artisanpack-badge :value="(string) $ecommerceCartCount" class="badge-primary badge-sm" aria-hidden="true" />
        @endif
    </a>
@endif
