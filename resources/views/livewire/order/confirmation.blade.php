{{--
    An order's confirmation. See Livewire\Order\Confirmation.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-8" data-order-confirmation="{{ $order->id }}">
    <div class="flex flex-col gap-3 rounded-box border border-success/40 bg-success/10 p-6" data-order-confirmation-header>
        <div class="flex items-center gap-3">
            <x-artisanpack-icon name="o-check-circle" class="h-8 w-8 text-success" aria-hidden="true" />
            <h2 class="text-2xl font-bold">{{ __( 'Thank you for your order' ) }}</h2>
        </div>

        <p>{{ __( 'We\'ve sent a confirmation to :email.', [ 'email' => $order->email ] ) }}</p>

        <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
            <div>
                <dt class="text-base-content/70">{{ __( 'Order number' ) }}</dt>
                <dd class="font-semibold" data-order-number>{{ $order->order_number }}</dd>
            </div>

            <div>
                <dt class="text-base-content/70">{{ __( 'Placed' ) }}</dt>
                <dd>{{ $date }}</dd>
            </div>

            <div>
                <dt class="text-base-content/70">{{ __( 'Status' ) }}</dt>
                <dd><x-artisanpack-badge :value="$status" class="{{ $statusColor }}" data-order-status /></dd>
            </div>
        </dl>

        <div class="flex flex-wrap gap-2">
            @if ( null !== $accountUrl )
                <x-artisanpack-button :label="__( 'View in your account' )" :link="$accountUrl" color="primary" class="btn-sm" data-order-account-link />
            @endif

            @if ( null !== $catalogUrl )
                <x-artisanpack-button :label="__( 'Continue shopping' )" :link="$catalogUrl" class="btn-sm" data-order-continue />
            @endif
        </div>
    </div>

    @if ( null !== $registerUrl || null !== $lookupUrl )
        <div class="grid gap-4 sm:grid-cols-2" data-order-guest-hints>
            @if ( null !== $registerUrl )
                <x-artisanpack-alert icon="o-user-plus" class="alert-info alert-soft" :title="__( 'Create an account to track orders' )" :description="__( 'Use :email to see this order and your future orders in one place.', [ 'email' => $order->email ] )" data-order-hint="register">
                    <x-slot:actions>
                        <x-artisanpack-button :label="__( 'Create an account' )" :link="$registerUrl" class="btn-sm" />
                    </x-slot:actions>
                </x-artisanpack-alert>
            @endif

            @if ( null !== $lookupUrl )
                <x-artisanpack-alert icon="o-magnifying-glass" class="alert-soft" :title="__( 'Look up this order later' )" :description="__( 'Find it any time with your email and order number :number.', [ 'number' => $order->order_number ] )" data-order-hint="lookup">
                    <x-slot:actions>
                        <x-artisanpack-button :label="__( 'Order lookup' )" :link="$lookupUrl" class="btn-sm" />
                    </x-slot:actions>
                </x-artisanpack-alert>
            @endif
        </div>
    @endif

    @include( 'ecommerce-storefront::partials.order-details' )
</div>
