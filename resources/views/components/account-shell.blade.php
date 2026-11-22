{{--
    Account shell (#72). See View\Components\AccountShell.

    Design-system parts with the layout in resources/css/storefront.css
    (ec-account): from 1024px a 232px sidebar (Avatar, name and email, the
    account Menu, a Divider and a ghost "Sign out" Button) beside the
    content, 32px apart; below 1024px the sidebar hides and the same entries
    show as a row of tabs that scrolls sideways. Both are
    `<nav aria-label="Account">`; only one shows at a time. The current page
    carries `aria-current="page"`. The content starts with a PageHeader
    holding the page's one h1; without a title the content brings its own.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceAccountEntries = $entries();
    $ecommerceAccountShopper = $shopper();
    $ecommerceSignOutUrl     = $signOutUrl();
@endphp
<div {{ $attributes->class( 'ec-account' ) }} data-account-shell>
    <div class="ec-account__sidebar" data-account-sidebar>
        @if ( null !== $ecommerceAccountShopper )
            <div class="ec-account__identity" data-account-identity>
                {{-- Decorative: the name is written beside it. --}}
                <x-artisanpack-avatar :initials="$ecommerceAccountShopper['initials']" color="primary" />
                <div class="ec-account__who">
                    <p class="ec-account__name">{{ $ecommerceAccountShopper['name'] }}</p>
                    @if ( '' !== $ecommerceAccountShopper['email'] && $ecommerceAccountShopper['email'] !== $ecommerceAccountShopper['name'] )
                        <p class="ec-account__email">{{ $ecommerceAccountShopper['email'] }}</p>
                    @endif
                </div>
            </div>
        @endif

        <x-artisanpack-menu :label="__( 'Account' )" :collapsible="false" data-account-nav>
            @foreach ( $ecommerceAccountEntries as $ecommerceAccountEntry )
                <x-artisanpack-menu-item
                    :label="$ecommerceAccountEntry['label']"
                    :href="$ecommerceAccountEntry['url']"
                    :icon="$ecommerceAccountEntry['icon']"
                    :current="$ecommerceAccountEntry['active']"
                    :navigate="false"
                    data-account-nav-entry="{{ $ecommerceAccountEntry['key'] }}"
                />
            @endforeach
        </x-artisanpack-menu>

        @if ( null !== $ecommerceSignOutUrl )
            <x-artisanpack-divider decorative />

            <form method="POST" action="{{ $ecommerceSignOutUrl }}" class="ec-account__signout" data-account-sign-out>
                @csrf
                <x-artisanpack-button type="submit" variant="ghost" color="neutral" leading-icon="log-out">{{ __( 'Sign out' ) }}</x-artisanpack-button>
            </form>
        @endif
    </div>

    <nav aria-label="{{ __( 'Account' ) }}" class="ec-account__tabs" data-account-tabs>
        <ul>
            @foreach ( $ecommerceAccountEntries as $ecommerceAccountEntry )
                <li>
                    <a
                        href="{{ $ecommerceAccountEntry['url'] }}"
                        class="ec-account__tab"
                        @if ( $ecommerceAccountEntry['active'] ) aria-current="page" @endif
                        data-account-tab="{{ $ecommerceAccountEntry['key'] }}"
                    >{{ $ecommerceAccountEntry['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="ec-account__content">
        @if ( null !== $title && '' !== $title )
            <x-artisanpack-page-header :title="$title" :level="1" class="ec-account__title" />
        @endif

        {{ $slot }}
    </div>
</div>
