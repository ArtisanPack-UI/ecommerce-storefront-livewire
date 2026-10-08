{{--
    The checkout. See Livewire\Checkout\Index.

    Steps are listed in order: completed ones show a summary and "Edit",
    the current one its form, later ones only their name. Each step's
    content is a partial under `livewire.checkout.steps`; a satellite's
    step is its own Livewire component.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6" data-ecommerce-checkout>
    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-checkout-announcement>{{ $announcement }}</p>

    @if ( null !== $unavailable )
        <x-artisanpack-ec-empty-state icon="o-shopping-cart" :title="$unavailable" data-checkout-unavailable>
            @if ( null !== $cartUrl )
                <x-artisanpack-button :label="__( 'Go to your cart' )" :link="$cartUrl" color="primary" />
            @endif
        </x-artisanpack-ec-empty-state>
    @elseif ( $signInRequired )
        <x-artisanpack-ec-empty-state
            icon="o-lock-closed"
            :title="__( 'Sign in to check out' )"
            :description="__( 'You need an account to place an order with this store.' )"
            data-checkout-sign-in-required
        >
            <div class="flex flex-wrap justify-center gap-2">
                @if ( null !== $loginUrl )
                    <x-artisanpack-button :label="__( 'Sign in' )" :link="$loginUrl" color="primary" data-checkout-login />
                @endif

                @if ( null !== $registerUrl )
                    <x-artisanpack-button :label="__( 'Create an account' )" :link="$registerUrl" data-checkout-register />
                @endif
            </div>
        </x-artisanpack-ec-empty-state>
    @else
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
            <div class="flex flex-col gap-6">
                @if ( [] !== $adjustments )
                    <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft" data-checkout-adjustments>
                        <span class="block font-bold">{{ __( 'We\'ve updated your cart' ) }}</span>
                        <ul class="list-disc ps-5">
                            @foreach ( $adjustments as $adjustment )
                                <li>{{ $adjustment }}</li>
                            @endforeach
                        </ul>
                    </x-artisanpack-alert>
                @endif

                @include( 'ecommerce-storefront::livewire.checkout.partials.error-summary' )

                <ol class="flex flex-col gap-4" aria-label="{{ __( 'Checkout steps' ) }}" data-checkout-steps>
                    @foreach ( $steps as $checkoutStep )
                        <li
                            wire:key="checkout-step-{{ $checkoutStep['key'] }}-{{ $checkoutStep['current'] ? 'current' : 'idle' }}"
                            @class( [
                                'rounded-box border p-5',
                                'border-primary' => $checkoutStep['current'],
                                'border-base-content/10' => ! $checkoutStep['current'],
                            ] )
                            @if ( $checkoutStep['current'] ) aria-current="step" @endif
                            data-checkout-step="{{ $checkoutStep['key'] }}"
                            data-checkout-step-state="{{ $checkoutStep['current'] ? 'current' : ( $checkoutStep['complete'] && $checkoutStep['reachable'] ? 'complete' : 'upcoming' ) }}"
                        >
                            <div class="flex items-center justify-between gap-4">
                                <h2
                                    id="ec-checkout-step-{{ $checkoutStep['key'] }}"
                                    tabindex="-1"
                                    @class( [ 'flex items-center gap-3 text-lg font-semibold focus:outline-none', 'text-base-content/60' => ! $checkoutStep['current'] && ! $checkoutStep['reachable'] ] )
                                    @if ( $checkoutStep['current'] && $focusStep ) x-init="$nextTick( () => $el.focus() )" @endif
                                >
                                    <span class="badge badge-sm {{ $checkoutStep['current'] ? 'badge-primary' : 'badge-ghost' }}" aria-hidden="true">{{ $checkoutStep['number'] }}</span>
                                    <span class="sr-only">{{ __( 'Step :number:', [ 'number' => $checkoutStep['number'] ] ) }}</span>
                                    {{ $checkoutStep['label'] }}
                                    @if ( ! $checkoutStep['current'] && $checkoutStep['complete'] && $checkoutStep['reachable'] )
                                        <x-artisanpack-icon name="o-check-circle" class="h-5 w-5 text-success" aria-hidden="true" />
                                        <span class="sr-only">{{ __( '(completed)' ) }}</span>
                                    @endif
                                </h2>

                                @if ( ! $checkoutStep['current'] && $checkoutStep['reachable'] && $checkoutStep['complete'] )
                                    <x-artisanpack-button
                                        :label="__( 'Edit' )"
                                        class="btn-ghost btn-sm"
                                        wire:click="goTo( '{{ $checkoutStep['key'] }}' )"
                                        aria-label="{{ __( 'Edit :step', [ 'step' => $checkoutStep['label'] ] ) }}"
                                        data-checkout-edit="{{ $checkoutStep['key'] }}"
                                    />
                                @endif
                            </div>

                            @if ( $checkoutStep['current'] )
                                <div class="mt-4">
                                    @if ( null !== $checkoutStep['component'] )
                                        @livewire( $checkoutStep['component'], [ 'step' => $checkoutStep['key'] ], key( 'checkout-step-component-' . $checkoutStep['key'] ) )
                                    @else
                                        @include( 'ecommerce-storefront::livewire.checkout.steps.' . $checkoutStep['key'] )
                                    @endif
                                </div>
                            @elseif ( $checkoutStep['complete'] && $checkoutStep['reachable'] )
                                <div class="mt-3 text-sm text-base-content/80" data-checkout-step-summary="{{ $checkoutStep['key'] }}">
                                    @include( 'ecommerce-storefront::livewire.checkout.partials.step-summary', [ 'summaryStep' => $checkoutStep ] )
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>

            @include( 'ecommerce-storefront::livewire.checkout.partials.order-summary' )
        </div>
    @endif
</div>
