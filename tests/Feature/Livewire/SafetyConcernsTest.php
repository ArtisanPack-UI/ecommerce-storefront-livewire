<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\IdempotencyRecord;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\SafetyScreen;

beforeEach( function (): void {
    SafetyScreen::$runs = 0;
    SafetyScreen::$fail = false;
} );

afterEach( function (): void {
    Carbon::setTestNow();
} );

/**
 * The JavaScript effects (toasts) of a Livewire test call, as one string.
 */
function toastsOf( Testable $component ): string
{
    return (string) json_encode( $component->effects['xjs'] ?? [] );
}

describe( 'action tokens', function (): void {
    it( 'runs the action once and replays its result for a double click', function (): void {
        $component = Livewire::test( SafetyScreen::class );
        $token     = $component->get( 'token' );

        $component->call( 'place', $token )->assertSet( 'result', 'order-1' );
        $component->call( 'place', $token )->assertSet( 'result', 'order-1' );

        expect( SafetyScreen::$runs )->toBe( 1 );
    } );

    it( 'runs again with a fresh token', function (): void {
        $first  = Livewire::test( SafetyScreen::class );
        $second = Livewire::test( SafetyScreen::class );

        $first->call( 'place', $first->get( 'token' ) )->assertSet( 'result', 'order-1' );
        $second->call( 'place', $second->get( 'token' ) )->assertSet( 'result', 'order-2' );
    } );

    it( 'refuses a forged, foreign, or expired token without running the action', function ( Closure $token ): void {
        $component = Livewire::test( SafetyScreen::class );

        $component->call( 'place', $token( $component ) )->assertSet( 'result', null );

        expect( SafetyScreen::$runs )->toBe( 0 )
            ->and( toastsOf( $component ) )->toContain( 'This page has expired.' );
    } )->with( [
        'forged'              => static fn (): string => str_repeat( 'a', 40 ) . '.' . time() . '.deadbeef',
        'garbage'             => static fn (): string => 'not-a-token',
        'another subject'     => static fn ( $component ): string => $component->instance()->tokenFor( 8 ),
        'another shopper'     => static function ( $component ): string {
            $token = $component->get( 'token' );
            test()->actingAs( makeUser() );

            return $token;
        },
        'expired'             => static function ( $component ): string {
            $token = $component->get( 'token' );
            Carbon::setTestNow( now()->addHours( 13 ) );

            return $token;
        },
    ] );

    it( 'lets the shopper retry with the same token after the action fails', function (): void {
        $component = Livewire::test( SafetyScreen::class );
        $token     = $component->get( 'token' );

        SafetyScreen::$fail = true;

        expect( fn () => $component->call( 'place', $token ) )->toThrow( RuntimeException::class, 'Payment declined.' );

        $component->call( 'place', $token )->assertSet( 'result', 'order-1' );
    } );

    it( 'tells the shopper to wait while the first request is still running', function (): void {
        config()->set( 'artisanpack.ecommerce.idempotency.wait_ms', 0 );

        $component = Livewire::test( SafetyScreen::class );
        $token     = $component->get( 'token' );

        IdempotencyRecord::query()->create( [
            'actor_scope'     => 'in-process',
            'endpoint_key'    => 'ecommerce-storefront.place-order:7',
            'idempotency_key' => hash( 'sha256', $token ),
            'request_hash'    => hash( 'sha256', '' ),
            'locked_at'       => now(),
            'expires_at'      => now()->addDay(),
        ] );

        $component->call( 'place', $token )->assertSet( 'result', null );

        expect( SafetyScreen::$runs )->toBe( 0 )
            ->and( toastsOf( $component ) )->toContain( 'Still working on it.' );
    } );
} );

describe( 'rate limits', function (): void {
    it( 'runs actions within the limit and refuses the rest with the retry time', function (): void {
        config()->set( 'artisanpack.ecommerce.rate_limits.coupon.attempt.per_cart', 2 );

        $component = Livewire::test( SafetyScreen::class );

        $component->call( 'applyCoupon' )->assertSet( 'result', 1 )->assertSet( 'throttled', false );
        $component->call( 'applyCoupon' )->assertSet( 'result', 2 );
        $component->call( 'applyCoupon' )->assertSet( 'result', null )->assertSet( 'throttled', true );

        expect( SafetyScreen::$runs )->toBe( 2 )
            ->and( toastsOf( $component ) )->toContain( 'Slow down' )->toContain( 'Too many attempts. Try again in' );
    } );

    it( 'counts per shopper, not per route', function (): void {
        config()->set( 'artisanpack.ecommerce.rate_limits.coupon.attempt.per_cart', 1 );
        config()->set( 'artisanpack.ecommerce.rate_limits.coupon.attempt.per_ip', 100 );

        // Two guests with their own carts don't share a bucket.
        $first = Livewire::test( SafetyScreen::class );
        app( StorefrontCart::class )->current( true );
        $first->call( 'applyCoupon' )->assertSet( 'throttled', false );
        $first->call( 'applyCoupon' )->assertSet( 'throttled', true );

        app()->forgetScopedInstances();
        app( StorefrontCart::class )->current( true );

        Livewire::test( SafetyScreen::class )->call( 'applyCoupon' )->assertSet( 'throttled', false );
    } );
} );
