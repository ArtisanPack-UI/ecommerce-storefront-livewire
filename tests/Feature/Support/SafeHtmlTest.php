<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Show;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\SafeHtml;
use Livewire\Livewire;

it( 'removes executable markup', function ( string $payload, array $forbidden ): void {
    $clean = (string) SafeHtml::clean( $payload );

    foreach ( $forbidden as $needle ) {
        expect( strtolower( $clean ) )->not->toContain( strtolower( $needle ) );
    }
} )->with( [
    'custom element with x-data'      => [ '<my-el x-data x-init="alert(document.cookie)">hi</my-el>', [ '<my-el', 'x-data', 'x-init' ] ],
    'custom element with wire:click'  => [ '<x-promo wire:click="delete">hi</x-promo>', [ '<x-promo', 'wire:click' ] ],
    'x-data on a known element'       => [ '<div x-data="{}" x-init="alert(1)">a</div>', [ 'x-data', 'x-init' ] ],
    'x-on and @click'                 => [ '<p x-on:click="alert(1)" @click="alert(2)">b</p>', [ 'x-on', '@click', 'alert' ] ],
    'wire:click'                      => [ '<span wire:click="delete">c</span>', [ 'wire:' ] ],
    ':href binding'                   => [ '<a :href="evil" href="/ok">d</a>', [ ':href', 'evil' ] ],
    'is attribute'                    => [ '<p is="my-el">e</p>', [ ' is=' ] ],
    'javascript: URL'                 => [ '<a href="javascript:alert(1)">f</a>', [ 'href="javascript:' ] ],
    'mixed-case javascript: URL'      => [ '<a href="JaVaScRiPt:alert(1)">f</a>', [ 'href="javascript:' ] ],
    'data: image'                     => [ '<img src="data:image/svg+xml;base64,PHN2Zz4=">', [ 'src="data:' ] ],
    'event handlers'                  => [ '<img src="x" onerror="alert(1)"><body onload="alert(1)">', [ 'onerror', 'onload' ] ],
    'template'                        => [ '<template x-if="true"><b>t</b></template>', [ '<template', 'x-if' ] ],
    'svg script'                      => [ '<svg><script>alert(1)</script><a xlink:href="javascript:alert(1)">s</a></svg>', [ '<svg', '<script', 'xlink:href' ] ],
    'math href'                       => [ '<math><mi xlink:href="javascript:alert(1)">m</mi></math>', [ '<math', 'xlink:href' ] ],
    'iframe and object'               => [ '<iframe src="https://evil.test"></iframe><object data="x"></object>', [ '<iframe', '<object' ] ],
    'utility-class overlay'           => [ '<div class="fixed inset-0 z-50">overlay</div>', [ 'class=' ] ],
    'inline style'                    => [ '<p style="position:fixed">x</p>', [ 'style=' ] ],
    'forms'                           => [ '<form action="https://evil.test"><input name="password"><button>Go</button></form>', [ '<form', '<input', '<button' ] ],
    'link, meta and base'             => [ '<link rel="stylesheet" href="x"><meta http-equiv="refresh" content="0"><base href="https://evil.test">', [ '<link', '<meta', '<base' ] ],
    'reverse tabnabbing'              => [ '<a href="https://e.test" target="_blank" rel="opener">x</a>', [ 'rel="opener"' ] ],
] );

it( 'keeps ordinary formatting and links', function (): void {
    expect( SafeHtml::clean( '<p>Hello <strong>bold</strong> <em>it</em> <a href="https://example.test/a">link</a> <a href="mailto:a@b.test">mail</a></p><ul><li>One</li></ul>' ) )
        ->toBe( '<p>Hello <strong>bold</strong> <em>it</em> <a href="https://example.test/a">link</a> <a href="mailto:a@b.test">mail</a></p><ul><li>One</li></ul>' );
} );

it( 'adds noopener to targeted links', function (): void {
    expect( SafeHtml::clean( '<a href="/x" target="_blank">x</a>' ) )
        ->toBe( '<a href="/x" target="_blank" rel="noopener">x</a>' );
} );

it( 'reduces a custom-element payload to its text', function (): void {
    expect( SafeHtml::clean( '<my-el x-data x-init="alert(document.cookie)">hi</my-el>' ) )->toBe( 'hi' );
} );

it( 'cleans product descriptions the same way', function (): void {
    $product = makeProduct( 1900, [ 'description' => '<x-promo x-data x-init="alert(1)">promo</x-promo><p class="fixed inset-0">body</p>' ] );

    Livewire::test( Show::class, [ 'product' => $product ] )
        ->assertSee( 'promo' )
        ->assertDontSeeHtml( 'alert(1)' )
        ->assertDontSeeHtml( '<x-promo' )
        ->assertDontSeeHtml( 'class="fixed' );
} );
