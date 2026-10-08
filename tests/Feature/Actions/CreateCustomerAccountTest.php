<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\EcommerceStorefrontLivewire\Actions\CreateCustomerAccount;
use ArtisanPackUI\EcommerceStorefrontLivewire\Contracts\CreatesCustomerAccounts;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\Fixtures\User;

it( 'is the default account action and implements the contract', function (): void {
    expect( config( 'artisanpack.ecommerce-storefront-livewire.auth.create_account_action' ) )->toBe( CreateCustomerAccount::class )
        ->and( app( CreateCustomerAccount::class ) )->toBeInstanceOf( CreatesCustomerAccounts::class );
} );

it( 'creates the user from the auth model, links the customer and order, and signs them in', function (): void {
    Event::fake( [ Registered::class ] );

    $order   = placedOrder( [ 'email' => 'grace@example.test' ] );
    $claimed = 0;

    addAction( 'ap.ecommerce.customer.orderClaimed', function () use ( &$claimed ): void {
        $claimed++;
    } );

    $user = app( CreateCustomerAccount::class )->create( $order, 'correct-horse-battery' );

    $customer = Customer::forUser( $user );

    expect( $user )->toBeInstanceOf( User::class )
        ->and( $user->email )->toBe( 'grace@example.test' )
        ->and( $user->name )->toBe( 'Ada Lovelace' )
        ->and( Hash::check( 'correct-horse-battery', $user->password ) )->toBeTrue()
        ->and( auth()->id() )->toBe( $user->id )
        ->and( $order->refresh() )->customer_id->toBe( $customer->id )->is_claimed->toBeTrue()
        ->and( $customer->refresh()->orders_count )->toBe( 1 )
        ->and( $claimed )->toBe( 1 );

    Event::assertDispatched( Registered::class );
} );

it( 'names the user after the email when the order has no name', function (): void {
    $order = placedOrder( [ 'email' => 'grace@example.test', 'billing_address' => null, 'shipping_address' => null ] );

    expect( app( CreateCustomerAccount::class )->create( $order, 'correct-horse-battery' )->name )->toBe( 'grace' );
} );

it( 'only attaches the order just placed, not other guest orders under the email', function (): void {
    $earlier = placedOrder( [ 'email' => 'grace@example.test' ] );
    $order   = placedOrder( [ 'email' => 'grace@example.test' ] );

    app( CreateCustomerAccount::class )->create( $order, 'correct-horse-battery' );

    expect( $order->refresh()->customer_id )->not->toBeNull()
        ->and( $earlier->refresh()->customer_id )->toBeNull();
} );

it( 'refuses an email that already has an account, in any case', function (): void {
    makeUser( [ 'email' => 'Grace@Example.test' ] );

    $action = app( CreateCustomerAccount::class );

    expect( $action->emailIsAvailable( 'grace@example.test' ) )->toBeFalse()
        ->and( $action->emailIsAvailable( 'ada@example.test' ) )->toBeTrue()
        ->and( fn () => $action->create( placedOrder( [ 'email' => 'grace@example.test' ] ), 'correct-horse-battery' ) )->toThrow( RuntimeException::class );
} );

it( 'checks the password against the application\'s rules', function (): void {
    $rules = app( CreateCustomerAccount::class )->passwordRules();

    expect( validator( [ 'password' => 'short' ], [ 'password' => $rules ] )->fails() )->toBeTrue()
        ->and( validator( [ 'password' => 'correct-horse-battery' ], [ 'password' => $rules ] )->passes() )->toBeTrue();
} );
