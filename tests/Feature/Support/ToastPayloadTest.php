<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload;

it( 'builds the toaster event detail from a type, title and description', function (): void {
    expect( ToastPayload::make( 'warning', 'Heads up', 'Something changed.' ) )
        ->toBe( [ 'toast' => [ 'title' => 'Heads up', 'description' => 'Something changed.', 'color' => 'warning' ] ] );
} );

it( 'dispatches a toast event with JSON that can\'t break out of a script', function (): void {
    $script = ToastPayload::script( ToastPayload::make( 'success', '</script><img src=x>', null ) );

    expect( $script )->toStartWith( 'window.dispatchEvent( new CustomEvent( "toast", { detail: ' )
        ->not->toContain( '</script>' )
        ->not->toContain( '<img' )
        ->toContain( 'u003C' );
} );
