{{--
    The checkout (#136). See Livewire\Checkout\Index.

    The steps beside a 380px sticky order summary (from 1024px; below that
    the summary is a "Show order summary" bar above the title). With
    `showTitle` (the checkout page) the "Checkout" title comes first.

    Multi-step: the design-system Steps from 768px (below that, "Step 2 of
    5 · Address"), then every step as a card: finished steps collapse to
    what they chose with "Edit", the open step shows its form, and the
    steps still to come sit on the page color with muted titles. Single
    page: every step, with the ones after an unfinished step disabled and
    saying why. Each step's content is a partial under
    `livewire.checkout.steps`; a satellite's step is its own Livewire
    component.

    After an order is placed but its payment isn't finished, the payment
    for that order is shown instead (partials.placement).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-checkout" data-ecommerce-checkout data-checkout-layout="{{ $layout }}">
    <p class="apui-sr-only" role="status" aria-live="polite" aria-atomic="true" data-checkout-announcement>{{ $announcement }}</p>

    @if ( null !== $placedOrder )
        <x-artisanpack-ec-empty-state
            icon="circle-check"
            :title="__( 'Thank you for your order' )"
            :description="__( 'Your order :number is placed. We\'ve emailed you a confirmation.', [ 'number' => $placedOrder ] )"
            card
            data-checkout-placed
        />
    @elseif ( null !== $unavailable )
        @if ( $showTitle )
            <h1 class="ec-checkout__title">{{ __( 'Checkout' ) }}</h1>
        @endif

        <x-artisanpack-ec-empty-state icon="shopping-bag" :title="$unavailable" card data-checkout-unavailable>
            @if ( null !== $cartUrl )
                <x-artisanpack-button :href="$cartUrl" color="primary">{{ __( 'Go to your cart' ) }}</x-artisanpack-button>
            @endif
        </x-artisanpack-ec-empty-state>
    @elseif ( $signInRequired )
        @if ( $showTitle )
            <h1 class="ec-checkout__title">{{ __( 'Checkout' ) }}</h1>
        @endif

        <x-artisanpack-ec-empty-state
            icon="lock"
            :title="__( 'Sign in to check out' )"
            :description="__( 'You need an account to place an order with this store.' )"
            card
            data-checkout-sign-in-required
        >
            @if ( null !== $loginUrl )
                <x-artisanpack-button :href="$loginUrl" color="primary" data-checkout-login>{{ __( 'Sign in' ) }}</x-artisanpack-button>
            @endif

            @if ( null !== $registerUrl )
                <x-artisanpack-button variant="outline" color="neutral" :href="$registerUrl" data-checkout-register>{{ __( 'Create an account' ) }}</x-artisanpack-button>
            @endif
        </x-artisanpack-ec-empty-state>
    @elseif ( null !== $placement )
        @if ( $showTitle )
            <h1 class="ec-checkout__title">{{ __( 'Checkout' ) }}</h1>
        @endif

        @include( 'ecommerce-storefront::livewire.checkout.partials.placement' )
    @else
        @php
            $ecommerceSinglePage = \ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutLayout::SINGLE_PAGE === $layout;
            $ecommerceBlocking   = collect( $steps )->where( 'reachable', true )->last()['label'] ?? '';
            $ecommerceCurrent    = collect( $steps )->firstWhere( 'current', true );
        @endphp
        <div class="ec-checkout__layout">
            <div class="ec-checkout__main">
                @if ( $showTitle )
                    <h1 class="ec-checkout__title">{{ __( 'Checkout' ) }}</h1>
                @endif

                @if ( ! $ecommerceSinglePage && [] !== $steps )
                    @if ( null !== $ecommerceCurrent )
                        {{-- Below 768px; the live announcement says the same to assistive tech. --}}
                        <p class="ec-checkout__step-line" aria-hidden="true" data-checkout-step-line>
                            {{ __( 'Step :number of :total', [ 'number' => $ecommerceCurrent['number'], 'total' => count( $steps ) ] ) }} &middot;
                            <span>{{ $ecommerceCurrent['label'] }}</span>
                        </p>
                    @endif

                    <div aria-hidden="true" class="ec-checkout__progress" data-checkout-progress>
                        <x-artisanpack-steps :value="$progress">
                            @foreach ( $steps as $checkoutStep )
                                <x-artisanpack-step :step="$checkoutStep['number']" :title="$checkoutStep['label']" />
                            @endforeach
                        </x-artisanpack-steps>
                    </div>
                @endif

                @if ( [] !== $adjustments )
                    <x-artisanpack-alert color="warning" :title="__( 'We\'ve updated your cart' )" data-checkout-adjustments>
                        <ul class="ec-checkout__adjustments">
                            @foreach ( $adjustments as $adjustment )
                                <li>{{ $adjustment }}</li>
                            @endforeach
                        </ul>
                    </x-artisanpack-alert>
                @endif

                @include( 'ecommerce-storefront::livewire.checkout.partials.error-summary' )

                <ol role="list" class="ec-checkout__steps" aria-label="{{ __( 'Checkout steps' ) }}" data-checkout-steps>
                    @foreach ( $steps as $checkoutStep )
                        @php
                            $ecommerceStepDone  = ! $checkoutStep['current'] && $checkoutStep['complete'] && $checkoutStep['reachable'];
                            $ecommerceStepState = $checkoutStep['current'] ? 'current' : ( $ecommerceStepDone ? 'complete' : 'upcoming' );
                        @endphp

                        <li
                            wire:key="checkout-step-{{ $checkoutStep['key'] }}-{{ $ecommerceStepState }}"
                            class="ec-checkout-step"
                            data-state="{{ $ecommerceStepState }}"
                            @if ( $checkoutStep['current'] ) aria-current="step" @endif
                            @if ( ! $checkoutStep['reachable'] ) aria-disabled="true" @endif
                            data-checkout-step="{{ $checkoutStep['key'] }}"
                            data-checkout-step-state="{{ $ecommerceStepState }}"
                        >
                            <div class="ec-checkout-step__header">
                                <h2
                                    id="ec-checkout-step-{{ $checkoutStep['key'] }}"
                                    tabindex="-1"
                                    class="ec-checkout-step__title"
                                    @if ( $checkoutStep['current'] && $focusStep ) x-init="$nextTick( () => $el.focus() )" @endif
                                >
                                    <span class="ec-checkout-step__number" aria-hidden="true">
                                        @if ( $ecommerceStepDone )
                                            <x-artisanpack-icon name="check" />
                                        @else
                                            {{ $checkoutStep['number'] }}
                                        @endif
                                    </span>
                                    <span class="apui-sr-only">{{ __( 'Step :number:', [ 'number' => $checkoutStep['number'] ] ) }}</span>
                                    <span>{{ $checkoutStep['label'] }}</span>
                                    @if ( $ecommerceStepDone )
                                        <span class="apui-sr-only">{{ __( '(completed)' ) }}</span>
                                    @endif
                                </h2>

                                @if ( $ecommerceStepDone )
                                    <x-artisanpack-button
                                        variant="ghost"
                                        color="neutral"
                                        size="sm"
                                        leading-icon="pencil"
                                        wire:click="goTo( '{{ $checkoutStep['key'] }}' )"
                                        aria-label="{{ __( 'Edit :step', [ 'step' => $checkoutStep['label'] ] ) }}"
                                        data-checkout-edit="{{ $checkoutStep['key'] }}">{{ __( 'Edit' ) }}</x-artisanpack-button>
                                @endif
                            </div>

                            @if ( $checkoutStep['current'] )
                                <div class="ec-checkout-step__body">
                                    @if ( null !== $checkoutStep['component'] )
                                        @livewire( $checkoutStep['component'], [ 'step' => $checkoutStep['key'] ], key( 'checkout-step-component-' . $checkoutStep['key'] ) )
                                    @else
                                        @include( 'ecommerce-storefront::livewire.checkout.steps.' . $checkoutStep['key'] )
                                    @endif
                                </div>
                            @elseif ( $ecommerceStepDone )
                                <div class="ec-checkout-step__summary" data-checkout-step-summary="{{ $checkoutStep['key'] }}">
                                    @include( 'ecommerce-storefront::livewire.checkout.partials.step-summary', [ 'summaryStep' => $checkoutStep ] )
                                </div>
                            @elseif ( $ecommerceSinglePage && ! $checkoutStep['reachable'] )
                                <p class="ec-checkout-step__locked" data-checkout-step-locked="{{ $checkoutStep['key'] }}">
                                    {{ __( 'Complete “:step” first.', [ 'step' => $ecommerceBlocking ] ) }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>

            @include( 'ecommerce-storefront::livewire.checkout.partials.order-summary' )
        </div>
    @endif
</div>
