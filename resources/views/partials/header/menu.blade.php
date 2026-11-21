{{--
    The store navigation drawer (#129), below 768px: a start-side
    design-system Drawer titled with the store's name, listing `$navigation`
    (Support\StorefrontNavigation::header()) with the current page marked.
    The menu button opens it (`ecommerce-menu-open`); its close button,
    Escape or the backdrop close it, and `ecommerce-menu-closed` sends focus
    back to the menu button.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      2.0.0
--}}
<x-artisanpack-drawer
    side="start"
    id="ecommerce-storefront-menu"
    size="sm"
    :title="(string) config( 'app.name' )"
    :close-label="__( 'Close menu' )"
    x-on:ecommerce-menu-open.window="open = true"
    x-init="$watch( 'open', ( isOpen ) => { if ( ! isOpen ) { $dispatch( 'ecommerce-menu-closed' ) } } )"
    data-storefront-menu
>
    <nav aria-label="{{ __( 'Store' ) }}">
        <ul role="list" class="ec-menu-list">
            @foreach ( $navigation as $ecommerceLink )
                <li>
                    <a href="{{ $ecommerceLink['url'] }}" class="ec-menu-list__link" @if ( $ecommerceLink['current'] ) aria-current="page" @endif>{{ $ecommerceLink['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
</x-artisanpack-drawer>
