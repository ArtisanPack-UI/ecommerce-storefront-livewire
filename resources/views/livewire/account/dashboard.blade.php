{{--
    The account dashboard (#146). See Livewire\Account\Dashboard.

    The greeting (the page's h1 with `showTitle`, else a lead paragraph),
    then the Recent orders Card: a divided List, each row a package icon
    tile, "Order {number}" linking to the order, the date and item count,
    the status Badge and the total; a new account gets the EmptyState with
    "Start shopping" instead. Then three Cards that stack below 768px: the
    default shipping and billing addresses ("Same as shipping" when they
    match) and the downloads, each with a Link onward. Then the claim prompt
    (an outline Card with "Claim your orders") and ghost links to edit the
    profile and change the password.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceGreeting = null === $name ? __( 'Welcome back.' ) : __( 'Welcome back, :name.', [ 'name' => $name ] ) )
<div class="ec-dashboard" data-account-dashboard>
    @if ( $showTitle )
        <h1 class="ec-dashboard__greeting" data-account-greeting>{{ $ecommerceGreeting }}</h1>
    @else
        <p class="ec-dashboard__lead" data-account-greeting>{{ $ecommerceGreeting }}</p>
    @endif

    @if ( $failed )
        <x-artisanpack-alert color="warning" icon="triangle-alert" :title="__( 'Some of your account details couldn\'t be loaded' )" :description="__( 'Try again in a moment.' )" data-account-dashboard-failed />
    @endif

    <x-artisanpack-card class="ec-order-card ec-order-card--flush" role="region" aria-labelledby="ec-account-recent-orders" data-account-recent-orders>
        <div class="ec-dashboard__card-head">
            <h2 id="ec-account-recent-orders" class="ec-order-card__title">{{ __( 'Recent orders' ) }}</h2>

            @if ( null !== $ordersUrl && [] !== $orders )
                <x-artisanpack-link :href="$ordersUrl" standalone :navigate="false" data-account-all-orders>{{ __( 'View all orders' ) }}</x-artisanpack-link>
            @endif
        </div>

        @if ( [] === $orders )
            <x-artisanpack-ec-empty-state icon="package" :title="__( 'No orders yet' )" :description="__( 'Orders you place will appear here.' )" :heading-level="3" class="ec-dashboard__empty" data-account-no-orders>
                @if ( null !== $catalogUrl )
                    <x-artisanpack-button :href="$catalogUrl" color="primary" size="sm">{{ __( 'Start shopping' ) }}</x-artisanpack-button>
                @endif
            </x-artisanpack-ec-empty-state>
        @else
            <x-artisanpack-list variant="divided" class="ec-dashboard__orders">
                @foreach ( $orders as $row )
                    <x-artisanpack-list-item class="ec-dashboard__order" wire:key="account-recent-order-{{ $row['id'] }}" data-account-recent-order="{{ $row['id'] }}">
                        <x-slot:leading>
                            <x-artisanpack-icon name="package" variant="tile" color="neutral" />
                        </x-slot:leading>

                        <x-slot:title>
                            @if ( null !== $row['url'] )
                                <a href="{{ $row['url'] }}" class="ec-dashboard__order-link">{{ __( 'Order :number', [ 'number' => $row['number'] ] ) }}</a>
                            @else
                                {{ __( 'Order :number', [ 'number' => $row['number'] ] ) }}
                            @endif
                        </x-slot:title>

                        <x-slot:description>
                            {{ $row['date'] }}<span aria-hidden="true"> &middot; </span><span class="apui-sr-only">, </span>{{ trans_choice( ':count item|:count items', $row['items'], [ 'count' => $row['items'] ] ) }}
                        </x-slot:description>

                        <x-slot:trailing>
                            <span class="ec-dashboard__order-end">
                                <x-artisanpack-badge :color="$row['color']" size="sm" data-order-status>{{ $row['status'] }}</x-artisanpack-badge>
                                <x-artisanpack-ec-money :amount="$row['total']" :currency="$row['currency']" class="ec-dashboard__order-total" />
                            </span>
                        </x-slot:trailing>
                    </x-artisanpack-list-item>
                @endforeach
            </x-artisanpack-list>
        @endif
    </x-artisanpack-card>

    <div class="ec-dashboard__cards">
        <x-artisanpack-card class="ec-order-card ec-dashboard__card" role="region" aria-labelledby="ec-account-default-shipping" data-account-default-shipping>
            <h2 id="ec-account-default-shipping" class="ec-dashboard__card-title">{{ __( 'Default shipping address' ) }}</h2>

            @if ( null !== $shippingAddress )
                <x-artisanpack-ec-address :address="$shippingAddress" />
            @else
                <p class="ec-dashboard__muted">{{ __( 'None saved yet.' ) }}</p>
            @endif

            @if ( null !== $addressesUrl )
                <x-artisanpack-link :href="$addressesUrl" standalone trailing-icon="chevron-right" :navigate="false" data-account-link="addresses">{{ __( 'Manage addresses' ) }}</x-artisanpack-link>
            @endif
        </x-artisanpack-card>

        <x-artisanpack-card class="ec-order-card ec-dashboard__card" role="region" aria-labelledby="ec-account-default-billing" data-account-default-billing>
            <h2 id="ec-account-default-billing" class="ec-dashboard__card-title">{{ __( 'Default billing address' ) }}</h2>

            @if ( null !== $billingAddress && null !== $shippingAddress && $billingSame )
                <p data-account-billing-same>{{ __( 'Same as shipping' ) }}</p>
            @elseif ( null !== $billingAddress )
                <x-artisanpack-ec-address :address="$billingAddress" />
            @else
                <p class="ec-dashboard__muted">{{ __( 'None saved yet.' ) }}</p>
            @endif

            @if ( null !== $addressesUrl )
                <x-artisanpack-link :href="$addressesUrl" standalone trailing-icon="chevron-right" :navigate="false">{{ __( 'Manage addresses' ) }}</x-artisanpack-link>
            @endif
        </x-artisanpack-card>

        <x-artisanpack-card class="ec-order-card ec-dashboard__card" role="region" aria-labelledby="ec-account-downloads" data-account-downloads-count>
            <h2 id="ec-account-downloads" class="ec-dashboard__card-title">{{ __( 'Downloads' ) }}</h2>

            @if ( $downloads > 0 )
                <p>{{ trans_choice( ':count download available|:count downloads available', $downloads, [ 'count' => $downloads ] ) }}</p>
            @else
                <p class="ec-dashboard__muted">{{ __( 'Files from your orders will appear here.' ) }}</p>
            @endif

            @if ( null !== $downloadsUrl )
                <x-artisanpack-link :href="$downloadsUrl" standalone trailing-icon="chevron-right" :navigate="false" data-account-link="downloads">{{ __( 'Go to your downloads' ) }}</x-artisanpack-link>
            @endif
        </x-artisanpack-card>
    </div>

    @if ( $claimable > 0 && null !== $claimUrl )
        <x-artisanpack-card class="ec-dashboard__claim" data-account-claim-prompt>
            <div class="ec-dashboard__claim-body">
                <x-artisanpack-icon name="package" />

                <div class="ec-dashboard__claim-text">
                    <p class="ec-dashboard__claim-title">{{ trans_choice( 'We found :count order placed as a guest with your email|We found :count orders placed as a guest with your email', $claimable, [ 'count' => $claimable ] ) }}</p>
                    <p class="ec-dashboard__claim-description">{{ __( 'Add them to your account to see them in your order history.' ) }}</p>
                </div>

                <x-artisanpack-button variant="outline" color="neutral" size="sm" :href="$claimUrl">{{ __( 'Claim your orders' ) }}</x-artisanpack-button>
            </div>
        </x-artisanpack-card>
    @endif

    @if ( null !== $profileUrl || null !== $passwordUrl )
        <div class="ec-dashboard__links" data-account-links>
            @if ( null !== $profileUrl )
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" :href="$profileUrl" leading-icon="pencil" data-account-host-link="profile">{{ __( 'Edit your profile' ) }}</x-artisanpack-button>
            @endif

            @if ( null !== $passwordUrl )
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" :href="$passwordUrl" leading-icon="lock" data-account-host-link="password">{{ __( 'Change your password' ) }}</x-artisanpack-button>
            @endif
        </div>
    @endif
</div>
