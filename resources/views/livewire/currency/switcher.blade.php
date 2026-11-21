{{--
    The currency switcher (#94). See Currency\Switcher.

    A design-system DropdownMenu: the trigger (an outline neutral Button)
    shows the current code ("USD") after a hidden "Currency", so its name
    says what it changes. The menu lists each currency as a radio item, its
    name with the code as a mono shortcut and a check on the current one
    (`menuitemradio` with `aria-checked`). Choosing one sets `currency`,
    which switches the shopper's currency and reloads the page. Each copy
    on a page (`placement`) has its own menu id.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-currency" data-ecommerce-currency-switcher data-currency-placement="{{ $placement }}">
    @if ( $show )
        <x-artisanpack-dropdown-menu
            :id="$menuId"
            align="end"
            :items="$items"
            x-on:checked-change="$event.detail.checked && $event.detail.value !== $wire.currency && $wire.set( 'currency', $event.detail.value )"
            data-currency-menu
        >
            <x-slot:trigger><span class="apui-sr-only">{{ __( 'Currency' ) }}</span> <span data-currency-code>{{ $currency }}</span></x-slot:trigger>
        </x-artisanpack-dropdown-menu>

        @error( 'currency' )
            <p class="ec-currency__error" role="alert" data-currency-error>{{ $message }}</p>
        @enderror
    @endif
</div>
