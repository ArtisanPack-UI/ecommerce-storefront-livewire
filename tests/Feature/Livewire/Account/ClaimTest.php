<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerClaimAttempt;
use ArtisanPackUI\Ecommerce\Services\CustomerClaimService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Claim;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach( function (): void {
    RateLimiter::clear( 'ecommerce:claim:ip:' . sha1( '127.0.0.1' ) );
} );

/**
 * An unclaimed guest order placed with `$email`.
 */
function guestOrderFor( string $email, string $number = 'ORD-1001' ): ArtisanPackUI\Ecommerce\Models\Order
{
    return placedOrder( [ 'email' => $email, 'order_number' => $number, 'is_claimed' => false ] );
}

it( 'renders the claim form', function (): void {
    shopper();

    Livewire::test( Claim::class )
        ->assertSeeHtml( 'data-account-claim-form' )
        ->assertSee( 'Order number' )
        ->assertSee( 'Shipping postcode' );
} );

it( 'claims the shopper\'s guest orders and moves them into the order history', function (): void {
    [ , $customer ] = shopper();

    $first  = guestOrderFor( $customer->email, 'ORD-1001' );
    $second = guestOrderFor( $customer->email, 'ORD-1002' );
    $other  = guestOrderFor( 'someone@example.test', 'ORD-1003' );

    Livewire::test( Claim::class )
        ->set( 'orderNumber', ' ORD-1001 ' )
        ->set( 'postalCode', '62 701' )
        ->call( 'claim' )
        ->assertHasNoErrors()
        ->assertSet( 'failure', null )
        ->assertRedirect( route( 'artisanpack.ecommerce.account.orders.index' ) );

    expect( $first->refresh()->customer_id )->toBe( $customer->id )
        ->and( $first->is_claimed )->toBeTrue()
        ->and( $second->refresh()->customer_id )->toBe( $customer->id )
        ->and( $other->refresh()->customer_id )->toBeNull()
        ->and( session( ToastPayload::SESSION_KEY )['toast']['title'] ?? '' )->toBe( '2 orders added to your account' );
} );

it( 'requires the order number and postcode', function (): void {
    shopper();

    Livewire::test( Claim::class )
        ->call( 'claim' )
        ->assertHasErrors( [ 'orderNumber' => 'Enter the order number.', 'postalCode' => 'Enter the postcode the order was shipped to.' ] );

    Livewire::test( Claim::class )
        ->set( 'orderNumber', str_repeat( '1', 51 ) )
        ->set( 'postalCode', str_repeat( '1', 21 ) )
        ->call( 'claim' )
        ->assertHasErrors( [ 'orderNumber' => 'Keep this under 50 characters.', 'postalCode' => 'Keep this under 20 characters.' ] );

    expect( CustomerClaimAttempt::query()->count() )->toBe( 0 );
} );

it( 'gives one message for a wrong postcode, a wrong number, and someone else\'s order', function (): void {
    [ , $customer ] = shopper();

    guestOrderFor( $customer->email, 'ORD-1001' );
    guestOrderFor( 'someone@example.test', 'ORD-2002' );

    $messages = collect( [ [ 'ORD-1001', '99999' ], [ 'ORD-9999', '62701' ], [ 'ORD-2002', '62701' ] ] )
        ->map( fn ( array $attempt ): ?string => Livewire::test( Claim::class )
            ->set( 'orderNumber', $attempt[0] )
            ->set( 'postalCode', $attempt[1] )
            ->call( 'claim' )
            ->assertNoRedirect()
            ->get( 'failure' ) )
        ->unique();

    expect( $messages )->toHaveCount( 1 )
        ->and( $messages->first() )->toBe( 'We couldn\'t find a guest order with that order number and postcode under your email address.' );
} );

it( 'stops the shopper after the engine\'s failed-attempt cap', function (): void {
    config()->set( 'artisanpack.ecommerce.customers.claim_rate_limit', 2 );

    [ , $customer ] = shopper();
    $order          = guestOrderFor( $customer->email );

    foreach ( range( 1, 2 ) as $attempt ) {
        Livewire::test( Claim::class )->set( 'orderNumber', 'ORD-1001' )->set( 'postalCode', '00000' )->call( 'claim' );
    }

    Livewire::test( Claim::class )
        ->set( 'orderNumber', 'ORD-1001' )
        ->set( 'postalCode', '62701' )
        ->call( 'claim' )
        ->assertNoRedirect()
        ->assertSet( 'failure', fn ( ?string $failure ): bool => str_starts_with( (string) $failure, 'Too many attempts.' ) );

    expect( $order->refresh()->customer_id )->toBeNull();
} );

it( 'is rate limited per IP', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.claim.attempt.per_ip', 1 );

    [ , $customer ] = shopper();
    guestOrderFor( $customer->email );

    Livewire::test( Claim::class )->set( 'orderNumber', 'ORD-1001' )->set( 'postalCode', '00000' )->call( 'claim' );

    $component = Livewire::test( Claim::class )
        ->set( 'orderNumber', 'ORD-1001' )
        ->set( 'postalCode', '62701' )
        ->call( 'claim' )
        ->assertNoRedirect()
        ->assertSet( 'failure', fn ( ?string $failure ): bool => str_starts_with( (string) $failure, 'Too many attempts. Try again in' ) );

    expect( CustomerClaimAttempt::query()->count() )->toBe( 1 );
} );

it( 'tells a shopper whose account can\'t have a customer record yet', function (): void {
    // An unverified user can't take over a customer record another account's email already has.
    Customer::factory()->create( [ 'email' => 'taken@example.test' ] );
    $this->actingAs( makeUser( [ 'email' => 'taken@example.test' ] ) );

    Livewire::test( Claim::class )
        ->set( 'orderNumber', 'ORD-1001' )
        ->set( 'postalCode', '62701' )
        ->call( 'claim' )
        ->assertSet( 'failure', 'Your account can\'t claim orders yet. Verify your email address and try again.' );
} );

it( 'reports an unexpected engine failure', function (): void {
    shopper();

    app()->instance( CustomerClaimService::class, Mockery::mock( CustomerClaimService::class, function ( $mock ): void {
        $mock->shouldReceive( 'claim' )->andThrow( new RuntimeException( 'Database is down.' ) );
    } ) );

    Livewire::test( Claim::class )
        ->set( 'orderNumber', 'ORD-1001' )
        ->set( 'postalCode', '62701' )
        ->call( 'claim' )
        ->assertNoRedirect()
        ->assertSet( 'failure', 'We couldn\'t claim your orders right now. Try again in a moment.' );
} );

it( 'won\'t claim for an account whose email isn\'t verified, even with a customer record', function (): void {
    $user = makeUser();
    Customer::factory()->create( [ 'user_id' => $user->id, 'email' => $user->email ] );
    $order = guestOrderFor( $user->email );

    $unverified = new class extends Tests\Fixtures\User implements Illuminate\Contracts\Auth\MustVerifyEmail {
        use Illuminate\Auth\MustVerifyEmail;
    };
    $unverified->setRawAttributes( $user->getAttributes(), true );
    $unverified->exists = true;

    $this->actingAs( $unverified );

    Livewire::test( Claim::class )
        ->set( 'orderNumber', 'ORD-1001' )
        ->set( 'postalCode', '62701' )
        ->call( 'claim' )
        ->assertNoRedirect()
        ->assertSet( 'failure', 'Your account can\'t claim orders yet. Verify your email address and try again.' );

    expect( $order->refresh()->customer_id )->toBeNull()
        ->and( CustomerClaimAttempt::query()->count() )->toBe( 0 );
} );
