<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Exceptions\CustomerWriteException;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Services\CustomerAddressService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Addresses;
use Livewire\Livewire;

/**
 * A saved address for `$customer`.
 *
 * @param  array<string, mixed>  $attributes  Overrides.
 */
function bookAddress( Customer $customer, array $attributes = [] ): CustomerAddress
{
    return CustomerAddress::factory()->create( $attributes + [
        'customer_id'         => $customer->id,
        'first_name'          => 'Ada',
        'last_name'           => 'Lovelace',
        'address1'            => '1 Main Street',
        'city'                => 'Springfield',
        'region_code'         => 'IL',
        'postal_code'         => '62701',
        'country_code'        => 'US',
        'is_default_shipping' => false,
        'is_default_billing'  => false,
    ] );
}

it( 'lists the shopper\'s addresses with their default badges', function (): void {
    [ , $customer ] = shopper();

    $home  = bookAddress( $customer, [ 'label' => 'Home', 'address1' => '9 Ship Lane', 'is_default_shipping' => true ] );
    $work  = bookAddress( $customer, [ 'label' => 'Work', 'address1' => '4 Bill Road', 'is_default_billing' => true ] );
    $other = bookAddress( Customer::factory()->create(), [ 'address1' => '7 Stranger Way' ] );

    Livewire::test( Addresses::class )
        ->assertSeeHtml( 'data-account-address="' . $home->id . '"' )
        ->assertSeeHtml( 'data-account-address="' . $work->id . '"' )
        ->assertDontSeeHtml( 'data-account-address="' . $other->id . '"' )
        ->assertDontSee( '7 Stranger Way' )
        ->assertSee( 'Home' )
        ->assertSee( '9 Ship Lane' )
        ->assertSeeHtml( 'data-account-address-default="shipping"' )
        ->assertSeeHtml( 'data-account-address-default="billing"' );
} );

it( 'shows an empty state when the shopper has no addresses, even without a customer record', function (): void {
    $this->actingAs( makeUser() );

    Livewire::test( Addresses::class )
        ->assertSeeHtml( 'data-account-no-addresses' )
        ->assertSee( 'No saved addresses' );
} );

it( 'adds the first address as the default for both, creating the customer record', function (): void {
    $user = makeUser();
    $this->actingAs( $user );

    Livewire::test( Addresses::class )
        ->call( 'add' )
        ->assertSet( 'editing', true )
        ->set( 'form', checkoutAddress( [ 'address1' => '12 Work Street' ] ) + [ 'label' => 'Work', 'is_default_shipping' => false, 'is_default_billing' => false ] )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'editing', false )
        ->assertSee( '12 Work Street' );

    $customer = Customer::forUser( $user );
    $address  = $customer?->addresses()->first();

    expect( $address )->not->toBeNull()
        ->and( $address->label )->toBe( 'Work' )
        ->and( $address->region )->toBe( 'Illinois' )
        ->and( $address->is_default_shipping )->toBeTrue()
        ->and( $address->is_default_billing )->toBeTrue();
} );

it( 'makes a new address the default shipping address when asked', function (): void {
    [ , $customer ] = shopper();
    $home           = bookAddress( $customer, [ 'is_default_shipping' => true, 'is_default_billing' => true ] );

    Livewire::test( Addresses::class )
        ->call( 'add' )
        ->set( 'form', checkoutAddress( [ 'address1' => '12 Work Street' ] ) + [ 'label' => 'Work', 'is_default_shipping' => true, 'is_default_billing' => false ] )
        ->call( 'save' )
        ->assertHasNoErrors();

    $work = $customer->addresses()->where( 'address1', '12 Work Street' )->first();

    expect( $work->is_default_shipping )->toBeTrue()
        ->and( $work->is_default_billing )->toBeFalse()
        ->and( $home->refresh()->is_default_shipping )->toBeFalse()
        ->and( $home->is_default_billing )->toBeTrue();
} );

it( 'edits an address in the modal', function (): void {
    [ , $customer ] = shopper();
    $address        = bookAddress( $customer, [ 'label' => 'Home' ] );

    Livewire::test( Addresses::class )
        ->call( 'edit', $address->id )
        ->assertSet( 'editing', true )
        ->assertSet( 'editingId', $address->id )
        ->assertSet( 'form.address1', '1 Main Street' )
        ->assertSet( 'form.label', 'Home' )
        ->set( 'form.city', 'Shelbyville' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'editing', false );

    expect( $address->refresh()->city )->toBe( 'Shelbyville' );
} );

it( 'clears the region when the country changes', function (): void {
    [ , $customer ] = shopper();
    $address        = bookAddress( $customer );

    Livewire::test( Addresses::class )
        ->call( 'edit', $address->id )
        ->set( 'form.country_code', 'GB' )
        ->assertSet( 'form.region_code', '' );
} );

it( 'validates the address before saving', function (): void {
    [ , $customer ] = shopper();

    Livewire::test( Addresses::class )
        ->call( 'add' )
        ->set( 'form', checkoutAddress( [ 'address1' => '', 'city' => '', 'postal_code' => 'nope', 'region_code' => '' ] ) + [ 'label' => str_repeat( 'x', 121 ) ] )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.address1', 'form.city', 'form.postal_code', 'form.region_code', 'form.label' ] )
        ->assertSet( 'editing', true );

    expect( $customer->addresses()->count() )->toBe( 0 );
} );

