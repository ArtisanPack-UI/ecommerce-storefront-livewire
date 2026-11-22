{{--
    The profile and notification preferences (#150). See
    Livewire\Account\Profile.

    Three Cards. Your details: first and last name side by side from 640px,
    the optional phone at half width, the news-and-offers consent, and
    "Save details" in the ruled footer. Email and password: the sign-in
    address and outline links to the host's "Change your email" and "Change
    your password". Notifications: "You can change your mind at any time."
    then a Toggle per category for each channel (the always-sent one locked
    on, news and offers off until the shopper agrees above), and an outline
    "Save notification preferences" in the footer.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-profile" data-account-profile>
    <form wire:submit="saveProfile" novalidate data-account-profile-form>
        <x-artisanpack-card class="ec-order-card ec-profile__card" role="region" aria-labelledby="ec-account-profile-details">
            <h2 id="ec-account-profile-details" class="ec-order-card__title">{{ __( 'Your details' ) }}</h2>

            @include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => 'ec-account-profile', 'summaryFieldIds' => $fieldIds ] )

            <div class="ec-profile__fields">
                <x-artisanpack-ec-field :id="$fieldIds['profile.first_name']" :label="__( 'First name' )" autocomplete="given-name" wire:model="profile.first_name" />
                <x-artisanpack-ec-field :id="$fieldIds['profile.last_name']" :label="__( 'Last name' )" autocomplete="family-name" wire:model="profile.last_name" />

                <div class="ec-profile__phone">
                    <x-artisanpack-ec-field :id="$fieldIds['profile.phone']" type="tel" optional :label="__( 'Phone' )" autocomplete="tel" wire:model="profile.phone" />
                </div>
            </div>

            <x-artisanpack-checkbox :id="$fieldIds['profile.accepts_marketing']" :label="__( 'Email me news and offers' )" :description="__( 'You can change your mind at any time.' )" wire:model="profile.accepts_marketing" data-account-profile-marketing />

            <x-slot:footer>
                <div class="ec-profile__footer">
                    <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" wire:target="saveProfile" data-account-profile-save>{{ __( 'Save details' ) }}</x-artisanpack-button>
                </div>
            </x-slot:footer>
        </x-artisanpack-card>
    </form>

    <x-artisanpack-card class="ec-order-card ec-profile__card" role="region" aria-labelledby="ec-account-profile-sign-in" data-account-profile-sign-in>
        <div class="ec-profile__intro">
            <h2 id="ec-account-profile-sign-in" class="ec-order-card__title">{{ __( 'Email and password' ) }}</h2>

            @if ( null !== $email )
                <p class="ec-profile__lead">{{ __( 'You sign in with :email.', [ 'email' => $email ] ) }}</p>
            @endif
        </div>

        @if ( null !== $profileUrl || null !== $passwordUrl )
            <div class="ec-profile__links">
                @if ( null !== $profileUrl )
                    <x-artisanpack-button variant="outline" color="neutral" :href="$profileUrl" leading-icon="mail" data-account-host-link="profile">{{ __( 'Change your email' ) }}</x-artisanpack-button>
                @endif

                @if ( null !== $passwordUrl )
                    <x-artisanpack-button variant="outline" color="neutral" :href="$passwordUrl" leading-icon="lock" data-account-host-link="password">{{ __( 'Change your password' ) }}</x-artisanpack-button>
                @endif
            </div>
        @endif
    </x-artisanpack-card>

    <form wire:submit="savePreferences" data-account-preferences-form>
        <x-artisanpack-card class="ec-order-card ec-profile__card" role="region" aria-labelledby="ec-account-profile-notifications">
            <div class="ec-profile__intro">
                <h2 id="ec-account-profile-notifications" class="ec-order-card__title">{{ __( 'Notifications' ) }}</h2>
                <p class="ec-profile__lead">{{ __( 'You can change your mind at any time.' ) }}</p>
            </div>

            @foreach ( $channels as $channel => $channelLabel )
                <fieldset class="ec-profile__channel" wire:key="account-preferences-{{ $channel }}" data-account-preferences-channel="{{ $channel }}">
                    {{-- With one channel the card's heading says it all; the legend still names the group. --}}
                    <legend @class( [ 'ec-profile__legend', 'apui-sr-only' => 1 === count( $channels ) ] )>{{ $channelLabel }}</legend>

                    @foreach ( $categories as $category => $definition )
                        <div wire:key="account-preference-{{ $channel }}-{{ $category }}" data-account-preference="{{ $channel }}.{{ $category }}">
                            @if ( 'marketing' === $category && ! $consented )
                                <x-artisanpack-toggle :id="'preferences-' . $channel . '-' . $category" :label="$definition['label']" :description="__( 'Agree to news and offers in your details above first.' )" disabled data-account-preference-needs-consent />
                            @elseif ( $definition['locked'] )
                                <x-artisanpack-toggle :id="'preferences-' . $channel . '-' . $category" :label="$definition['label']" :description="$definition['description']" checked disabled data-account-preference-locked />
                            @else
                                <x-artisanpack-toggle :id="'preferences-' . $channel . '-' . $category" :label="$definition['label']" :description="$definition['description']" wire:model="preferences.{{ $channel }}.{{ $category }}" />
                            @endif
                        </div>
                    @endforeach
                </fieldset>
            @endforeach

            <x-slot:footer>
                <div class="ec-profile__footer">
                    <x-artisanpack-button type="submit" variant="outline" color="neutral" wire:loading.attr="disabled" wire:target="savePreferences" data-account-preferences-save>{{ __( 'Save notification preferences' ) }}</x-artisanpack-button>
                </div>
            </x-slot:footer>
        </x-artisanpack-card>
    </form>
</div>
