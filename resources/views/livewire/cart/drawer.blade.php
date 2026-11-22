{{--
    The cart drawer (#95). See Cart\Drawer.

    The design system's Drawer: a modal dialog on the end side, 420px wide
    and full width on a phone, opened by the `ecommerce-cart-open` browser
    event and closed by `ecommerce-cart-close`, its close button, Escape or
    the scrim. Its title, "Your cart", labels the dialog and the item count
    beside it describes it. Focus moves into the panel on open and back to
    the control that opened it on close, and `ecommerce-cart-closed` is
    dispatched whenever it closes, however it was closed (its `open` state
    is watched: setting it in code fires no `open-change`).

    The body holds the free-shipping meter (a success Progress) and the
    lines: a 64px thumbnail, the name, the options, the stepper and a
    Remove link, and the line total. A free gift shows a "Free gift" badge
    and no stepper. The footer holds the subtotal, the note about shipping
    and tax, and View cart (outline) beside Checkout (solid).

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
        class="ec-cart-drawer"
        :title="__( 'Your cart' )"
        :description="trans_choice( ':count item|:count items', $count, [ 'count' => $count ] )"
        :close-label="__( 'Close cart' )"
        x-on:ecommerce-cart-open.window="open = true"
        x-on:ecommerce-cart-close.window="open = false"
        x-init="$watch( 'open', ( isOpen ) => { if ( ! isOpen ) { $dispatch( 'ecommerce-cart-closed' ) } } )"
    >
        <div id="ecommerce-cart-drawer-panel" class="ec-cart-drawer__panel">
            <p class="apui-sr-only" role="status" aria-live="polite" aria-atomic="true">{{ $announcement }}</p>

            @include( 'ecommerce-storefront::livewire.cart.partials.focus-after-change', [ 'root' => '#ecommerce-cart-drawer-panel', 'fallback' => '#ecommerce-cart-drawer-content' ] )

            @if ( null !== $removedLine )
                <div class="ec-cart-drawer__undo" data-cart-undo>
                    <span>{{ __( ':name was removed from your cart.', [ 'name' => $removedLine['name'] ] ) }}</span>
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" wire:click="undoRemove" wire:loading.attr="disabled" wire:target="undoRemove" data-cart-undo-button>{{ __( 'Undo' ) }}</x-artisanpack-button>
                </div>
            @endif

            @if ( ! $loaded && $count > 0 )
                {{-- The lines are listed once the drawer first opens. --}}
                <div class="ec-cart-drawer__loading" aria-busy="true" data-cart-loading>
                    <p>{{ __( 'Loading…' ) }}</p>
                </div>
            @elseif ( [] === $lines )
                <x-artisanpack-ec-empty-state icon="shopping-bag" :title="__( 'Your cart is empty' )" size="sm" :heading-level="3" class="ec-cart-drawer__empty" live data-cart-empty>
                    @if ( null !== $catalogUrl )
                        <x-artisanpack-button variant="outline" color="neutral" size="sm" :href="$catalogUrl">{{ __( 'Continue shopping' ) }}</x-artisanpack-button>
                    @endif
                </x-artisanpack-ec-empty-state>
            @else
                @if ( null !== $freeShipping )
                    <div class="ec-cart-drawer__shipping" data-cart-free-shipping @if ( $freeShipping['unlocked'] ) data-unlocked @endif>
                        <p id="ecommerce-cart-drawer-shipping" class="ec-cart-drawer__shipping-text">
                            <x-artisanpack-icon name="truck" />
                            <span>{{ $freeShipping['unlocked'] ? __( 'You\'ve unlocked free shipping' ) : __( 'Add :amount more for free shipping', [ 'amount' => $freeShipping['remaining'] ] ) }}</span>
                        </p>
                        <x-artisanpack-progress :value="$freeShipping['percent']" color="success" size="sm" aria-labelledby="ecommerce-cart-drawer-shipping" />
                    </div>
                @endif

                <ul class="ec-cart-drawer__lines" data-cart-lines>
                    @foreach ( $lines as $line )
                        @php( $ecommerceField = 'quantities.' . $line['id'] )
                        <li class="ec-cart-line" wire:key="drawer-line-{{ $line['id'] }}" data-cart-line="{{ $line['id'] }}">
                            <span class="ec-cart-line__thumb">
                                @if ( null !== $line['image'] )
                                    <img src="{{ $line['image']['url'] }}" @if ( null !== ( $line['image']['srcset'] ?? null ) ) srcset="{{ $line['image']['srcset'] }}" sizes="64px" @endif width="64" height="64" alt="" loading="lazy" decoding="async">
                                @endif
                            </span>

                            <div class="ec-cart-line__body">
                                <div class="ec-cart-line__head">
                                    <h3 class="ec-cart-line__name">
                                        @if ( null !== $line['url'] )
                                            <a href="{{ $line['url'] }}">{{ $line['name'] }}</a>
                                        @else
                                            {{ $line['name'] }}
                                        @endif
                                    </h3>

                                    @if ( $line['free'] )
                                        <span class="ec-cart-line__total" data-cart-line-total>{{ __( 'Free' ) }}</span>
                                    @else
                                        <x-artisanpack-ec-money :amount="$line['total']" :currency="$line['currency']" class="ec-cart-line__total" data-cart-line-total />
                                    @endif
                                </div>

                                @if ( [] !== $line['options'] )
                                    <p class="ec-cart-line__options">{{ implode( ' · ', $line['options'] ) }}</p>
                                @endif

                                @if ( null !== $line['unsellable'] )
                                    <p class="ec-cart-line__problem" data-cart-line-unsellable @if ( $line['short'] ) data-cart-line-short @endif>{{ $line['unsellable'] }}</p>
                                @endif

                                @if ( $line['free'] )
                                    <x-artisanpack-badge variant="soft" color="success" size="sm" leading-icon="gift" data-cart-line-free>{{ __( 'Free gift' ) }}</x-artisanpack-badge>
                                @else
                                    <div class="ec-cart-line__controls">
                                        @if ( null === $line['unsellable'] || $line['short'] )
                                            <x-artisanpack-ec-quantity
                                                :id="'ec-drawer-quantity-' . $line['id']"
                                                :item-name="$line['name']"
                                                size="xs"
                                                :min="1"
                                                :max="\ArtisanPackUI\Ecommerce\Services\StorefrontCartService::MAX_LINE_QUANTITY"
                                                wire:model.live.change="{{ $ecommerceField }}"
                                                :error="$errors->first( $ecommerceField )"
                                            />
                                        @endif

                                        <x-artisanpack-button
                                            variant="link"
                                            color="neutral"
                                            size="sm"
                                            :aria-label="__( 'Remove :name', [ 'name' => $line['name'] ] )"
                                            wire:click="removeLine( {{ $line['id'] }} )"
                                            wire:loading.attr="disabled"
                                            wire:target="removeLine( {{ $line['id'] }} )"
                                            data-cart-line-remove
                                        >{{ __( 'Remove' ) }}</x-artisanpack-button>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ( $loaded && [] !== $lines )
            <x-slot:footer>
                <div class="ec-cart-drawer__footer">
                    <div class="ec-cart-drawer__subtotal">
                        <span>{{ __( 'Subtotal' ) }}</span>
                        <x-artisanpack-ec-money :amount="(int) $cart->subtotal_amount" :currency="(string) $cart->currency" data-cart-subtotal />
                    </div>
                    <p class="ec-cart-drawer__note">{{ __( 'Shipping and tax are calculated at checkout.' ) }}</p>

                    @if ( $hasUnsellable )
                        <p class="ec-cart-drawer__blocked" data-cart-drawer-blocked>{{ $onlyShort ? __( 'Lower the quantities marked to check out.' ) : __( 'Remove the items that can\'t be bought to check out.' ) }}</p>
                    @endif

                    <div class="ec-cart-drawer__actions">
                        @if ( null !== $cartUrl )
                            <x-artisanpack-button variant="outline" color="neutral" :href="$cartUrl" data-cart-drawer-view>{{ __( 'View cart' ) }}</x-artisanpack-button>
                        @endif

                        @if ( ! $hasUnsellable && null !== $checkoutUrl )
                            <x-artisanpack-button :href="$checkoutUrl" color="primary" data-cart-drawer-checkout>{{ __( 'Checkout' ) }}</x-artisanpack-button>
                        @endif
                    </div>
                </div>
            </x-slot:footer>
        @endif
    </x-artisanpack-drawer>
</div>
