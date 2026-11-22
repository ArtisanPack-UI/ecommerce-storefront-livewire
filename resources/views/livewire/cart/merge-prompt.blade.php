{{--
    The cart merge prompt (#96). See MergePrompt.

    The design system's Dialog with its icon (the bag on a soft primary
    circle), title, description and footer. The primary action keeps the
    guest cart's currency (solid), then switching to the account's currency
    (outline) and "Don't merge" (ghost). Focus starts on the primary action.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-ecommerce-cart-merge-prompt>
    @if ( $open )
        {{--
            An alertdialog: the shopper must choose, so the backdrop doesn't
            close it, there is no close button, and Escape is caught before
            the dialog machine sees it. The first sentence describes it.
            The machine focuses the first button; the primary action is
            focused once it has.
        --}}
        <x-artisanpack-dialog
            id="ecommerce-cart-merge"
            wire:model="open"
            role="alertdialog"
            color="primary"
            icon="shopping-bag"
            hide-close
            class="ec-merge-prompt"
            :title="__( 'Combine your carts?' )"
            :description="__( 'The cart you filled before signing in is in :guest, but the cart saved to your account is in :account.', [ 'guest' => $guestCurrency, 'account' => $accountCurrency ] )"
            x-on:keydown.escape.window.capture.stop.prevent=""
            x-init="const focusPrimary = () => setTimeout( () => document.getElementById( 'ecommerce-cart-merge-primary' )?.focus(), 80 ); if ( open ) { focusPrimary() } $watch( 'open', ( isOpen ) => isOpen && focusPrimary() )"
        >
            <div id="ecommerce-cart-merge-details" class="ec-merge-prompt__details">
                <p>{{ __( 'Choose the currency for your combined cart. Prices are worked out again in that currency.' ) }}</p>
                <p class="ec-merge-prompt__note">{{ __( "Don't merge keeps your saved cart and discards the items added before signing in." ) }}</p>
            </div>

            <x-slot:footer>
                @foreach ( $choices as $value => $choice )
                    <x-artisanpack-button
                        wire:click="resolve( '{{ $value }}' )"
                        wire:loading.attr="disabled"
                        wire:target="resolve"
                        :variant="$choice['variant']"
                        :color="'solid' === $choice['variant'] ? 'primary' : 'neutral'"
                        :id="'solid' === $choice['variant'] ? 'ecommerce-cart-merge-primary' : null"
                        wire:key="cart-merge-{{ $value }}"
                        data-merge-choice="{{ $value }}">{{ $choice['label'] }}</x-artisanpack-button>
                @endforeach
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
