{{--
    An order's confirmation (#143). See Livewire\Order\Confirmation.

    A success check icon tile, "Thank you for your order" (the page's h1 with
    `showTitle`, else an h2) and the confirmation email line, then a Card
    meta strip (order number in the mono face, placed date, status Badge)
    whose three cells stack below 640px. Guests then see two Alerts side by
    side: an info "Create an account to track orders" with a solid "Create
    an account", and a neutral "Look up this order later" with an outline
    "Order lookup". Then the order details, then "View in your account" (the
    owner) and an outline "Continue shopping".

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceConfirmationHeading = $showTitle ? 'h1' : 'h2' )
<div class="ec-confirmation" data-order-confirmation="{{ $order->id }}">
    <div class="ec-confirmation__header" data-order-confirmation-header>
        <x-artisanpack-icon name="circle-check" variant="tile" color="success" size="lg" />

        <{{ $ecommerceConfirmationHeading }} class="ec-confirmation__title">{{ __( 'Thank you for your order' ) }}</{{ $ecommerceConfirmationHeading }}>

        <p class="ec-confirmation__lead">{{ __( 'We\'ve sent a confirmation to :email.', [ 'email' => $order->email ] ) }}</p>
    </div>

    <x-artisanpack-card class="ec-confirmation__strip" data-order-confirmation-strip>
        <dl class="ec-confirmation__facts">
            <div class="ec-confirmation__fact">
                <dt>{{ __( 'Order number' ) }}</dt>
                <dd class="ec-confirmation__number" data-order-number><bdi>{{ $order->order_number }}</bdi></dd>
            </div>

            <div class="ec-confirmation__fact">
                <dt>{{ __( 'Placed' ) }}</dt>
                <dd>{{ $date }}</dd>
            </div>

            <div class="ec-confirmation__fact">
                <dt>{{ __( 'Status' ) }}</dt>
                <dd><x-artisanpack-badge :color="$statusColor" :leading-icon="$statusIcon" data-order-status>{{ $status }}</x-artisanpack-badge></dd>
            </div>
        </dl>
    </x-artisanpack-card>

    @if ( null !== $registerUrl || null !== $lookupUrl )
        <div class="ec-confirmation__hints" data-order-guest-hints>
            @if ( null !== $registerUrl )
                <x-artisanpack-alert color="info" icon="user" :title="__( 'Create an account to track orders' )" :description="__( 'Use :email to see this order and your future orders in one place.', [ 'email' => $order->email ] )" data-order-hint="register">
                    <x-slot:actions>
                        <x-artisanpack-button color="primary" size="sm" :href="$registerUrl">{{ __( 'Create an account' ) }}</x-artisanpack-button>
                    </x-slot:actions>
                </x-artisanpack-alert>
            @endif

            @if ( null !== $lookupUrl )
                <x-artisanpack-alert icon="search" :title="__( 'Look up this order later' )" :description="__( 'Find it any time with your email and order number :number.', [ 'number' => $order->order_number ] )" data-order-hint="lookup">
                    <x-slot:actions>
                        <x-artisanpack-button variant="outline" color="neutral" size="sm" :href="$lookupUrl">{{ __( 'Order lookup' ) }}</x-artisanpack-button>
                    </x-slot:actions>
                </x-artisanpack-alert>
            @endif
        </div>
    @endif

    @include( 'ecommerce-storefront::partials.order-details' )

    @if ( null !== $accountUrl || null !== $catalogUrl )
        <div class="ec-confirmation__actions" data-order-confirmation-actions>
            @if ( null !== $accountUrl )
                <x-artisanpack-button size="lg" color="primary" :href="$accountUrl" data-order-account-link>{{ __( 'View in your account' ) }}</x-artisanpack-button>
            @endif

            @if ( null !== $catalogUrl )
                <x-artisanpack-button size="lg" variant="outline" color="neutral" :href="$catalogUrl" leading-icon="arrow-left" data-order-continue>{{ __( 'Continue shopping' ) }}</x-artisanpack-button>
            @endif
        </div>
    @endif
</div>
