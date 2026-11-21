{{--
    The standalone storefront layout (spec §5.3, #129).

    Used unless `storefront.layout` points at the host's own layout. Any
    layout a host uses must match this contract: a `title` section (plain
    text), a `content` section, `styles` / `scripts` stacks, and an include
    of `ecommerce-storefront::partials.global` (cart drawer, toasts, cart
    merge prompt). A `head` stack inside `<head>` receives each page's
    description, canonical URL, robots directive, and structured data
    (`ecommerce-storefront::partials.seo`).

    The package ships no CSS build. The design system's CSS and behavior come
    from the host's Vite entries (filterable with
    `ap.ecommerceStorefrontLivewire.layout.viteEntries`), loaded when a Vite
    build or dev server is present. The storefront's own token-only styles
    are inlined (see partials/styles), and `@apuiThemeScript` applies the
    shopper's light or dark choice before the first paint.

    The page, in order:

    - "Skip to content", the first focusable element, shown when focused.
    - The sticky design-system Navbar, its content 1232px wide with 24px
      gutters (16px below 768px): the store's mark and name, the store
      navigation ("Shop all" and the top-level categories, from
      Support\StorefrontNavigation), the header search
      (`partials.header.search`) and the header actions. Below 768px a menu
      button before the mark opens the store navigation in a start-side
      Drawer (`partials.header.menu`), and the actions are icons. Header
      actions (currency switcher, account, cart, and anything a satellite
      adds, such as a wishlist) come from the
      `ap.ecommerceStorefrontLivewire.header.actions` filter: key => view
      name or Htmlable.
    - The page content, 1232px wide with the same gutters.
    - The footer (`partials.footer`): the mark, name and `storefront.tagline`,
      the Shop / Help / Account link columns, then the copyright, the
      currency switcher and the theme toggle.

    Publish with `php artisan vendor:publish --tag=ecommerce-storefront-views`.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    use Illuminate\Contracts\Support\Htmlable;
    use Illuminate\Support\Facades\Route;
    use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontNavigation;

    $ecommerceHeaderActions = (array) applyFilters( 'ap.ecommerceStorefrontLivewire.header.actions', [
        'currency' => 'ecommerce-storefront::partials.header.currency',
        'account'  => 'ecommerce-storefront::partials.header.account',
        'cart'     => 'ecommerce-storefront::partials.header.cart',
    ] );
    $ecommerceStoreName  = (string) config( 'app.name' );
    $ecommerceHomeUrl    = Route::has( StorefrontNavigation::CATALOG_ROUTE ) ? route( StorefrontNavigation::CATALOG_ROUTE ) : url( '/' );
    $ecommerceNavigation = StorefrontNavigation::header();
    $ecommerceHasSearch  = Route::has( 'artisanpack.ecommerce.storefront.search' );
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}" dir="{{ \ArtisanPackUI\EcommerceStorefrontLivewire\Support\TextDirection::for() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield( 'title', __( 'Shop' ) ) &middot; {{ $ecommerceStoreName }}</title>
    @apuiThemeScript
    @include( 'ecommerce-storefront::partials.vite-assets' )
    @include( 'ecommerce-storefront::partials.styles' )
    @livewireStyles
    @stack( 'head' )
    @if ( \ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontSeo::seoPackageInstalled() )
        <x-dynamic-component component="seo:schema" />
    @endif
    @stack( 'styles' )
</head>
<body class="ec-storefront">
    <a href="#ecommerce-storefront-content" class="apui-button ec-skip-link" data-variant="solid" data-color="primary" data-size="sm" data-skip-link>{{ __( 'Skip to content' ) }}</a>

    <x-artisanpack-navbar sticky :label="__( 'Store' )" class="ec-header" data-storefront-header>
        <x-slot:brand>
            @if ( [] !== $ecommerceNavigation )
                @include( 'ecommerce-storefront::partials.header.menu-button' )
            @endif

            <a href="{{ $ecommerceHomeUrl }}" class="ec-brand" data-storefront-brand>
                <span class="ec-brand__mark" aria-hidden="true">{{ StorefrontNavigation::mark( $ecommerceStoreName ) }}</span>
                <span class="ec-brand__name">{{ $ecommerceStoreName }}</span>
            </a>
        </x-slot:brand>

        @foreach ( $ecommerceNavigation as $ecommerceLink )
            <x-artisanpack-navbar-item :href="$ecommerceLink['url']" :current="$ecommerceLink['current']" :navigate="false" data-storefront-nav-link>{{ $ecommerceLink['label'] }}</x-artisanpack-navbar-item>
        @endforeach

        @if ( $ecommerceHasSearch )
            <x-slot:search>
                @include( 'ecommerce-storefront::partials.header.search' )
            </x-slot:search>
        @endif

        <x-slot:actions>
            @foreach ( $ecommerceHeaderActions as $ecommerceActionKey => $ecommerceAction )
                @if ( $ecommerceAction instanceof Htmlable )
                    {{ $ecommerceAction }}
                @elseif ( is_string( $ecommerceAction ) && view()->exists( $ecommerceAction ) )
                    @include( $ecommerceAction )
                @endif
            @endforeach
        </x-slot:actions>
    </x-artisanpack-navbar>

    @if ( [] !== $ecommerceNavigation )
        @include( 'ecommerce-storefront::partials.header.menu', [ 'navigation' => $ecommerceNavigation ] )
    @endif

    <main class="ec-main">
        <div id="ecommerce-storefront-content" tabindex="-1" class="ec-main__content">
            @yield( 'content' )
        </div>
    </main>

    @include( 'ecommerce-storefront::partials.footer', [ 'storeName' => $ecommerceStoreName, 'homeUrl' => $ecommerceHomeUrl ] )

    @include( 'ecommerce-storefront::partials.global' )

    @livewireScripts
    @stack( 'scripts' )
</body>
</html>
