{{--
    The profile and notification preferences. See Livewire\Account\Profile.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-8" data-account-profile>
    <section class="flex flex-col gap-4" aria-labelledby="ec-account-profile-details">
        <h2 id="ec-account-profile-details" class="text-xl font-bold">{{ __( 'Your details' ) }}</h2>

        <form wire:submit="saveProfile" class="grid max-w-2xl gap-4 sm:grid-cols-2" novalidate data-account-profile-form>
            <x-artisanpack-input id="profile.first_name" :label="__( 'First name' )" autocomplete="given-name" wire:model="profile.first_name" />
            <x-artisanpack-input id="profile.last_name" :label="__( 'Last name' )" autocomplete="family-name" wire:model="profile.last_name" />

            <div class="sm:col-span-2">
                <x-artisanpack-input id="profile.phone" type="tel" :label="__( 'Phone' )" autocomplete="tel" wire:model="profile.phone" />
            </div>

            <div class="sm:col-span-2">
                <x-artisanpack-checkbox id="profile.accepts_marketing" :label="__( 'Email me news and offers' )" :hint="__( 'You can change your mind at any time.' )" wire:model="profile.accepts_marketing" data-account-profile-marketing />
            </div>

            <div class="sm:col-span-2">
                <x-artisanpack-button type="submit" :label="__( 'Save details' )" color="primary" spinner="saveProfile" wire:loading.attr="disabled" wire:target="saveProfile" data-account-profile-save />
            </div>
        </form>
    </section>

    <section class="flex max-w-2xl flex-col gap-3 rounded-box border border-base-content/10 p-5" aria-labelledby="ec-account-profile-sign-in" data-account-profile-sign-in>
        <h2 id="ec-account-profile-sign-in" class="font-semibold">{{ __( 'Email and password' ) }}</h2>

        @if ( null !== $email )
            <p>{{ __( 'You sign in with :email.', [ 'email' => $email ] ) }}</p>
        @endif

        @if ( null !== $profileUrl || null !== $passwordUrl )
            <div class="flex flex-wrap gap-2">
                @if ( null !== $profileUrl )
                    <x-artisanpack-button :label="__( 'Change your email' )" :link="$profileUrl" icon="o-envelope" class="btn-sm" data-account-host-link="profile" />
                @endif

                @if ( null !== $passwordUrl )
                    <x-artisanpack-button :label="__( 'Change your password' )" :link="$passwordUrl" icon="o-key" class="btn-sm" data-account-host-link="password" />
                @endif
            </div>
        @endif
    </section>

    <section class="flex flex-col gap-4" aria-labelledby="ec-account-profile-notifications">
        <h2 id="ec-account-profile-notifications" class="text-xl font-bold">{{ __( 'Notifications' ) }}</h2>

        <form wire:submit="savePreferences" class="flex max-w-2xl flex-col gap-6" data-account-preferences-form>
            @foreach ( $channels as $channel => $channelLabel )
                <fieldset class="flex flex-col gap-3" wire:key="account-preferences-{{ $channel }}" data-account-preferences-channel="{{ $channel }}">
                    <legend class="mb-2 font-semibold">{{ $channelLabel }}</legend>

                    @foreach ( $categories as $category => $definition )
                        <div wire:key="account-preference-{{ $channel }}-{{ $category }}" data-account-preference="{{ $channel }}.{{ $category }}">
                            @if ( 'marketing' === $category && ! $consented )
                                <x-artisanpack-toggle :id="'preferences-' . $channel . '-' . $category" :label="$definition['label']" :hint="__( 'Agree to news and offers in your details above first.' )" disabled data-account-preference-needs-consent />
                            @elseif ( $definition['locked'] )
                                <x-artisanpack-toggle :id="'preferences-' . $channel . '-' . $category" :label="$definition['label']" :hint="$definition['description']" checked disabled data-account-preference-locked />
                            @else
                                <x-artisanpack-toggle :id="'preferences-' . $channel . '-' . $category" :label="$definition['label']" :hint="$definition['description']" wire:model="preferences.{{ $channel }}.{{ $category }}" />
                            @endif
                        </div>
                    @endforeach
                </fieldset>
            @endforeach

            <div>
                <x-artisanpack-button type="submit" :label="__( 'Save notification preferences' )" color="primary" spinner="savePreferences" wire:loading.attr="disabled" wire:target="savePreferences" data-account-preferences-save />
            </div>
        </form>
    </section>
</div>
