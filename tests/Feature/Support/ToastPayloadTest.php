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

it( 'adds the inverse variant, an icon and an action from the options', function (): void {
    expect( ToastPayload::make( 'success', 'Mug was removed from your cart.', null, [
        'variant' => 'inverse',
        'icon'    => 'trash',
        'action'  => [ 'label' => 'Undo', 'call' => 'undoRemove' ],
    ] ) )->toBe( [ 'toast' => [
        'title'       => 'Mug was removed from your cart.',
        'description' => null,
        'color'       => 'success',
        'variant'     => 'inverse',
        'icon'        => 'trash',
        'action'      => [ 'label' => 'Undo', 'call' => 'undoRemove' ],
    ] ] );
} );

it( 'keeps a toast id so a later toast can replace it', function (): void {
    expect( ToastPayload::make( 'success', 'Removed', null, [ 'id' => 'ecommerce-cart-line-removed' ] )['toast']['id'] )
        ->toBe( 'ecommerce-cart-line-removed' );
} );

it( 'drops options it does not know or trust', function ( array $options ): void {
    expect( ToastPayload::make( 'error', 'Oops', null, $options ) )
        ->toBe( [ 'toast' => [ 'title' => 'Oops', 'description' => null, 'color' => 'error' ] ] );
} )->with( [
    'id with a quote'      => [ [ 'id' => 'a"b' ] ],
    'id with a space'      => [ [ 'id' => 'a b' ] ],
    'default variant'      => [ [ 'variant' => 'default' ] ],
    'unknown variant'      => [ [ 'variant' => 'glass' ] ],
    'icon with markup'     => [ [ 'icon' => '<svg onload=x>' ] ],
    'javascript url'       => [ [ 'action' => [ 'label' => 'Go', 'url' => 'javascript:alert(1)' ] ] ],
    'protocol-relative'    => [ [ 'action' => [ 'label' => 'Go', 'url' => '//evil.test/cart' ] ] ],
    'backslash url'        => [ [ 'action' => [ 'label' => 'Go', 'url' => '/\\evil.test' ] ] ],
    'method with code'     => [ [ 'action' => [ 'label' => 'Undo', 'call' => 'undo(); alert(1)' ] ] ],
    'empty label'          => [ [ 'action' => [ 'label' => ' ', 'url' => '/cart' ] ] ],
    'action without label' => [ [ 'action' => [ 'url' => '/cart' ] ] ],
] );

it( 'turns a url action into a link and a call action into a $wire call', function (): void {
    $link = ToastPayload::script( ToastPayload::make( 'success', 'Added to your cart', 'Mug', [ 'action' => [ 'label' => 'View cart', 'url' => '/shop/cart' ] ] ) );
    $undo = ToastPayload::script( ToastPayload::make( 'success', 'Removed', null, [ 'action' => [ 'label' => 'Undo', 'call' => 'undoRemove' ] ] ), true );

    expect( $link )->toContain( 'Object.assign( {"title":"Added to your cart","description":"Mug","color":"success"}' )
        ->toContain( 'label: ["View cart"][0], onClick: () => window.location.assign( ["\\/shop\\/cart"][0] )' )
        ->and( $undo )->toContain( 'onClick: () => $wire.call( ["undoRemove"][0] )' );
} );

it( 'drops a call action from a toast shown on the next page, which has no $wire', function (): void {
    $script = ToastPayload::script( ToastPayload::make( 'success', 'Removed', null, [ 'action' => [ 'label' => 'Undo', 'call' => 'undoRemove' ] ] ) );

    expect( $script )->toBe( 'window.dispatchEvent( new CustomEvent( "toast", { detail: {"title":"Removed","description":null,"color":"success"} } ) );' );
} );

it( 'keeps an action label from breaking out of the script', function (): void {
    $script = ToastPayload::script( ToastPayload::make( 'success', 'Hi', null, [ 'action' => [ 'label' => '</script><img src=x>', 'url' => '/cart' ] ] ) );

    expect( $script )->not->toContain( '</script>' )->not->toContain( '<img' );
} );
