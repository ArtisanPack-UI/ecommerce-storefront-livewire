{{--
    Checkout step: contact (#137). For a guest, the sign-in prompt on the
    sunken surface (a person icon, "Have an account? Sign in for faster
    checkout." and an outline "Sign in"), or — when the store needs an
    account to pay — an info Alert with "Sign in" and "Create an account".
    Then the email (a mail icon, and a hint saying what it's used for),
    "Email me news and offers" (unchecked), and "Continue".

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<form wire:submit="saveContact" novalidate class="ec-checkout-form" data-checkout-contact-form>
    @if ( $isGuest && null !== $loginUrl && ! $needsAccount )
        <div class="ec-checkout-callout" data-checkout-sign-in-link>
            <x-artisanpack-icon name="user" class="ec-checkout-callout__icon" aria-hidden="true" />
            <p class="ec-checkout-callout__text">{{ __( 'Have an account? Sign in for faster checkout.' ) }}</p>
            <x-artisanpack-button :href="$loginUrl" variant="outline" color="neutral" size="sm" class="ec-checkout-callout__action">{{ __( 'Sign in' ) }}</x-artisanpack-button>
        </div>
    @endif

    @if ( $needsAccount )
        <x-artisanpack-alert color="info" icon="info" :title="__( 'You\'ll need an account to pay' )" :description="__( 'Fill in your details now, then sign in or create an account before paying.' )" data-checkout-account-needed>
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
            :hint="__( 'We\'ll send your receipt and shipping updates here.' )"
            leading-icon="mail"
            autocomplete="email"
            inputmode="email"
            required
            wire:model="email"
        />
    </div>

    <x-artisanpack-checkbox :label="__( 'Email me news and offers' )" wire:model="marketingConsent" data-checkout-marketing />

    <div class="ec-checkout-form__actions">
        <x-artisanpack-button type="submit" color="primary" class="ec-checkout-form__submit" wire:loading.attr="disabled" wire:target="saveContact" data-checkout-contact-submit>{{ __( 'Continue' ) }}</x-artisanpack-button>
    </div>
</form>
