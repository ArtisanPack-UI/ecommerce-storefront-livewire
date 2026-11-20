{{--
    The cart merge prompt. See MergePrompt.

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
        --}}
        <x-artisanpack-dialog
            id="ecommerce-cart-merge"
            wire:model="open"
            role="alertdialog"
            hide-close
            :title="__( 'Combine your carts?' )"
            :description="__( 'The cart you filled before signing in is in :guest, but the cart saved to your account is in :account.', [ 'guest' => $guestCurrency, 'account' => $accountCurrency ] )"
            x-on:keydown.escape.window.capture.stop.prevent=""
        >
            <div id="ecommerce-cart-merge-details" class="space-y-2">
                <p>{{ __( 'Choose the currency for your combined cart. Prices are worked out again in that currency.' ) }}</p>
                <p class="text-sm opacity-75">{{ __( "Don't merge keeps your saved cart and discards the items added before signing in." ) }}</p>
            </div>

            <x-slot:footer>
                <div class="flex flex-wrap justify-end gap-2">
                    @foreach ( $choices as $value => $label )
                        <x-artisanpack-button
                            wire:click="resolve( '{{ $value }}' )"
                            wire:loading.attr="disabled"
                            wire:target="resolve"
                            :variant="'cancel_merge' === $value ? 'ghost' : 'solid'"
                            :color="'cancel_merge' === $value ? 'neutral' : 'primary'"
                            wire:key="cart-merge-{{ $value }}"
                            data-merge-choice="{{ $value }}">{{ $label }}</x-artisanpack-button>
                    @endforeach
                </div>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
