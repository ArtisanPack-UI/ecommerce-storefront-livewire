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

it( 'offers to continue to the gateway', function (): void {
    Livewire::test( RedirectDriver::class, redirectDriverProps( 'https://pay.example.test/session/1' ) )
        ->assertSeeHtml( 'data-payment-driver="redirect"' )
        ->assertSee( 'Continue to Hosted pay' )
        ->assertSee( 'You\'ll go to Hosted pay to pay, then come back here to review your order.' )
        ->assertSeeHtml( 'data-payment-redirect' );
} );

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
