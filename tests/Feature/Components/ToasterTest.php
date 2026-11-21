<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\Toaster;

it( 'renders the library toaster at the top end from 768px and along the bottom below', function (): void {
    $html = (string) $this->blade( '<x-artisanpack-ec-toaster />' );

    expect( $html )->toContain( 'id="apui-toaster"' )
        ->toContain( 'x-data="apuiToaster(Object.assign(' )
        ->toContain( 'window.matchMedia(&quot;(min-width: 48rem)&quot;).matches ? &quot;top-end&quot; : &quot;bottom&quot;' )
        ->toContain( 'Dismiss notification' );
} );

it( 'takes other placements and falls back on unknown ones', function (): void {
    $toaster = ( new Toaster( 'top-start', 'nowhere' ) )->withAttributes( [] );
    $unknown = ( new Toaster( 'sideways', 'top' ) )->withAttributes( [] );

    expect( $toaster->alpineData() )->toContain( '? "top-start" : "bottom"' )
        ->and( $unknown->alpineData() )->toContain( '? "top-end" : "top"' );
} );

it( 'is the toaster the storefront puts on every page', function (): void {
    expect( view( 'ecommerce-storefront::partials.global' )->render() )
        ->toContain( 'apuiToaster(Object.assign(' );
} );
