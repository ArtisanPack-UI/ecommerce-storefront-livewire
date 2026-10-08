{{--
    The standalone storefront layout (spec §5.3).

    Used unless `storefront.layout` points at the host's own layout. Any
    layout a host uses must match this contract: a `title` section (plain
    text), a `content` section, `styles` / `scripts` stacks, and an include
    of `ecommerce-storefront::partials.global` (cart drawer, toasts, cart
    merge prompt).

    The package ships no CSS build. The host's Vite entries (filterable with
    `ap.ecommerceStorefrontLivewire.layout.viteEntries`) are loaded when a
    Vite build or dev server is present; the host adds this package's views
    to its Tailwind `@source` list (see `php artisan ecommerce-storefront:install`).

    The header search box comes from `ecommerce-storefront::partials.header.search`.
    Header actions (currency switcher, account, cart, and anything a
    satellite adds, such as a wishlist) come from the `ap.ecommerceStorefrontLivewire.header.actions`
    filter: key => view name or Htmlable.

    Publish with `php artisan vendor:publish --tag=ecommerce-storefront-views`.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    use Illuminate\Contracts\Support\Htmlable;
    use Illuminate\Support\Facades\Route;

    $ecommerceHeaderActions = (array) applyFilters( 'ap.ecommerceStorefrontLivewire.header.actions', [
        'currency' => 'ecommerce-storefront::partials.header.currency',
        'account'  => 'ecommerce-storefront::partials.header.account',
        'cart'     => 'ecommerce-storefront::partials.header.cart',
    ] );
    $ecommerceHasCatalog = Route::has( 'artisanpack.ecommerce.storefront.catalog' );
    $ecommerceHasSearch  = Route::has( 'artisanpack.ecommerce.storefront.search' );
    $ecommerceHasLookup  = Route::has( 'artisanpack.ecommerce.storefront.lookup' );
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield( 'title', __( 'Shop' ) ) &middot; {{ config( 'app.name' ) }}</title>
    @include( 'ecommerce-storefront::partials.vite-assets' )
    @livewireStyles
    @stack( 'styles' )
</head>
<body class="flex min-h-screen flex-col bg-base-100 font-sans antialiased">
    <a href="#ecommerce-storefront-content" class="btn btn-sm sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50">
        {{ __( 'Skip to content' ) }}
    </a>

    <header>
        <x-artisanpack-nav sticky>
            <x-slot:brand class="gap-6">
                <a href="{{ $ecommerceHasCatalog ? route( 'artisanpack.ecommerce.storefront.catalog' ) : url( '/' ) }}" class="text-lg font-bold">
                    {{ config( 'app.name' ) }}
                </a>

                @if ( $ecommerceHasCatalog )
                    <nav aria-label="{{ __( 'Store' ) }}" class="hidden sm:block">
                        <a href="{{ route( 'artisanpack.ecommerce.storefront.catalog' ) }}" class="link link-hover">{{ __( 'Shop' ) }}</a>
                    </nav>
                @endif
            </x-slot:brand>

            <x-slot:actions>
                @if ( $ecommerceHasSearch )
                    @include( 'ecommerce-storefront::partials.header.search' )
                @endif

                @foreach ( $ecommerceHeaderActions as $ecommerceActionKey => $ecommerceAction )
                    @if ( $ecommerceAction instanceof Htmlable )
                        {{ $ecommerceAction }}
                    @elseif ( is_string( $ecommerceAction ) && view()->exists( $ecommerceAction ) )
                        @include( $ecommerceAction )
                    @endif
                @endforeach

                <x-artisanpack-theme-toggle aria-label="{{ __( 'Toggle dark mode' ) }}" />
            </x-slot:actions>
        </x-artisanpack-nav>
    </header>

    <x-artisanpack-main>
        <x-slot:content id="ecommerce-storefront-content" tabindex="-1">
            @yield( 'content' )
        </x-slot:content>

        <x-slot:footer class="mt-auto border-t border-base-content/10">
            <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-6 text-sm lg:px-10">
                <p>&copy; {{ now()->year }} {{ config( 'app.name' ) }}</p>

                @if ( $ecommerceHasLookup )
                    <nav aria-label="{{ __( 'Customer service' ) }}">
                        <a href="{{ route( 'artisanpack.ecommerce.storefront.lookup' ) }}" class="link link-hover">{{ __( 'Find an order' ) }}</a>
                    </nav>
                @endif
            </div>
        </x-slot:footer>
    </x-artisanpack-main>

    @include( 'ecommerce-storefront::partials.global' )

    @livewireScripts
    @stack( 'scripts' )
</body>
</html>
