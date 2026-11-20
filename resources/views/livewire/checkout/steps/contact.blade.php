{{--
    Checkout step: contact.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<form wire:submit="saveContact" novalidate class="flex flex-col gap-4" data-checkout-contact-form>
    @if ( $isGuest && null !== $loginUrl && ! $needsAccount )
        <p class="text-sm" data-checkout-sign-in-link>
            {{ __( 'Have an account?' ) }}
            <a href="{{ $loginUrl }}" class="link link-primary">{{ __( 'Sign in for faster checkout' ) }}</a>
        </p>
    @endif

    @if ( $needsAccount )
        <x-artisanpack-alert color="info" icon="o-information-circle" :title="__( 'You\'ll need an account to pay' )" :description="__( 'Fill in your details now, then sign in or create an account before paying.' )" data-checkout-account-needed>
            <x-slot:actions>
                @if ( null !== $loginUrl )
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" :href="$loginUrl">{{ __( 'Sign in' ) }}</x-artisanpack-button>
                @endif

                @if ( null !== $registerUrl )
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" :href="$registerUrl">{{ __( 'Create an account' ) }}</x-artisanpack-button>
                @endif
            </x-slot:actions>
        </x-artisanpack-alert>
    @endif

    <div data-field="email">
        <x-artisanpack-ec-field
            id="ec-checkout-email"
            type="email"
            :label="__( 'Email' )"
            :hint="__( 'We\'ll send your order confirmation here.' )"
            autocomplete="email"
            inputmode="email"
            required
            wire:model="email"
        />
    </div>

    <x-artisanpack-checkbox :label="__( 'Email me news and offers' )" wire:model="marketingConsent" data-checkout-marketing />

    <div>
        <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" wire:target="saveContact" data-checkout-contact-submit>{{ __( 'Continue' ) }}</x-artisanpack-button>
    </div>
</form>
