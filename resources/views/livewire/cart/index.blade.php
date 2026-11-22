{{--
    The cart page (#135). See Cart\Index.

    With `showTitle` (the cart page), "Your cart" and the item count come
    first; after a removal, a bar says what went with "Undo". Then the lines
    in a design-system Card (the cart drawer's line: thumbnail, name, options,
    the price each, any notice, the quantity stepper and an icon-only remove,
    the line total at the end) and "Continue shopping", beside a 380px sticky
    order summary Card from 1024px (below the lines on smaller screens): the
    coupon input group and the applied coupon as a chip, "Estimate shipping"
    (a disclosure holding the country, region and postcode, and the rates
    as radio cards), the totals and Checkout, the page's one solid primary
    button. An empty cart is an empty-state Card with "Continue shopping".
    Filterable sections (cross-sells) follow.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
@endphp
<div class="ec-cart" data-ecommerce-cart>
    <p class="apui-sr-only" role="status" aria-live="polite" aria-atomic="true" data-cart-announcement>{{ $announcement }}</p>

    @include( 'ecommerce-storefront::livewire.cart.partials.focus-after-change', [ 'root' => '[data-ecommerce-cart]', 'fallback' => '[data-cart-line-remove], [data-cart-empty] a, #ecommerce-storefront-content' ] )

    @if ( $showTitle )
        <div class="ec-cart__heading">
            <h1 class="ec-cart__title">{{ __( 'Your cart' ) }}</h1>

            @if ( [] !== $lines )
                <p class="ec-cart__count" data-cart-item-count>{{ trans_choice( ':count item|:count items', $count, [ 'count' => $count ] ) }}</p>
            @endif
        </div>
    @endif

    @if ( null !== $removedLine )
        <div class="ec-cart__undo" data-cart-undo>
            <x-artisanpack-icon name="trash" aria-hidden="true" />
            <p>{{ __( ':name was removed from your cart.', [ 'name' => $removedLine['name'] ] ) }}</p>
            <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="undoRemove" wire:loading.attr="disabled" wire:target="undoRemove" data-cart-undo-button>{{ __( 'Undo' ) }}</x-artisanpack-button>
        </div>
    @endif

    @if ( [] === $lines )
        <x-artisanpack-ec-empty-state
            icon="shopping-bag"
            :title="__( 'Your cart is empty' )"
            :description="__( 'Find something you like and add it to your cart.' )"
            card
            live
            data-cart-empty
        >
            @if ( null !== $catalogUrl )
                <x-artisanpack-button :href="$catalogUrl" color="primary">{{ __( 'Continue shopping' ) }}</x-artisanpack-button>
            @endif
        </x-artisanpack-ec-empty-state>
    @else
        <div class="ec-cart__layout">
            <section class="ec-cart__main" aria-labelledby="ec-cart-lines-heading">
                <h2 id="ec-cart-lines-heading" class="apui-sr-only">{{ __( 'Items in your cart' ) }}</h2>

                <ul role="list" class="apui-card ec-cart__lines" data-surface="default" data-cart-lines>
                    @foreach ( $lines as $line )
                        @php
                            $ecommerceField = 'quantities.' . $line['id'];
                        @endphp
                        <li class="ec-cart-line ec-cart__line" wire:key="cart-line-{{ $line['id'] }}" data-cart-line="{{ $line['id'] }}">
                            <span class="ec-cart-line__thumb" aria-hidden="true">
                                @if ( null !== $line['image'] )
                                    <img src="{{ $line['image']['url'] }}" @if ( null !== ( $line['image']['srcset'] ?? null ) ) srcset="{{ $line['image']['srcset'] }}" sizes="96px" @endif width="96" height="96" alt="" loading="lazy" decoding="async">
                                @else
                                    <x-artisanpack-icon name="image" />
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

                                    <p class="ec-cart-line__total" data-cart-line-total>
                                        <span class="apui-sr-only">{{ __( 'Line total:' ) }}</span>
                                        @if ( $line['free'] )
                                            {{ __( 'Free' ) }}
                                        @else
                                            <x-artisanpack-ec-money :amount="$line['total']" :currency="$line['currency']" />
                                        @endif
                                    </p>
                                </div>

                                @if ( [] !== $line['options'] )
                                    <p class="ec-cart-line__options">
                                        <span class="apui-sr-only">{{ __( 'Options:' ) }}</span>
                                        {{ implode( ' · ', $line['options'] ) }}
                                    </p>
                                @endif

                                <p class="ec-cart-line__options">
                                    <span class="apui-sr-only">{{ __( 'Price each:' ) }}</span>
                                    <x-artisanpack-ec-money :amount="$line['unit']" :currency="$line['currency']" />
                                    <span aria-hidden="true">{{ __( 'each' ) }}</span>
                                </p>

                                @if ( $line['free'] )
                                    <x-artisanpack-badge variant="soft" color="success" size="sm" leading-icon="gift" data-cart-line-free>{{ __( 'Free gift' ) }}</x-artisanpack-badge>
                                @endif

                                @if ( null !== $line['unsellable'] )
                                    <p class="ec-cart-line__problem" data-cart-line-unsellable @if ( $line['short'] ) data-cart-line-short @endif>{{ $line['unsellable'] }}</p>
                                @endif

                                @if ( null !== $line['unsellable'] && ! $line['short'] )
                                    <div class="ec-cart-line__controls">
                                        <x-artisanpack-button
                                            variant="outline"
                                            color="neutral"
                                            size="sm"
                                            :aria-label="__( 'Remove :name', [ 'name' => $line['name'] ] )"
                                            wire:click="removeLine( {{ $line['id'] }} )"
                                            wire:loading.attr="disabled"
                                            wire:target="removeLine( {{ $line['id'] }} )"
                                            data-cart-line-remove>{{ __( 'Remove' ) }}</x-artisanpack-button>
                                    </div>
                                @elseif ( ! $line['free'] )
                                    <div class="ec-cart-line__controls">
                                        <x-artisanpack-ec-quantity
                                            :id="'ec-cart-quantity-' . $line['id']"
                                            :item-name="$line['name']"
                                            size="sm"
                                            :min="1"
                                            :max="\ArtisanPackUI\Ecommerce\Services\StorefrontCartService::MAX_LINE_QUANTITY"
                                            wire:model.live.change="{{ $ecommerceField }}"
                                            :error="$errors->first( $ecommerceField )"
                                        />

                                        <x-artisanpack-button
                                            variant="ghost"
                                            color="neutral"
                                            size="sm"
                                            icon-only
                                            leading-icon="trash"
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

                @if ( null !== $catalogUrl )
                    <x-artisanpack-link :href="$catalogUrl" standalone leading-icon="arrow-left" :navigate="false" class="ec-cart__continue" data-cart-continue>{{ __( 'Continue shopping' ) }}</x-artisanpack-link>
                @endif
            </section>

            <section class="apui-card ec-cart-summary" data-surface="default" aria-labelledby="ec-cart-summary-heading" data-cart-summary>
                <h2 id="ec-cart-summary-heading" class="ec-cart-summary__title">{{ __( 'Order summary' ) }}</h2>

                @if ( $showCoupon )
                    <div class="ec-cart-summary__coupon" data-cart-coupon>
                        <form wire:submit="applyCoupon" class="ec-input-group" novalidate>
                            <x-artisanpack-ec-field
                                id="ec-cart-coupon"
                                :label="__( 'Coupon code' )"
                                wire:model="couponCode"
                                autocomplete="off"
                                autocapitalize="characters"
                                maxlength="64"
                                error-field="couponCode"
                            />
                            <x-artisanpack-button variant="outline" color="neutral" type="submit" wire:loading.attr="disabled" wire:target="applyCoupon" data-cart-coupon-apply>{{ __( 'Apply' ) }}</x-artisanpack-button>
                        </form>

                        {{-- After a removal the chip (and its button, which had focus) is gone: hand focus to the coupon field, once per removal, only when focus was lost. --}}
                        @if ( $couponChanges > 0 )
                            <span
                                hidden
                                wire:key="cart-coupon-focus-{{ $couponChanges }}"
                                x-data
                                x-init="setTimeout( () => {
                                    if ( document.activeElement && document.activeElement !== document.body ) {
                                        return;
                                    }

                                    document.getElementById( 'ec-cart-coupon' )?.focus();
                                } )"
                                data-cart-coupon-focus
                            ></span>
                        @endif

                        @if ( null !== $coupon )
                            <p class="ec-chip" data-variant="coupon">
                                <x-artisanpack-icon name="tag" class="ec-chip__icon" aria-hidden="true" />
                                <span class="apui-sr-only">{{ __( 'Coupon applied:' ) }}</span>
                                <span data-cart-coupon-code>{{ $coupon }}</span>
                                <button
                                    type="button"
                                    class="ec-chip__remove"
                                    aria-label="{{ __( 'Remove coupon :code', [ 'code' => $coupon ] ) }}"
                                    wire:click="removeCoupon( {{ \Illuminate\Support\Js::from( $coupon ) }} )"
                                    data-cart-coupon-remove
                                ><x-artisanpack-icon name="x" aria-hidden="true" /></button>
                            </p>
                        @endif
                    </div>
                @endif

                @if ( $requiresShipping )
                    {{-- Native disclosure: Livewire leaves its open state alone (wire:ignore.self) while the fields inside update. --}}
                    <details class="ec-disclosure" wire:ignore.self @if ( null !== $rates ) open @endif data-cart-estimate-disclosure>
                        <summary class="ec-disclosure__summary">
                            <x-artisanpack-icon name="truck" aria-hidden="true" />
                            <span id="ec-cart-estimate-heading">{{ __( 'Estimate shipping' ) }}</span>
                            <x-artisanpack-icon name="chevron-down" class="ec-disclosure__chevron" aria-hidden="true" />
                        </summary>

                        <form wire:submit="estimateShipping" class="ec-disclosure__body ec-cart-estimate" aria-labelledby="ec-cart-estimate-heading" novalidate data-cart-estimate>
                            <div class="ec-cart-estimate__fields">
                                <x-artisanpack-ec-field
                                    control="select"
                                    id="ec-cart-estimate-country"
                                    :label="__( 'Country' )"
                                    :options="$countries"
                                    :placeholder="__( 'Select a country' )"
                                    placeholder-value=""
                                    autocomplete="shipping country"
                                    wire:model.live="estimateCountry"
                                />

                                @if ( [] !== $regions )
                                    <x-artisanpack-ec-field
                                        control="select"
                                        id="ec-cart-estimate-region"
                                        :label="__( 'Region' )"
                                        :options="$regions"
                                        :placeholder="__( 'Select one' )"
                                        placeholder-value=""
                                        autocomplete="shipping address-level1"
                                        wire:model="estimateRegion"
                                    />
                                @endif

                                <x-artisanpack-ec-field
                                    id="ec-cart-estimate-postcode"
                                    :label="$postcodeLabel"
                                    wire:model="estimatePostcode"
                                    autocomplete="shipping postal-code"
                                    maxlength="32"
                                />
                            </div>

                            <x-artisanpack-button variant="outline" color="neutral" type="submit" wire:loading.attr="disabled" wire:target="estimateShipping" data-cart-estimate-submit>{{ __( 'Get shipping rates' ) }}</x-artisanpack-button>

                            @if ( null !== $rates && [] !== $rates )
                                {{-- The group shows a rate error itself, tied to the radios (aria-invalid, aria-describedby). --}}
                                <x-artisanpack-radio-group
                                    id="ec-cart-estimate-rate"
                                    :label="__( 'Shipping option' )"
                                    variant="cards"
                                    :options="collect( $rates )->map( fn ( array $rate ): array => [ 'value' => $rate['id'], 'label' => $rate['label'], 'meta' => MoneyFormatter::format( $rate['amount'], $rate['currency'] ) ] )->all()"
                                    wire:model.live="selectedRate"
                                    error-field="selectedRate"
                                    data-cart-estimate-rates
                                />
                            @else
                                @if ( [] === $rates )
                                    <p class="ec-cart-estimate__none" data-cart-estimate-none>{{ __( 'No shipping options for this address.' ) }}</p>
                                @endif

                                @error( 'selectedRate' )
                                    <p class="ec-cart-estimate__error" role="alert" data-cart-estimate-error>{{ $message }}</p>
                                @enderror
                            @endif
                        </form>
                    </details>
                @endif

                @if ( null !== $totals )
                    @include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => $totals ] )
                @endif

                <div class="ec-cart-summary__actions">
                    @if ( $hasUnsellable )
                        <x-artisanpack-button color="primary" size="lg" leading-icon="lock" class="ec-cart-summary__checkout" disabled aria-describedby="ec-cart-checkout-blocked" data-cart-checkout>{{ __( 'Checkout' ) }}</x-artisanpack-button>
                        <p id="ec-cart-checkout-blocked" class="ec-cart-summary__blocked" data-cart-checkout-blocked>{{ $onlyShort ? __( 'Lower the quantities marked to check out.' ) : __( 'Remove the items that can\'t be bought to check out.' ) }}</p>
                    @elseif ( null !== $checkoutUrl )
                        <x-artisanpack-button :href="$checkoutUrl" color="primary" size="lg" leading-icon="lock" class="ec-cart-summary__checkout" data-cart-checkout>{{ __( 'Checkout' ) }}</x-artisanpack-button>
                    @endif

                    @if ( $requiresShipping )
                        <p class="ec-cart-summary__note">{{ __( 'Taxes are estimated until you enter your address.' ) }}</p>
                    @endif
                </div>
            </section>
        </div>
    @endif

    @foreach ( $sections as $key => $section )
        <div data-cart-section="{{ $key }}" wire:key="cart-section-{{ $key }}">
            @livewire( $section['component'], $section['params'], key( 'cart-section-' . $key ) )
        </div>
    @endforeach
</div>
