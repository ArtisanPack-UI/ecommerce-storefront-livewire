{{--
    The guest order lookup (#144). See Livewire\Order\Lookup.

    A centered 480px column: with `showTitle` (the lookup page) "Find your
    order" as the h1, then the line about where to find both details. A
    failure is an error Alert above the form; when no order matched it says
    what to check and the order number field is marked invalid. The form
    sits in a Card: large email (with a mail icon) and order number fields,
    then a full-width large "Find my order". "Have an account? Sign in to
    see all your orders" sits under the Card.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-lookup" data-order-lookup>
    <div class="ec-lookup__intro">
        @if ( $showTitle )
            <h1 class="ec-lookup__title">{{ __( 'Find your order' ) }}</h1>
        @endif

        <p class="ec-lookup__lead">{{ __( 'Enter the email address you used at checkout and your order number. You\'ll find both in your order confirmation email.' ) }}</p>
    </div>

    <div role="alert" aria-live="assertive" class="ec-lookup__failure">
        @if ( null !== $failure )
            <x-artisanpack-alert
                color="error"
                icon="circle-alert"
                :title="$failure"
                :description="$notFound ? __( 'Check the order number and use the same email as at checkout.' ) : null"
                data-order-lookup-failure
            />
        @endif
    </div>

    <x-artisanpack-card class="ec-lookup__card">
        <form wire:submit="find" class="ec-lookup__form" novalidate data-order-lookup-form>
            @include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => 'ec-order-lookup' ] )

            <x-artisanpack-ec-field id="email" type="email" size="lg" leading-icon="mail" :label="__( 'Email' )" autocomplete="email" required wire:model="email" />
            <x-artisanpack-ec-field id="orderNumber" size="lg" class="ec-lookup__number" :label="__( 'Order number' )" autocomplete="off" spellcheck="false" required :invalid="$notFound" wire:model="orderNumber" />

            <x-artisanpack-button type="submit" size="lg" color="primary" class="ec-lookup__submit" wire:loading.attr="disabled" wire:target="find" data-order-lookup-submit>{{ __( 'Find my order' ) }}</x-artisanpack-button>
        </form>
    </x-artisanpack-card>

    @if ( null !== $loginUrl )
        <p class="ec-lookup__login" data-order-lookup-login>
            {{ __( 'Have an account?' ) }}
            <x-artisanpack-link :href="$loginUrl" :navigate="false">{{ __( 'Sign in to see all your orders' ) }}</x-artisanpack-link>
        </p>
    @endif
</div>
