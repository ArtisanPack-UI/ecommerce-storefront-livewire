{{--
    Header action: the account link for signed-in shoppers, else a sign-in
    link to the host's `auth.login_route`.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceLoginRoute = (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.login_route', 'login' );
@endphp
@auth
    @if ( \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.account.dashboard' ) )
        <a href="{{ route( 'artisanpack.ecommerce.account.dashboard' ) }}" class="btn btn-ghost btn-sm" data-header-action="account">
            <x-artisanpack-icon name="o-user" class="h-5 w-5" aria-hidden="true" />
            <span>{{ __( 'Account' ) }}</span>
        </a>
    @endif
@else
    @if ( '' !== $ecommerceLoginRoute && \Illuminate\Support\Facades\Route::has( $ecommerceLoginRoute ) )
        <a href="{{ route( $ecommerceLoginRoute ) }}" class="btn btn-ghost btn-sm" data-header-action="account">
            <x-artisanpack-icon name="o-user" class="h-5 w-5" aria-hidden="true" />
            <span>{{ __( 'Sign in' ) }}</span>
        </a>
    @endif
@endauth
