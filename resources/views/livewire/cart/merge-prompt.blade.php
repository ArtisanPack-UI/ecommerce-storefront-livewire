{{--
    The cart merge prompt. See MergePrompt.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-ecommerce-cart-merge-prompt>
    @if ( $open )
        <x-artisanpack-modal
            wire:model="open"
            persistent
            aria-labelledby="ecommerce-cart-merge-title"
            aria-describedby="ecommerce-cart-merge-description"
        >
            <h2 id="ecommerce-cart-merge-title" class="mb-4 text-xl font-bold">{{ __( 'Combine your carts?' ) }}</h2>

            <div id="ecommerce-cart-merge-description" class="space-y-2">
                <p>
                    {{ __( 'The cart you filled before signing in is in :guest, but the cart saved to your account is in :account.', [ 'guest' => $guestCurrency, 'account' => $accountCurrency ] ) }}
                </p>
                <p>{{ __( 'Choose the currency for your combined cart. Prices are worked out again in that currency.' ) }}</p>
                <p class="text-sm opacity-75">{{ __( "Don't merge keeps your saved cart and discards the items added before signing in." ) }}</p>
            </div>

            <x-slot:actions>
                <div class="flex flex-wrap justify-end gap-2">
                    @foreach ( $choices as $value => $label )
                        <x-artisanpack-button
                            :label="$label"
                            wire:click="resolve( '{{ $value }}' )"
                            wire:loading.attr="disabled"
                            wire:target="resolve"
                            :class="'cancel_merge' === $value ? 'btn-ghost' : ''"
                            :color="'cancel_merge' === $value ? null : 'primary'"
                            wire:key="cart-merge-{{ $value }}"
                            data-merge-choice="{{ $value }}"
                        />
                    @endforeach
                </div>
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</div>
