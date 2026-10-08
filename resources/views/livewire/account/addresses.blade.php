{{--
    The address book. See Livewire\Account\Addresses.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6" data-account-addresses>
    @if ( $failed )
        <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-warning alert-soft" :title="__( 'Your addresses couldn\'t be loaded' )" :description="__( 'Try again in a moment.' )" data-account-addresses-failed />
    @endif

    @if ( [] === $addresses && ! $failed )
        <x-artisanpack-ec-empty-state icon="o-map-pin" :title="__( 'No saved addresses' )" :description="__( 'Save an address to check out faster.' )" data-account-no-addresses>
            <x-artisanpack-button :label="__( 'Add an address' )" icon="o-plus" color="primary" wire:click="add" data-account-address-add />
        </x-artisanpack-ec-empty-state>
    @elseif ( [] !== $addresses )
        <div class="flex justify-end">
            <x-artisanpack-button :label="__( 'Add an address' )" icon="o-plus" color="primary" wire:click="add" data-account-address-add />
        </div>

        <ul class="grid gap-4 md:grid-cols-2" aria-label="{{ __( 'Saved addresses' ) }}">
            @foreach ( $addresses as $row )
                <li class="flex flex-col gap-3 rounded-box border border-base-content/10 p-5" wire:key="account-address-{{ $row['id'] }}" data-account-address="{{ $row['id'] }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-semibold">{{ $row['label'] ?? __( 'Address' ) }}</h2>

                        <div class="flex flex-wrap gap-1">
                            @if ( $row['default_shipping'] )
                                <x-artisanpack-badge :value="__( 'Default shipping' )" class="badge-primary badge-soft" data-account-address-default="shipping" />
                            @endif

                            @if ( $row['default_billing'] )
                                <x-artisanpack-badge :value="__( 'Default billing' )" class="badge-secondary badge-soft" data-account-address-default="billing" />
                            @endif
                        </div>
                    </div>

                    <x-artisanpack-ec-address :address="$row['address']" />

                    <div class="mt-auto flex flex-wrap gap-2">
                        <x-artisanpack-button :label="__( 'Edit' )" icon="o-pencil" class="btn-sm" wire:click="edit( {{ $row['id'] }} )" data-account-address-edit="{{ $row['id'] }}" />
                        <x-artisanpack-button :label="__( 'Delete' )" icon="o-trash" class="btn-sm btn-ghost" wire:click="confirmDelete( {{ $row['id'] }} )" data-account-address-delete="{{ $row['id'] }}" />

                        @unless ( $row['default_shipping'] )
                            <x-artisanpack-button :label="__( 'Use for shipping' )" class="btn-sm btn-ghost" wire:click="makeDefault( {{ $row['id'] }}, 'shipping' )" wire:loading.attr="disabled" wire:target="makeDefault" data-account-address-make-default="shipping" />
                        @endunless

                        @unless ( $row['default_billing'] )
                            <x-artisanpack-button :label="__( 'Use for billing' )" class="btn-sm btn-ghost" wire:click="makeDefault( {{ $row['id'] }}, 'billing' )" wire:loading.attr="disabled" wire:target="makeDefault" data-account-address-make-default="billing" />
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <x-artisanpack-modal wire:model="editing" :title="null === $editingId ? __( 'Add an address' ) : __( 'Edit address' )" box-class="max-w-2xl" data-account-address-modal>
        <form wire:submit="save" class="flex flex-col gap-4" novalidate>
            <x-artisanpack-input id="form.label" :label="__( 'Label' )" :hint="__( 'For example, Home or Work.' )" wire:model="form.label" />

            <x-artisanpack-ec-address-form model="form" :country="$form['country_code'] ?? null" />

            <div class="flex flex-col gap-2">
                @if ( $isDefaultShipping )
                    <p class="text-sm" data-account-address-is-default="shipping">{{ __( 'This is your default shipping address.' ) }}</p>
                @else
                    <x-artisanpack-checkbox :label="__( 'Make this my default shipping address' )" wire:model="form.is_default_shipping" data-account-address-flag="shipping" />
                @endif

                @if ( $isDefaultBilling )
                    <p class="text-sm" data-account-address-is-default="billing">{{ __( 'This is your default billing address.' ) }}</p>
                @else
                    <x-artisanpack-checkbox :label="__( 'Make this my default billing address' )" wire:model="form.is_default_billing" data-account-address-flag="billing" />
                @endif
            </div>

            <div class="modal-action">
                <x-artisanpack-button :label="__( 'Cancel' )" class="btn-ghost" @click="$wire.editing = false" />
                <x-artisanpack-button type="submit" :label="__( 'Save address' )" color="primary" spinner="save" wire:loading.attr="disabled" wire:target="save" data-account-address-save />
            </div>
        </form>
    </x-artisanpack-modal>

    <x-artisanpack-modal wire:model="confirmingDelete" :title="__( 'Delete this address?' )" data-account-address-delete-modal>
        <p>{{ __( 'It will be removed from your address book. Orders that used it keep their copy.' ) }}</p>

        <x-slot:actions>
            <x-artisanpack-button :label="__( 'Cancel' )" class="btn-ghost" @click="$wire.confirmingDelete = false" />
            <x-artisanpack-button :label="__( 'Delete address' )" class="btn-error" wire:click="delete" wire:loading.attr="disabled" wire:target="delete" data-account-address-delete-confirm />
        </x-slot:actions>
    </x-artisanpack-modal>
</div>
