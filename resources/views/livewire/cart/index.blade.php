{{--
    The cart page. See Cart\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
@endphp
<div class="flex flex-col gap-10" data-ecommerce-cart>
    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-cart-announcement>{{ $announcement }}</p>

    @if ( null !== $removedLine )
        <x-artisanpack-alert icon="o-information-circle" :title="__( ':name was removed from your cart.', [ 'name' => $removedLine['name'] ] )" data-cart-undo>
            <x-slot:actions>
                <x-artisanpack-button :label="__( 'Undo' )" class="btn-sm" wire:click="undoRemove" wire:loading.attr="disabled" wire:target="undoRemove" data-cart-undo-button />
            </x-slot:actions>
        </x-artisanpack-alert>
    @endif

    @if ( [] === $lines )
        <x-artisanpack-ec-empty-state
            icon="o-shopping-cart"
            :title="__( 'Your cart is empty' )"
            :description="__( 'Find something you like and add it to your cart.' )"
            data-cart-empty
        >
            @if ( null !== $catalogUrl )
                <x-artisanpack-button :label="__( 'Continue shopping' )" :link="$catalogUrl" color="primary" />
            @endif
        </x-artisanpack-ec-empty-state>
    @else
        <div class="grid gap-8 lg:grid-cols-3">
            <section class="lg:col-span-2" aria-labelledby="ec-cart-lines-heading">
                <h2 id="ec-cart-lines-heading" class="sr-only">{{ __( 'Items in your cart' ) }}</h2>

                <ul class="flex flex-col divide-y divide-base-content/10" data-cart-lines>
                    @foreach ( $lines as $line )
                        @php( $ecommerceField = 'quantities.' . $line['id'] )
                        <li class="flex gap-4 py-4 first:pt-0" wire:key="cart-line-{{ $line['id'] }}" data-cart-line="{{ $line['id'] }}">
                            <div class="h-24 w-24 shrink-0 overflow-hidden rounded-box bg-base-200">
                                @if ( null !== $line['image'] )
                                    <img src="{{ $line['image']['url'] }}" @if ( null !== ( $line['image']['srcset'] ?? null ) ) srcset="{{ $line['image']['srcset'] }}" sizes="96px" @endif width="96" height="96" alt="" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                @else
                                    <div class="flex h-full w-full items-center justify-center" aria-hidden="true">
                                        <x-artisanpack-icon name="o-photo" class="h-8 w-8 opacity-30" />
                                    </div>
                                @endif
                            </div>

                            <div class="flex grow flex-col gap-2">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <h3 class="font-semibold">
                                            @if ( null !== $line['url'] )
                                                <a href="{{ $line['url'] }}" class="link-hover">{{ $line['name'] }}</a>
                                            @else
                                                {{ $line['name'] }}
                                            @endif
                                        </h3>

                                        @if ( [] !== $line['options'] )
                                            <ul class="text-sm text-base-content/70" aria-label="{{ __( 'Options' ) }}">
                                                @foreach ( $line['options'] as $option )
                                                    <li>{{ $option }}</li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        <p class="text-sm">
                                            @if ( $line['free'] )
                                                <x-artisanpack-badge :value="__( 'Free gift' )" class="badge-success badge-sm" />
                                            @else
                                                <span class="sr-only">{{ __( 'Price each:' ) }}</span>
                                                <x-artisanpack-ec-money :amount="$line['unit']" :currency="$line['currency']" />
                                                <span aria-hidden="true">{{ __( 'each' ) }}</span>
                                            @endif
                                        </p>
                                    </div>

                                    <p class="font-semibold" data-cart-line-total>
                                        <span class="sr-only">{{ __( 'Line total:' ) }}</span>
                                        <x-artisanpack-ec-money :amount="$line['total']" :currency="$line['currency']" />
                                    </p>
                                </div>

                                @if ( null !== $line['unsellable'] )
                                    <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft py-2" :title="$line['unsellable']" data-cart-line-unsellable>
                                        <x-slot:actions>
                                            <x-artisanpack-button
                                                :label="__( 'Remove' )"
                                                class="btn-sm"
                                                :aria-label="__( 'Remove :name', [ 'name' => $line['name'] ] )"
                                                wire:click="removeLine( {{ $line['id'] }} )"
                                                wire:loading.attr="disabled"
                                                wire:target="removeLine( {{ $line['id'] }} )"
                                            />
                                        </x-slot:actions>
                                    </x-artisanpack-alert>
                                @elseif ( ! $line['free'] )
                                    <div class="flex flex-wrap items-end gap-3">
                                        <x-artisanpack-ec-quantity
                                            :id="'ec-cart-quantity-' . $line['id']"
                                            :item-name="$line['name']"
                                            :min="1"
                                            :max="\ArtisanPackUI\Ecommerce\Services\StorefrontCartService::MAX_LINE_QUANTITY"
                                            wire:model.live.debounce.500ms="{{ $ecommerceField }}"
                                            :error="$errors->first( $ecommerceField )"
                                        />

                                        <x-artisanpack-button
                                            :label="__( 'Remove' )"
                                            icon="o-trash"
                                            class="btn-ghost btn-sm"
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
            </section>

            <aside class="flex flex-col gap-6" aria-labelledby="ec-cart-summary-heading" data-cart-summary>
                <h2 id="ec-cart-summary-heading" class="text-xl font-bold">{{ __( 'Order summary' ) }}</h2>

                @if ( $showCoupon )
                    <div class="flex flex-col gap-2" data-cart-coupon>
                        @if ( null !== $coupon )
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span>{{ __( 'Coupon applied:' ) }}</span>
                                <x-artisanpack-badge :value="$coupon" class="badge-neutral" data-cart-coupon-code />
                                <x-artisanpack-button
                                    :label="__( 'Remove' )"
                                    class="btn-ghost btn-xs"
                                    :aria-label="__( 'Remove coupon :code', [ 'code' => $coupon ] )"
                                    wire:click="removeCoupon( {{ \Illuminate\Support\Js::from( $coupon ) }} )"
                                    data-cart-coupon-remove
                                />
                            </div>
                        @endif

                        <form wire:submit="applyCoupon" class="flex items-end gap-2" novalidate>
                            <div class="grow">
                                <x-artisanpack-input
                                    id="ec-cart-coupon"
                                    :label="__( 'Coupon code' )"
                                    wire:model="couponCode"
                                    autocomplete="off"
                                    autocapitalize="characters"
                                    maxlength="64"
                                    error-field="couponCode"
                                />
                            </div>
                            <x-artisanpack-button type="submit" :label="__( 'Apply' )" spinner="applyCoupon" wire:loading.attr="disabled" wire:target="applyCoupon" data-cart-coupon-apply />
                        </form>
                    </div>
                @endif

                @if ( $requiresShipping )
                    <form wire:submit="estimateShipping" class="flex flex-col gap-3 border-t border-base-content/10 pt-4" aria-labelledby="ec-cart-estimate-heading" novalidate data-cart-estimate>
                        <h3 id="ec-cart-estimate-heading" class="font-semibold">{{ __( 'Estimate shipping' ) }}</h3>

                        <x-artisanpack-select
                            id="ec-cart-estimate-country"
                            :label="__( 'Country' )"
                            :options="$countries"
                            :placeholder="__( 'Select a country' )"
                            placeholder-value=""
                            autocomplete="shipping country"
                            wire:model.live="estimateCountry"
                        />

                        @if ( [] !== $regions )
                            <x-artisanpack-select
                                id="ec-cart-estimate-region"
                                :label="__( 'Region' )"
                                :options="$regions"
                                :placeholder="__( 'Select one' )"
                                placeholder-value=""
                                autocomplete="shipping address-level1"
                                wire:model="estimateRegion"
                            />
                        @endif

                        <x-artisanpack-input
                            id="ec-cart-estimate-postcode"
                            :label="$postcodeLabel"
                            wire:model="estimatePostcode"
                            autocomplete="shipping postal-code"
                            maxlength="32"
                        />

                        <x-artisanpack-button type="submit" :label="__( 'Get shipping rates' )" spinner="estimateShipping" wire:loading.attr="disabled" wire:target="estimateShipping" data-cart-estimate-submit />

                        @if ( null !== $rates )
                            @if ( [] === $rates )
                                <p class="text-sm" data-cart-estimate-none>{{ __( 'No shipping options for this address.' ) }}</p>
                            @else
                                <x-artisanpack-radio
                                    id="ec-cart-estimate-rate"
                                    :label="__( 'Shipping option' )"
                                    :options="collect( $rates )->map( fn ( array $rate ): array => [ 'id' => $rate['id'], 'name' => $rate['label'], 'hint' => MoneyFormatter::format( $rate['amount'], $rate['currency'] ) ] )->all()"
                                    wire:model.live="selectedRate"
                                    data-cart-estimate-rates
                                />
                            @endif
                        @endif

                        @error( 'selectedRate' )
                            <p class="text-sm text-error" data-cart-estimate-error>{{ $message }}</p>
                        @enderror
                    </form>
                @endif

                @if ( null !== $totals )
                    @include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => $totals ] )
                @endif

                <div class="flex flex-col gap-2">
                    @if ( $hasUnsellable )
                        <x-artisanpack-button :label="__( 'Checkout' )" color="primary" class="w-full" disabled aria-describedby="ec-cart-checkout-blocked" data-cart-checkout />
                        <p id="ec-cart-checkout-blocked" class="text-sm text-warning" data-cart-checkout-blocked>{{ __( 'Remove the items that can\'t be bought to check out.' ) }}</p>
                    @elseif ( null !== $checkoutUrl )
                        <x-artisanpack-button :label="__( 'Checkout' )" :link="$checkoutUrl" color="primary" class="w-full" data-cart-checkout />
                    @endif

                    @if ( null !== $catalogUrl )
                        <x-artisanpack-button :label="__( 'Continue shopping' )" :link="$catalogUrl" class="btn-ghost w-full" />
                    @endif
                </div>
            </aside>
        </div>
    @endif

    @foreach ( $sections as $key => $section )
        <div data-cart-section="{{ $key }}" wire:key="cart-section-{{ $key }}">
            @livewire( $section['component'], $section['params'], key( 'cart-section-' . $key ) )
        </div>
    @endforeach
</div>
