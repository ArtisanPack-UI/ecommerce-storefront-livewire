<?php

/**
 * Account address book component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account;

use ArtisanPackUI\Ecommerce\Exceptions\CustomerWriteException;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\RateLimiting\RateLimitSubject;
use ArtisanPackUI\Ecommerce\Services\CustomerAddressService;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\EditsAddresses;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\AddressForm;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-account-addresses />`
 *
 * The shopper's address book (spec §7.5, S27): every saved address with
 * its default shipping and billing badges; add and edit in a modal using
 * the address form; delete after confirming; and make an address the
 * default for shipping or billing. Writes go through the engine's
 * `CustomerAddressService`, so its rules hold here too: the first address
 * becomes both defaults, a new default clears the old one, and its
 * `CustomerWriteException` messages show against the field they name.
 *
 * Saves count against `ecommerce.admin.mutate` per user, the same bucket
 * the engine's `me/addresses` endpoints use.
 *
 * Every address is loaded from the shopper's own book and checked against
 * the engine's address policy on each request; anyone else's is a 404. A
 * signed-in user without a customer record gets one on their first save.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Addresses extends Component
{
    use EditsAddresses;
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * How many addresses the book lists.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const LIMIT = 100;

    /**
     * The longest label an address can have (the engine's limit).
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const LABEL_MAX = 120;

    /**
     * The address being added or edited: the address form's fields plus
     * `label`, `is_default_shipping`, and `is_default_billing`.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    /**
     * Whether the add / edit modal is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $editing = false;

    /**
     * The address being edited, or null when adding one.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $editingId = null;

    /**
     * Whether the delete confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingDelete = false;

    /**
     * The address waiting for the delete confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deletingId = null;

    /**
     * Starts with an empty form.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->form = $this->blankForm();
    }

    /**
     * Opens the modal to add an address.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function add(): void
    {
        $this->resetErrorBag();

        $this->form      = $this->blankForm();
        $this->editingId = null;
        $this->editing   = true;
    }

    /**
     * Opens the modal to edit one of the shopper's addresses.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The address id.
     *
     * @return void
     */
    public function edit( int $id ): void
    {
        $address = $this->ownedAddress( $id );

        $this->resetErrorBag();

        $this->form      = $this->formFor( $address );
        $this->editingId = (int) $address->id;
        $this->editing   = true;
    }

    /**
     * After a field changes: a new country clears the region.
     *
     * @since 1.0.0
     *
     * @param  mixed        $value  The new value.
     * @param  string|null  $field  The changed field (null when the whole form was replaced).
     *
     * @return void
     */
    public function updatedForm( mixed $value, ?string $field = null ): void
    {
        $this->form = $this->addressChanged( $this->form, $field );
    }

    /**
     * Saves the address being added or edited.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function save(): void
    {
        $this->resetErrorBag();

        $errors = $this->addressErrors( $this->form, 'form' );

        if ( mb_strlen( trim( (string) ( $this->form['label'] ?? '' ) ) ) > self::LABEL_MAX ) {
            $errors['form.label'] = __( 'Keep this under :max characters.', [ 'max' => self::LABEL_MAX ] );
        }

        if ( [] !== $errors ) {
            foreach ( $errors as $field => $message ) {
                $this->addError( $field, $message );
            }

            return;
        }

        $address = null === $this->editingId ? null : $this->ownedAddress( $this->editingId );
        $saved   = $this->rateLimited(
            'ecommerce.admin.mutate',
            fn (): bool => $this->persist( $address ),
            RateLimitSubject::customer( auth()->user(), request()->ip() ?? '127.0.0.1' ),
        );

        if ( true !== $saved ) {
            return;
        }

        $this->editing   = false;
        $this->editingId = null;
        $this->form      = $this->blankForm();

        $this->toastSuccess( __( 'Address saved' ) );
    }

    /**
     * Asks before deleting one of the shopper's addresses.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The address id.
     *
     * @return void
     */
    public function confirmDelete( int $id ): void
    {
        $this->deletingId       = (int) $this->ownedAddress( $id )->id;
        $this->confirmingDelete = true;
    }

    /**
     * Deletes the address the shopper confirmed. A deleted default isn't
     * replaced; the shopper picks a new one.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function delete(): void
    {
        if ( null === $this->deletingId ) {
            $this->confirmingDelete = false;

            return;
        }

        $address = $this->ownedAddress( $this->deletingId );

        $this->confirmingDelete = false;
        $this->deletingId       = null;

        try {
            app( CustomerAddressService::class )->delete( $address, (int) auth()->id() );
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->toastError( __( 'We couldn\'t delete the address' ), __( 'Try again in a moment.' ) );

            return;
        }

        $this->toastSuccess( __( 'Address deleted' ) );
    }

    /**
     * Makes one of the shopper's addresses the default for shipping or
     * billing.
     *
     * @since 1.0.0
     *
     * @param  int     $id    The address id.
     * @param  string  $type  `shipping` or `billing`.
     *
     * @return void
     */
    public function makeDefault( int $id, string $type ): void
    {
        if ( ! in_array( $type, [ 'shipping', 'billing' ], true ) ) {
            return;
        }

        $address = $this->ownedAddress( $id );

        try {
            app( CustomerAddressService::class )->update( $address, [ 'is_default_' . $type => true ], (int) auth()->id() );
        } catch ( CustomerWriteException $exception ) {
            $this->toastError( __( 'We couldn\'t change your default address' ), $exception->getMessage() );

            return;
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->toastError( __( 'We couldn\'t change your default address' ), __( 'Try again in a moment.' ) );

            return;
        }

        $this->toastSuccess( 'shipping' === $type ? __( 'Default shipping address updated' ) : __( 'Default billing address updated' ) );
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $customer  = $this->customer();
        $addresses = [];
        $failed    = false;

        if ( null !== $customer ) {
            try {
                $addresses = $customer->addresses()
                    ->orderByDesc( 'is_default_shipping' )
                    ->orderByDesc( 'is_default_billing' )
                    ->orderBy( 'id' )
                    ->limit( self::LIMIT )
                    ->get()
                    ->map( static fn ( CustomerAddress $address ): array => [
                        'id'               => (int) $address->id,
                        'label'            => '' !== trim( (string) $address->label ) ? trim( (string) $address->label ) : null,
                        'address'          => $address->toArray(),
                        'default_shipping' => (bool) $address->is_default_shipping,
                        'default_billing'  => (bool) $address->is_default_billing,
                    ] )
                    ->all();
            } catch ( Throwable $exception ) {
                report( $exception );

                $failed = true;
            }
        }

        $editingAddress = null === $this->editingId ? null : collect( $addresses )->firstWhere( 'id', $this->editingId );

        return view( 'ecommerce-storefront::livewire.account.addresses', [
            'addresses'         => $addresses,
            'failed'            => $failed,
            'isDefaultShipping' => true === ( $editingAddress['default_shipping'] ?? false ),
            'isDefaultBilling'  => true === ( $editingAddress['default_billing'] ?? false ),
        ] );
    }

    /**
     * Writes the form through the engine: a new address, or `$address`.
     *
     * @since 1.0.0
     *
     * @param  CustomerAddress|null  $address  The address being edited, or null to add one.
     *
     * @return bool Whether it was saved.
     */
    protected function persist( ?CustomerAddress $address ): bool
    {
        $service = app( CustomerAddressService::class );

        try {
            if ( null === $address ) {
                $customer = $this->customer( true );

                if ( null === $customer ) {
                    $this->toastError( __( 'We couldn\'t save the address' ), __( 'Your account isn\'t ready to save addresses yet. Verify your email address and try again.' ) );

                    return false;
                }

                $service->create( $customer, $this->attributes(), (int) auth()->id() );
            } else {
                $service->update( $address, $this->attributes(), (int) auth()->id() );
            }
        } catch ( CustomerWriteException $exception ) {
            $this->showWriteErrors( $exception );

            return false;
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->toastError( __( 'We couldn\'t save the address' ), __( 'Try again in a moment.' ) );

            return false;
        }

        return true;
    }

    /**
     * The shopper's customer record; with `$create`, one is made (or
     * linked) when they have none.
     *
     * @since 1.0.0
     *
     * @param  bool  $create  Create or link a customer record when none is linked.
     *
     * @return Customer|null
     */
    protected function customer( bool $create = false ): ?Customer
    {
        $user = auth()->user();

        if ( null === $user ) {
            return null;
        }

        return $create ? app( CustomerService::class )->customerForUser( $user, true ) : Customer::forUser( $user );
    }

    /**
     * One of the shopper's addresses, authorized by the engine's address
     * policy; anything else is a 404.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The address id.
     *
     * @return CustomerAddress
     */
    protected function ownedAddress( int $id ): CustomerAddress
    {
        $customer = $this->customer();
        $address  = $customer?->addresses()->whereKey( $id )->first();

        abort_if( null === $address || ! Gate::forUser( auth()->user() )->allows( 'update', $address ), 404 );

        return $address;
    }

    /**
     * An empty form in the store's country; the shopper's first address
     * is their default for both.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function blankForm(): array
    {
        return $this->blankAddress() + [
            'label'               => '',
            'is_default_shipping' => false,
            'is_default_billing'  => false,
        ];
    }

    /**
     * A saved address as the form.
     *
     * @since 1.0.0
     *
     * @param  CustomerAddress  $address  The address.
     *
     * @return array<string, mixed>
     */
    protected function formFor( CustomerAddress $address ): array
    {
        return $this->formAddress( $address->toArray() ) + [
            'label'               => (string) ( $address->label ?? '' ),
            'is_default_shipping' => (bool) $address->is_default_shipping,
            'is_default_billing'  => (bool) $address->is_default_billing,
        ];
    }

    /**
     * The form as the engine's address columns. Unticking a default
     * doesn't clear it (an address stops being the default when another
     * becomes it), so only a ticked box is sent.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function attributes(): array
    {
        $attributes = $this->toAddress( $this->form )->toArray();

        $attributes['label'] = trim( (string) ( $this->form['label'] ?? '' ) );

        foreach ( [ 'is_default_shipping', 'is_default_billing' ] as $flag ) {
            if ( true === filter_var( $this->form[ $flag ] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
                $attributes[ $flag ] = true;
            }
        }

        return $attributes;
    }

    /**
     * Shows the engine's errors against the fields they name, and the rest
     * as a toast.
     *
     * @since 1.0.0
     *
     * @param  CustomerWriteException  $exception  The engine's refusal.
     *
     * @return void
     */
    protected function showWriteErrors( CustomerWriteException $exception ): void
    {
        $general = [];

        foreach ( $exception->errors as $error ) {
            $field = $error['field'] ?? null;

            if ( is_string( $field ) && ( 'label' === $field || in_array( $field, AddressForm::FIELDS, true ) ) ) {
                $this->addError( 'form.' . $field, (string) $error['message'] );
            } else {
                $general[] = (string) $error['message'];
            }
        }

        if ( [] !== $general ) {
            $this->toastError( __( 'We couldn\'t save the address' ), implode( ' ', $general ) );
        }
    }
}
