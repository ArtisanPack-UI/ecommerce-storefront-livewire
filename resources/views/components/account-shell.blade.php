{{--
    The frame every account page sits in: the heading, the account
    navigation, and the page's content. See View\Components\AccountShell.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( 'flex flex-col gap-6' ) }} data-account-shell>
    <h1 class="text-3xl font-bold">{{ $title }}</h1>

    <div class="grid gap-8 lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start">
        <nav aria-label="{{ __( 'Account' ) }}" data-account-nav>
            <x-artisanpack-menu class="w-full rounded-box border border-base-content/10 p-2">
                @foreach ( $entries() as $ecommerceAccountEntry )
                    <x-artisanpack-menu-item
                        :title="$ecommerceAccountEntry['label']"
                        :link="$ecommerceAccountEntry['url']"
                        :icon="$ecommerceAccountEntry['icon']"
                        :active="$ecommerceAccountEntry['active']"
                        role="link"
                        no-wire-navigate
                        :aria-current="$ecommerceAccountEntry['active'] ? 'page' : null"
                        data-account-nav-entry="{{ $ecommerceAccountEntry['key'] }}"
                    />
                @endforeach
            </x-artisanpack-menu>
        </nav>

        <div class="min-w-0">
            {{ $slot }}
        </div>
    </div>
</div>
