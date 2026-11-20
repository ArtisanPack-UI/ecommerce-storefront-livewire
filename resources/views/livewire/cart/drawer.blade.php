{{--
    The cart drawer. See Cart\Drawer.

    The design system's Drawer: a modal dialog on the end side, opened by the
    `ecommerce-cart-open` browser event and closed by `ecommerce-cart-close`,
    its close button, Escape or the backdrop. Its title, "Your cart", labels
    the dialog and the item count describes it. Focus moves into the panel
    on open and back to the control that opened it on close, and
    `ecommerce-cart-closed` is dispatched whenever it closes, however it was
    closed (its `open` state is watched: setting it in code fires no
    `open-change`).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
{{--
    The drawer often opens from the server (after "Add to cart"), while the
    button that started it is disabled, so the dialog has nothing to restore
    focus to. The last control focused outside the drawer is kept and gets
    focus back when the drawer closes and focus would otherwise be lost.
--}}
<div
    data-ecommerce-cart-drawer
    x-data="{ returnTo: null }"
    x-on:focusin.window="if ( ! document.getElementById( 'ecommerce-cart-drawer-content' )?.contains( $event.target ) ) { returnTo = $event.target }"
    x-on:ecommerce-cart-closed.window="setTimeout( () => { if ( returnTo?.isConnected && ( ! document.activeElement || document.activeElement === document.body ) ) { returnTo.focus() } }, 50 )"
>
    <x-artisanpack-drawer
        id="ecommerce-cart-drawer"
        side="end"
        :title="__( 'Your cart' )"
        :description="trans_choice( ':count item|:count items', $count, [ 'count' => $count ] )"
        :close-label="__( 'Close cart' )"
        x-on:ecommerce-cart-open.window="open = true"
        x-on:ecommerce-cart-close.window="open = false"
        x-init="$watch( 'open', ( isOpen ) => { if ( ! isOpen ) { $dispatch( 'ecommerce-cart-closed' ) } } )"
    >
        <div id="ecommerce-cart-drawer-panel" class="flex min-h-full flex-col gap-4">
            <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ $announcement }}</p>

            @include( 'ecommerce-storefront::livewire.cart.partials.focus-after-change', [ 'root' => '#ecommerce-cart-drawer-panel', 'fallback' => '#ecommerce-cart-drawer-content' ] )

            @if ( null !== $removedLine )
                <div class="flex items-center justify-between gap-2 rounded-box bg-base-200 px-3 py-2 text-sm" data-cart-undo>
                    <span>{{ __( ':name was removed from your cart.', [ 'name' => $removedLine['name'] ] ) }}</span>
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" wire:click="undoRemove" wire:loading.attr="disabled" wire:target="undoRemove" data-cart-undo-button>{{ __( 'Undo' ) }}</x-artisanpack-button>
                </div>
            @endif

            @if ( ! $loaded && $count > 0 )
                {{-- The lines are listed once the drawer first opens. --}}
                <div class="flex grow items-center justify-center py-10 text-sm text-base-content/70" aria-busy="true" data-cart-loading>
                    <p>{{ __( 'Loading…' ) }}</p>
                </div>
            @elseif ( [] === $lines )
                <div class="flex grow flex-col items-center justify-center gap-4 py-10 text-center" data-cart-empty>
                    <x-artisanpack-icon name="o-shopping-cart" class="h-10 w-10 opacity-40" aria-hidden="true" />
                    <p>{{ __( 'Your cart is empty' ) }}</p>
                    @if ( null !== $catalogUrl )
                        <x-artisanpack-button variant="outline" color="neutral" size="sm" :href="$catalogUrl">{{ __( 'Continue shopping' ) }}</x-artisanpack-button>
                    @endif
                </div>
            @else
                <ul class="flex flex-col divide-y divide-base-content/10" data-cart-lines>
                    @foreach ( $lines as $line )
                        @php( $ecommerceField = 'quantities.' . $line['id'] )
                        <li class="flex gap-3 py-3" wire:key="drawer-line-{{ $line['id'] }}" data-cart-line="{{ $line['id'] }}">
                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-box bg-base-200">
                                @if ( null !== $line['image'] )
                                    <img src="{{ $line['image']['url'] }}" @if ( null !== ( $line['image']['srcset'] ?? null ) ) srcset="{{ $line['image']['srcset'] }}" sizes="64px" @endif width="64" height="64" alt="" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                @endif
                            </div>

                            <div class="flex grow flex-col gap-1 text-sm">
                                <div class="flex justify-between gap-2">
                                    <h3 class="font-semibold">
                                        @if ( null !== $line['url'] )
                                            <a href="{{ $line['url'] }}" class="link-hover">{{ $line['name'] }}</a>
                                        @else
                                            {{ $line['name'] }}
                                        @endif
                                    </h3>
                                    <x-artisanpack-ec-money :amount="$line['total']" :currency="$line['currency']" class="font-semibold" />
                                </div>

                                @foreach ( $line['options'] as $option )
                                    <p class="text-base-content/70">{{ $option }}</p>
                                @endforeach

                                @if ( null !== $line['unsellable'] )
                                    <p class="font-semibold" data-cart-line-unsellable @if ( $line['short'] ) data-cart-line-short @endif>{{ $line['unsellable'] }}</p>
                                @endif

                                @if ( $line['free'] )
                                    <x-artisanpack-badge color="success" size="sm" :value="__( 'Free gift' )" />
                                @else
                                    <div class="flex flex-wrap items-end gap-2">
                                        @if ( null === $line['unsellable'] || $line['short'] )
                                            <x-artisanpack-ec-quantity
                                                :id="'ec-drawer-quantity-' . $line['id']"
                                                :item-name="$line['name']"
                                                :min="1"
                                                :max="\ArtisanPackUI\Ecommerce\Services\StorefrontCartService::MAX_LINE_QUANTITY"
                                                wire:model.live.debounce.500ms="{{ $ecommerceField }}"
                                                :error="$errors->first( $ecommerceField )"
                                            />
                                        @endif

                                        <x-artisanpack-button
                                           variant="ghost"
                                           color="neutral"
                                           size="sm"
                                           
                                            :aria-label="__( 'Remove :name', [ 'name' => $line['name'] ] )"
                                            wire:click="removeLine( {{ $line['id'] }} )"
                                            wire:loading.attr="disabled"
                                            wire:target="removeLine( {{ $line['id'] }} )"
                                            data-cart-line-remove>{{ __( 'Remove' ) }}</x-artisanpack-button>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-auto flex flex-col gap-3 border-t border-base-content/10 pt-4">
                    <div class="flex justify-between gap-4 font-semibold">
                        <span>{{ __( 'Subtotal' ) }}</span>
                        <x-artisanpack-ec-money :amount="(int) $cart->subtotal_amount" :currency="(string) $cart->currency" data-cart-subtotal />
                    </div>
                    <p class="text-sm text-base-content/70">{{ __( 'Shipping and tax are calculated at checkout.' ) }}</p>

                    @if ( null !== $cartUrl )
                        <x-artisanpack-button variant="outline" color="neutral" :href="$cartUrl" class="w-full" data-cart-drawer-view>{{ __( 'View cart' ) }}</x-artisanpack-button>
                    @endif

                    @if ( $hasUnsellable )
                        <p class="text-sm font-semibold">{{ $onlyShort ? __( 'Lower the quantities marked to check out.' ) : __( 'Remove the items that can\'t be bought to check out.' ) }}</p>
                    @elseif ( null !== $checkoutUrl )
                        <x-artisanpack-button :href="$checkoutUrl" color="primary" class="w-full" data-cart-drawer-checkout>{{ __( 'Checkout' ) }}</x-artisanpack-button>
                    @endif
                </div>
            @endif
        </div>
    </x-artisanpack-drawer>
</div>
