<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Payment\RedirectDriver;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * The redirect driver's props for `$url`.
 *
 * @return array<string, mixed>
 */
function redirectDriverProps( ?string $url ): array
{
    return [
        'config'       => [ 'driver' => 'redirect', 'flow' => 'redirect', 'redirect_url' => $url, 'options' => [], 'gateway' => 'hosted' ],
        'gateway'      => 'hosted',
        'gatewayLabel' => 'Hosted pay',
        'reference'    => 'hosted_ps_1',
    ];
}

it( 'offers to continue to the gateway in a card with an icon tile and one line of explanation', function (): void {
    $html = Livewire::test( RedirectDriver::class, redirectDriverProps( 'https://pay.example.test/session/1' ) )
        ->assertSeeHtml( 'data-payment-driver="redirect"' )
        ->assertSee( 'Pay with Hosted pay' )
        ->assertSee( 'You\'ll finish paying on Hosted pay\'s site, then come back here.' )
        ->assertSee( 'Continue to Hosted pay' )
        ->assertDontSeeHtml( 'data-payment-unavailable' )
        ->html();

    expect( $html )
        ->toContain( 'class="apui-card ec-redirect__card"' )
        ->toMatch( '/class="apui-icon apui-icon-tile[^"]*"/' )
        ->toMatch( '/class="apui-button ec-redirect__continue"[^>]*data-variant="solid"[^>]*data-color="neutral"|data-variant="solid"[^>]*data-color="neutral"[^>]*class="apui-button ec-redirect__continue"/s' )
        ->toContain( 'data-payment-redirect' );
} );

it( 'names the payment provider when the gateway has no label', function (): void {
    Livewire::test( RedirectDriver::class, [ ...redirectDriverProps( 'https://pay.example.test/session/1' ), 'gatewayLabel' => '' ] )
        ->assertSee( 'Continue to the payment provider' )
        ->assertSee( 'You\'ll finish paying on the payment provider\'s site, then come back here.' );
} );

it( 'leaves no DaisyUI or Tailwind classes', function ( ?string $url ): void {
    expect( classTokens( Livewire::test( RedirectDriver::class, redirectDriverProps( $url ) )->html() ) )
        ->not->toContain( 'flex', 'flex-col', 'gap-3', 'text-sm', 'text-base-content/70' );
} )->with( [ 'available' => [ 'https://pay.example.test/session/1' ], 'unavailable' => [ null ] ] );

it( 'sends the shopper to the gateway', function (): void {
    Livewire::test( RedirectDriver::class, redirectDriverProps( 'https://pay.example.test/session/1' ) )
        ->call( 'confirm' )
        ->assertRedirect( 'https://pay.example.test/session/1' )
        ->assertNotDispatched( 'payment-failed' );
} );

it( 'refuses a missing or unsafe redirect URL', function ( ?string $url ): void {
    Livewire::test( RedirectDriver::class, redirectDriverProps( $url ) )
        ->assertSeeHtml( 'data-payment-unavailable' )
        ->assertSee( 'This payment method isn\'t available.' )
        ->call( 'confirm' )
        ->assertNoRedirect()
        ->assertDispatched( 'payment-failed', message: 'This payment method isn\'t available.', gateway: 'hosted' );
} )->with( [
    'missing'    => [ null ],
    'javascript' => [ 'javascript:alert(1)' ],
    'relative'   => [ '/pay' ],
] );

it( 'keeps its props out of the browser\'s reach', function ( string $property ): void {
    Livewire::test( RedirectDriver::class, redirectDriverProps( 'https://pay.example.test/session/1' ) )
        ->set( $property, 'https://evil.example.test' );
} )->with( [ 'config.redirect_url', 'reference', 'gateway' ] )->throws( CannotUpdateLockedPropertyException::class );
