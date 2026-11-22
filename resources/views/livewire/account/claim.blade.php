{{--
    Claiming guest orders (#151). See Livewire\Account\Claim.

    The lead line, then a success Alert after a claim ("Order {number} is
    now in your account", or how many joined, with an outline "View
    orders"), or an error Alert when it failed. The form sits in a Card at
    most 560px wide: the order number (monospace) and shipping postcode
    side by side from 640px, then "Claim orders".

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-claim" data-account-claim>
    <p class="ec-claim__lead">{{ __( 'Ordered before you had an account? Enter the order number and the postcode it was shipped to, and we\'ll add your guest orders placed with this email address to your account.' ) }}</p>

    <div role="alert" aria-live="assertive" class="ec-claim__region">
        @if ( null !== $failure )
            <x-artisanpack-alert color="error" icon="circle-alert" :title="$failure" data-account-claim-failure />
        @endif
    </div>

    <div aria-live="polite" class="ec-claim__region">
        @if ( null !== $claimed )
            <x-artisanpack-alert
                color="success"
                icon="circle-check"
                :title="1 === $claimed['count'] ? __( 'Order :number is now in your account', [ 'number' => $claimed['number'] ] ) : trans_choice( ':count order is now in your account|:count orders are now in your account', $claimed['count'], [ 'count' => $claimed['count'] ] )"
                :description="1 === $claimed['count'] ? __( 'You\'ll find it in your order history.' ) : __( 'You\'ll find them in your order history.' )"
                data-account-claim-success
            >
                @if ( null !== $ordersUrl )
                    <x-slot:actions>
                        <x-artisanpack-button variant="outline" color="neutral" size="sm" :href="$ordersUrl" data-account-claim-orders>{{ __( 'View orders' ) }}</x-artisanpack-button>
                    </x-slot:actions>
                @endif
            </x-artisanpack-alert>
        @endif
    </div>

    <x-artisanpack-card class="ec-claim__card">
        <form wire:submit="claim" class="ec-claim__form" novalidate data-account-claim-form>
            @include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => 'ec-account-claim' ] )

            <div class="ec-claim__fields">
                <x-artisanpack-ec-field id="orderNumber" class="ec-claim__number" :label="__( 'Order number' )" autocomplete="off" spellcheck="false" required wire:model="orderNumber" />
                <x-artisanpack-ec-field id="postalCode" :label="__( 'Shipping postcode' )" autocomplete="postal-code" required wire:model="postalCode" />
            </div>

            <div>
                <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" wire:target="claim" data-account-claim-submit>{{ __( 'Claim orders' ) }}</x-artisanpack-button>
            </div>
        </form>
    </x-artisanpack-card>
</div>
