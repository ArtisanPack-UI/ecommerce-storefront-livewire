{{--
    The cart drawer. See Cart\Drawer.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-ecommerce-cart-drawer x-data x-on:close="$dispatch( 'ecommerce-cart-closed' )">
    <x-artisanpack-drawer
        id="ecommerce-cart-drawer"
        right
        open-on="ecommerce-cart-open"
        close-on="ecommerce-cart-close"
        close-on-escape
        class="w-96 max-w-full"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ecommerce-cart-drawer-title"
    >
        <div id="ecommerce-cart-drawer-panel" class="flex min-h-full flex-col gap-4">
            <div class="flex items-center justify-between gap-4">
                <h2 id="ecommerce-cart-drawer-title" class="text-xl font-bold">
                    {{ __( 'Your cart' ) }}
                    <span class="text-base font-normal text-base-content/70">({{ trans_choice( ':count item|:count items', $count, [ 'count' => $count ] ) }})</span>
                </h2>

                <x-artisanpack-button
                    icon="o-x-mark"
                    class="btn-ghost btn-sm btn-circle"
                    :aria-label="__( 'Close cart' )"
                    x-on:click="$dispatch( 'ecommerce-cart-close' )"
                    data-cart-drawer-close
                />
            </div>

            <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ $announcement }}</p>

            @if ( null !== $removedLine )
                <div class="flex items-center justify-between gap-2 rounded-box bg-base-200 px-3 py-2 text-sm" data-cart-undo>
                    <span>{{ __( ':name was removed from your cart.', [ 'name' => $removedLine['name'] ] ) }}</span>
                    <x-artisanpack-button :label="__( 'Undo' )" class="btn-xs" wire:click="undoRemove" wire:loading.attr="disabled" wire:target="undoRemove" data-cart-undo-button />
                </div>
            @endif

            @if ( [] === $lines )
                <div class="flex grow flex-col items-center justify-center gap-4 py-10 text-center" data-cart-empty>
                    <x-artisanpack-icon name="o-shopping-cart" class="h-10 w-10 opacity-40" aria-hidden="true" />
                    <p>{{ __( 'Your cart is empty' ) }}</p>
                    @if ( null !== $catalogUrl )
                        <x-artisanpack-button :label="__( 'Continue shopping' )" :link="$catalogUrl" class="btn-sm" />
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
                                    <p class="text-warning" data-cart-line-unsellable>{{ $line['unsellable'] }}</p>
                                @endif

                                @if ( $line['free'] )
                                    <x-artisanpack-badge :value="__( 'Free gift' )" class="badge-success badge-sm" />
                                @else
                                    <div class="flex flex-wrap items-end gap-2">
                                        @if ( null === $line['unsellable'] )
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
                                            :label="__( 'Remove' )"
                                            class="btn-ghost btn-xs"
                                            :aria-label="__( 'Remove :name', [ 'name' => $line['name'] ] )"
                                            wire:click="removeLine( {{ $line['id'] }} )"
                                            wire:loading.attr="disabled"
                                            wire:target="removeLine( {{ $line['id'] }} )"
                                            data-cart-line-remove
                                        />
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
                        <x-artisanpack-button :label="__( 'View cart' )" :link="$cartUrl" class="w-full" data-cart-drawer-view />
                    @endif

                    @if ( $hasUnsellable )
                        <p class="text-sm text-warning">{{ __( 'Remove the items that can\'t be bought to check out.' ) }}</p>
                    @elseif ( null !== $checkoutUrl )
                        <x-artisanpack-button :label="__( 'Checkout' )" :link="$checkoutUrl" color="primary" class="w-full" data-cart-drawer-checkout />
                    @endif
                </div>
            @endif
        </div>
    </x-artisanpack-drawer>
</div>