it( 'shows the engine\'s write errors against their fields', function (): void {
    [ , $customer ] = shopper();

    // The engine caps names at 120 characters; the form allows 255.
    Livewire::test( Addresses::class )
        ->call( 'add' )
        ->set( 'form', checkoutAddress( [ 'first_name' => str_repeat( 'A', 150 ) ] ) + [ 'label' => '' ] )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.first_name' ] )
        ->assertSee( 'This value can be at most 120 characters.' )
        ->assertSet( 'editing', true );

    expect( $customer->addresses()->count() )->toBe( 0 );
} );

it( 'shows an engine write error that names no field as a toast', function (): void {
    shopper();

    app()->instance( CustomerAddressService::class, new class( app( ArtisanPackUI\Ecommerce\Services\ActivityLogService::class ) ) extends CustomerAddressService {
        public function create( Customer $customer, array $attributes, ?int $actorUserId = null ): CustomerAddress
        {
            throw CustomerWriteException::field( null, 'blocked', 'Addresses are read-only right now.' );
        }
    } );

    $component = Livewire::test( Addresses::class )
        ->call( 'add' )
        ->set( 'form', checkoutAddress() + [ 'label' => '' ] )
        ->call( 'save' )
        ->assertSet( 'editing', true );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Addresses are read-only right now.' );
} );

it( 'reports an unexpected engine failure without losing the form', function (): void {
    shopper();

    app()->instance( CustomerAddressService::class, new class( app( ArtisanPackUI\Ecommerce\Services\ActivityLogService::class ) ) extends CustomerAddressService {
        public function create( Customer $customer, array $attributes, ?int $actorUserId = null ): CustomerAddress
        {
            throw new RuntimeException( 'Database is down.' );
        }
    } );

    Livewire::test( Addresses::class )
        ->call( 'add' )
        ->set( 'form', checkoutAddress( [ 'address1' => '12 Work Street' ] ) + [ 'label' => '' ] )
        ->call( 'save' )
        ->assertSet( 'editing', true )
        ->assertSet( 'form.address1', '12 Work Street' );
} );

it( 'deletes an address after the shopper confirms', function (): void {
    [ , $customer ] = shopper();
    $address        = bookAddress( $customer );

    Livewire::test( Addresses::class )
        ->call( 'confirmDelete', $address->id )
        ->assertSet( 'confirmingDelete', true )
        ->assertSet( 'deletingId', $address->id )
        ->call( 'delete' )
        ->assertSet( 'confirmingDelete', false )
        ->assertDontSeeHtml( 'data-account-address="' . $address->id . '"' );

    expect( CustomerAddress::query()->find( $address->id ) )->toBeNull();
} );

it( 'sets the default shipping and billing addresses', function (): void {
    [ , $customer ] = shopper();
    $home           = bookAddress( $customer, [ 'is_default_shipping' => true, 'is_default_billing' => true ] );
    $work           = bookAddress( $customer, [ 'address1' => '12 Work Street' ] );

    Livewire::test( Addresses::class )
        ->call( 'makeDefault', $work->id, 'shipping' )
        ->call( 'makeDefault', $work->id, 'nonsense' );

    expect( $work->refresh()->is_default_shipping )->toBeTrue()
        ->and( $work->is_default_billing )->toBeFalse()
        ->and( $home->refresh()->is_default_shipping )->toBeFalse()
        ->and( $home->is_default_billing )->toBeTrue();

    Livewire::test( Addresses::class )->call( 'makeDefault', $work->id, 'billing' );

    expect( $work->refresh()->is_default_billing )->toBeTrue()
        ->and( $home->refresh()->is_default_billing )->toBeFalse();
} );

it( 'answers 404 for another customer\'s address', function ( string $method, array $arguments ): void {
    shopper();
    $stranger = bookAddress( Customer::factory()->create() );

    Livewire::test( Addresses::class )
        ->call( $method, $stranger->id, ...$arguments )
        ->assertNotFound();

    expect( CustomerAddress::query()->find( $stranger->id ) )->not->toBeNull();
} )->with( [
    'edit'        => [ 'edit', [] ],
    'delete'      => [ 'confirmDelete', [] ],
    'set default' => [ 'makeDefault', [ 'shipping' ] ],
] );

it( 'rate limits saving addresses per user', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.admin.mutate.per_user', 1 );

    [ $user, $customer ] = shopper();
    Illuminate\Support\Facades\RateLimiter::clear( 'ecommerce:admin:mutate:user:' . $user->id );

    foreach ( [ '1 First Street', '2 Second Street' ] as $street ) {
        $component = Livewire::test( Addresses::class )
            ->call( 'add' )
            ->set( 'form', checkoutAddress( [ 'address1' => $street ] ) + [ 'label' => '' ] )
            ->call( 'save' );
    }

    $component->assertSet( 'editing', true );

    expect( $customer->addresses()->pluck( 'address1' )->all() )->toBe( [ '1 First Street' ] )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Too many attempts' );
} );
