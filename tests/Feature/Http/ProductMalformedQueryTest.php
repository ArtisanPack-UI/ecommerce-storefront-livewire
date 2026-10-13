<?php

declare( strict_types=1 );

/*
 * Crawlers and mangled share links send query values the product page's
 * typed `#[Url]` properties can't hold. Livewire 3 doesn't catch the
 * `TypeError`, so each of these was a 500 there (audit C1).
 */

beforeEach( function (): void {
    $this->simple   = makeProduct( 2500, [ 'name' => 'Linen trousers', 'slug' => 'linen-trousers' ] );
    $this->variable = makeVariableProduct( [
        'red/m'  => [ 'price' => 2000, 'stock' => 5 ],
        'blue/l' => [ 'price' => 2100, 'stock' => 5 ],
    ], [ 'slug' => 'linen-shirt' ] );
} );

it( 'renders the product page for a malformed variant', function ( string $slug, array $query ): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $slug, ...$query ] ) )
        ->assertOk();
} )->with( [
    'variable, text'       => [ 'linen-shirt', [ 'variant' => 'abc' ] ],
    'variable, array'      => [ 'linen-shirt', [ 'variant' => [ '1' ] ] ],
    'variable, nested'     => [ 'linen-shirt', [ 'variant' => [ 'a' => [ 'b' ] ] ] ],
    'variable, decimal'    => [ 'linen-shirt', [ 'variant' => '1.5' ] ],
    'variable, json'       => [ 'linen-shirt', [ 'variant' => '{"a":1}' ] ],
    'variable, rating'     => [ 'linen-shirt', [ 'review-rating' => [ 'x' ] ] ],
    'simple, text'         => [ 'linen-trousers', [ 'variant' => 'abc' ] ],
    'simple, array'        => [ 'linen-trousers', [ 'variant' => [ '1' ] ] ],
] );

it( 'still opens on a valid variant', function (): void {
    $redM = $this->variable['variants']['red/m'];

    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'linen-shirt', 'variant' => $redM->id ] ) )
        ->assertOk()
        ->assertSee( (string) $redM->sku );
} );
