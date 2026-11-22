{{--
    The address book (#148). See Livewire\Account\Addresses.

    "Saved addresses" (the page's h1 with `showTitle`) with "Add an
    address" at the end. The addresses are Cards, two across from 768px:
    the label with its "Default shipping" and "Default billing" Badges, the
    address, then a ruled footer with "Edit", "Use for shipping" and "Use
    for billing" (each only while it isn't already the default) and
    "Delete" at the end. No addresses is the EmptyState in a Card. Adding
    and editing open the address form in a Dialog with "Cancel" and "Save
    address" in its footer; deleting asks first in an alert Dialog.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-addresses" data-account-addresses>
    @if ( $deletions > 0 )
        {{-- After a delete, focus (left on the removed card, or nowhere) moves to "Add an address". The dialog hands focus back as it closes, so this waits for it. --}}
        <span
            hidden
            wire:key="account-address-focus-{{ $deletions }}"
            x-data
            x-init="setTimeout( () => {
                if ( document.activeElement && document.activeElement !== document.body && document.activeElement.isConnected ) {
                    return;
                }

                $el.closest( '[data-account-addresses]' )?.querySelector( '[data-account-address-add]' )?.focus();
            }, 100 )"
            data-account-address-focus
        ></span>
    @endif

    @if ( $failed )
        <x-artisanpack-alert color="warning" icon="circle-alert" :title="__( 'Your addresses couldn\'t be loaded' )" :description="__( 'Try again in a moment.' )" data-account-addresses-failed />
    @endif

    @if ( $showTitle || ! $failed )
        <div class="ec-addresses__head">
            @if ( $showTitle )
                <h1 class="ec-addresses__title">{{ __( 'Saved addresses' ) }}</h1>
            @endif

            @if ( ! $failed )
                <x-artisanpack-button leading-icon="plus" color="primary" class="ec-addresses__add" wire:click="add" data-account-address-add>{{ __( 'Add an address' ) }}</x-artisanpack-button>
            @endif
        </div>
    @endif

    @if ( [] === $addresses && ! $failed )
        <x-artisanpack-ec-empty-state card icon="map-pin" :title="__( 'No saved addresses' )" :description="__( 'Save an address to check out faster.' )" data-account-no-addresses>
            <x-artisanpack-button color="primary" wire:click="add" data-account-address-add-empty>{{ __( 'Add an address' ) }}</x-artisanpack-button>
        </x-artisanpack-ec-empty-state>
    @elseif ( [] !== $addresses )
        <ul class="ec-addresses__list" aria-label="{{ __( 'Saved addresses' ) }}">
            @foreach ( $addresses as $row )
                {{-- Each button names its address for screen readers, after the visible word (WCAG 2.5.3). --}}
                @php( $ecommerceAddressName = implode( ', ', array_filter( [ $row['label'], $row['summary'] ] ) ) )
                <li class="ec-addresses__item" wire:key="account-address-{{ $row['id'] }}" data-account-address="{{ $row['id'] }}">
                    <x-artisanpack-card class="ec-address-book-card">
                        <div class="ec-address-book-card__head">
                            <h2 class="ec-address-book-card__title">{{ $row['label'] ?? __( 'Address' ) }}</h2>

                            @if ( $row['default_shipping'] )
                                <x-artisanpack-badge color="primary" size="sm" data-account-address-default="shipping">{{ __( 'Default shipping' ) }}</x-artisanpack-badge>
                            @endif

                            @if ( $row['default_billing'] )
                                <x-artisanpack-badge color="neutral" size="sm" data-account-address-default="billing">{{ __( 'Default billing' ) }}</x-artisanpack-badge>
                            @endif
                        </div>

                        <x-artisanpack-ec-address :address="$row['address']" />

                        <x-slot:footer>
                            <div class="ec-address-book-card__actions">
                                <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="pencil" wire:click="edit( {{ $row['id'] }} )" data-account-address-edit="{{ $row['id'] }}">{{ __( 'Edit' ) }}<span class="apui-sr-only">: {{ $ecommerceAddressName }}</span></x-artisanpack-button>

                                @unless ( $row['default_shipping'] )
                                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="makeDefault( {{ $row['id'] }}, 'shipping' )" wire:loading.attr="disabled" wire:target="makeDefault" data-account-address-make-default="shipping">{{ __( 'Use for shipping' ) }}<span class="apui-sr-only">: {{ $ecommerceAddressName }}</span></x-artisanpack-button>
                                @endunless

                                @unless ( $row['default_billing'] )
                                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="makeDefault( {{ $row['id'] }}, 'billing' )" wire:loading.attr="disabled" wire:target="makeDefault" data-account-address-make-default="billing">{{ __( 'Use for billing' ) }}<span class="apui-sr-only">: {{ $ecommerceAddressName }}</span></x-artisanpack-button>
                                @endunless

                                <x-artisanpack-button variant="ghost" color="error" size="sm" leading-icon="trash" class="ec-address-book-card__delete" wire:click="confirmDelete( {{ $row['id'] }} )" data-account-address-delete="{{ $row['id'] }}">{{ __( 'Delete' ) }}<span class="apui-sr-only">: {{ $ecommerceAddressName }}</span></x-artisanpack-button>
                            </div>
                        </x-slot:footer>
                    </x-artisanpack-card>
                </li>
            @endforeach
        </ul>
    @endif

    <x-artisanpack-dialog wire:model="editing" size="lg" :title="null === $editingId ? __( 'Add an address' ) : __( 'Edit address' )" data-account-address-modal>
        <form id="ec-account-address-form" wire:submit="save" class="ec-addresses__form" novalidate>
            @include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => 'ec-account-address' ] )

            <x-artisanpack-ec-field id="form.label" :label="__( 'Label' )" :hint="__( 'For example, Home or Work.' )" wire:model="form.label" />

            <x-artisanpack-ec-address-form model="form" :country="$form['country_code'] ?? null" />

            <div class="ec-addresses__flags">
                @if ( $isDefaultShipping )
                    <p class="ec-addresses__flag-note" data-account-address-is-default="shipping">{{ __( 'This is your default shipping address.' ) }}</p>
                @else
                    <x-artisanpack-checkbox :label="__( 'Make this my default shipping address' )" wire:model="form.is_default_shipping" data-account-address-flag="shipping" />
                @endif

                @if ( $isDefaultBilling )
                    <p class="ec-addresses__flag-note" data-account-address-is-default="billing">{{ __( 'This is your default billing address.' ) }}</p>
                @else
                    <x-artisanpack-checkbox :label="__( 'Make this my default billing address' )" wire:model="form.is_default_billing" data-account-address-flag="billing" />
                @endif
            </div>
        </form>

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" @click="$wire.editing = false">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="ec-account-address-form" color="primary" wire:loading.attr="disabled" wire:target="save" data-account-address-save>{{ __( 'Save address' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>

    <x-artisanpack-dialog
        wire:model="confirmingDelete"
        role="alertdialog"
        size="sm"
        color="error"
        icon="trash"
        hide-close
        :title="__( 'Delete this address?' )"
        :description="__( 'It will be removed from your address book. Orders that used it keep their copy.' )"
        data-account-address-delete-modal
    >
        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" @click="$wire.confirmingDelete = false">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button color="error" wire:click="delete" wire:loading.attr="disabled" wire:target="delete" data-account-address-delete-confirm>{{ __( 'Delete address' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>
</div>
