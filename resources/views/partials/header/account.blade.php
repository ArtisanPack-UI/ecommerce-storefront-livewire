{{--
    Header action: the account link for signed-in shoppers, else a sign-in
    link to the host's `auth.login_route`. A ghost icon-only Button (the
    user icon) named "Account" or "Sign in" (#129).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceLoginRoute = (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.login_route', 'login' );
    $ecommerceAccount    = auth()->check()
        ? ( \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.account.dashboard' ) ? [ route( 'artisanpack.ecommerce.account.dashboard' ), __( 'Account' ) ] : null )
        : ( '' !== $ecommerceLoginRoute && \Illuminate\Support\Facades\Route::has( $ecommerceLoginRoute ) ? [ route( $ecommerceLoginRoute ), __( 'Sign in' ) ] : null );
@endphp
@if ( null !== $ecommerceAccount )
    <x-artisanpack-button
        variant="ghost"
        color="neutral"
        size="sm"
        icon-only
        leading-icon="user"
        :href="$ecommerceAccount[0]"
        :aria-label="$ecommerceAccount[1]"
        data-header-action="account"
    />
@endif
