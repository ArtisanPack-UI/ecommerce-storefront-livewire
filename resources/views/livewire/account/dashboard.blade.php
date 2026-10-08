{{--
    The account dashboard. See Livewire\Account\Dashboard.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-8" data-account-dashboard>
    <p class="text-lg" data-account-greeting>
        {{ null === $name ? __( 'Welcome back.' ) : __( 'Welcome back, :name.', [ 'name' => $name ] ) }}
    </p>

    @if ( $failed )
        <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft" :title="__( 'Some of your account details couldn\'t be loaded' )" :description="__( 'Try again in a moment.' )" data-account-dashboard-failed />
    @endif

    @if ( $claimable > 0 && null !== $claimUrl )
        <x-artisanpack-alert
            icon="o-inbox-arrow-down"
            class="alert-info alert-soft"
            :title="trans_choice( 'We found :count order placed as a guest with your email|We found :count orders placed as a guest with your email', $claimable, [ 'count' => $claimable ] )"
            :description="__( 'Add them to your account to see them in your order history.' )"
            data-account-claim-prompt
        >
            <x-slot:actions>
                <x-artisanpack-button :label="__( 'Claim your orders' )" :link="$claimUrl" class="btn-sm" />
            </x-slot:actions>
        </x-artisanpack-alert>
    @endif

    <section class="flex flex-col gap-3" aria-labelledby="ec-account-recent-orders" data-account-recent-orders>
        <div class="flex items-center justify-between gap-4">
            <h2 id="ec-account-recent-orders" class="text-xl font-bold">{{ __( 'Recent orders' ) }}</h2>

            @if ( null !== $ordersUrl && [] !== $orders )
                <a href="{{ $ordersUrl }}" class="link text-sm" data-account-all-orders>{{ __( 'View all orders' ) }}</a>
            @endif
        </div>

        @if ( [] === $orders )
            <x-artisanpack-ec-empty-state icon="o-shopping-bag" :title="__( 'No orders yet' )" :description="__( 'Orders you place will appear here.' )" data-account-no-orders>
                @if ( null !== $catalogUrl )
                    <x-artisanpack-button :label="__( 'Start shopping' )" :link="$catalogUrl" color="primary" />
                @endif
            </x-artisanpack-ec-empty-state>
        @else
            <ul class="flex flex-col divide-y divide-base-content/10 rounded-box border border-base-content/10">
                @foreach ( $orders as $row )
                    <li class="flex flex-wrap items-center justify-between gap-4 p-4" wire:key="account-recent-order-{{ $row['id'] }}" data-account-recent-order="{{ $row['id'] }}">
                        <div>
                            @if ( null !== $row['url'] )
                                <a href="{{ $row['url'] }}" class="link font-semibold">{{ __( 'Order :number', [ 'number' => $row['number'] ] ) }}</a>
                            @else
                                <span class="font-semibold">{{ __( 'Order :number', [ 'number' => $row['number'] ] ) }}</span>
                            @endif
                            <p class="text-sm text-base-content/70">
                                {{ $row['date'] }} &middot; {{ trans_choice( ':count item|:count items', $row['items'], [ 'count' => $row['items'] ] ) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-artisanpack-badge :value="$row['status']" class="{{ $row['color'] }}" />
                            <x-artisanpack-ec-money :amount="$row['total']" :currency="$row['currency']" class="font-semibold" />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="grid gap-6 md:grid-cols-3">
        <section class="flex flex-col gap-2 rounded-box border border-base-content/10 p-5" aria-labelledby="ec-account-default-shipping" data-account-default-shipping>
            <h2 id="ec-account-default-shipping" class="font-semibold">{{ __( 'Default shipping address' ) }}</h2>

            @if ( null !== $shippingAddress )
                <x-artisanpack-ec-address :address="$shippingAddress" />
            @else
                <p class="text-sm text-base-content/70">{{ __( 'None saved yet.' ) }}</p>
            @endif
        </section>

        <section class="flex flex-col gap-2 rounded-box border border-base-content/10 p-5" aria-labelledby="ec-account-default-billing" data-account-default-billing>
            <h2 id="ec-account-default-billing" class="font-semibold">{{ __( 'Default billing address' ) }}</h2>

            @if ( null !== $billingAddress )
                <x-artisanpack-ec-address :address="$billingAddress" />
            @else
                <p class="text-sm text-base-content/70">{{ __( 'None saved yet.' ) }}</p>
            @endif
        </section>

        <section class="flex flex-col gap-2 rounded-box border border-base-content/10 p-5" aria-labelledby="ec-account-downloads" data-account-downloads-count>
            <h2 id="ec-account-downloads" class="font-semibold">{{ __( 'Downloads' ) }}</h2>
            <p>{{ trans_choice( ':count download available|:count downloads available', $downloads, [ 'count' => $downloads ] ) }}</p>
        </section>
    </div>

    <div class="flex flex-wrap gap-2" data-account-links>
        @if ( null !== $addressesUrl )
            <x-artisanpack-button :label="__( 'Manage addresses' )" :link="$addressesUrl" icon="o-map-pin" class="btn-sm" />
        @endif

        @if ( null !== $downloadsUrl )
            <x-artisanpack-button :label="__( 'Go to your downloads' )" :link="$downloadsUrl" icon="o-arrow-down-tray" class="btn-sm" />
        @endif

        @if ( null !== $profileUrl )
            <x-artisanpack-button :label="__( 'Edit your profile' )" :link="$profileUrl" icon="o-user-circle" class="btn-sm" data-account-host-link="profile" />
        @endif

        @if ( null !== $passwordUrl )
            <x-artisanpack-button :label="__( 'Change your password' )" :link="$passwordUrl" icon="o-key" class="btn-sm" data-account-host-link="password" />
        @endif
    </div>
</div>
