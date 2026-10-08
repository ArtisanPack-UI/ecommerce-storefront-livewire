{{--
    The guest order lookup. See Livewire\Order\Lookup.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex max-w-xl flex-col gap-6" data-order-lookup>
    <p>{{ __( 'Enter the email address you used at checkout and your order number. You\'ll find both in your order confirmation email.' ) }}</p>

    <div role="alert" aria-live="assertive">
        @if ( null !== $failure )
            <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-error alert-soft" :title="$failure" data-order-lookup-failure />
        @endif
    </div>

    <form wire:submit="find" class="flex flex-col gap-4" novalidate data-order-lookup-form>
        <x-artisanpack-input id="email" type="email" :label="__( 'Email' )" autocomplete="email" required wire:model="email" />
        <x-artisanpack-input id="orderNumber" :label="__( 'Order number' )" autocomplete="off" required wire:model="orderNumber" />

        <div>
            <x-artisanpack-button type="submit" :label="__( 'Find my order' )" color="primary" spinner="find" wire:loading.attr="disabled" wire:target="find" data-order-lookup-submit />
        </div>
    </form>

    @if ( null !== $loginUrl )
        <p class="text-sm" data-order-lookup-login>
            {{ __( 'Have an account?' ) }}
            <a href="{{ $loginUrl }}" class="link link-primary">{{ __( 'Sign in to see all your orders' ) }}</a>
        </p>
    @endif
</div>
