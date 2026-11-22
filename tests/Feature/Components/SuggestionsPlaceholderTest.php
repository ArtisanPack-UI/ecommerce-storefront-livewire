<?php

declare( strict_types=1 );

/*
 * livewire/product/related-products-placeholder (#90): design-system
 * Skeleton cards the size of a product card, in a busy list.
 */

it( 'renders skeleton product cards in a busy list', function (): void {
    $html = view( 'ecommerce-storefront::livewire.product.related-products-placeholder', [ 'title' => 'You may also like', 'count' => 3 ] )->render();

    expect( $html )->toStartWith( '<div data-related-placeholder>' )
        ->toContain( '<h2 class="ec-suggestions__title">You may also like</h2>' )
        ->toContain( '<span class="apui-sr-only" role="status">Loading suggestions…</span>' )
        ->toMatch( '/<ul class="ec-suggestions__grid" data-columns="3" aria-busy="true" data-skeleton="card">/' )
        ->and( substr_count( $html, '<li class="ec-suggestions__item" aria-hidden="true">' ) )->toBe( 3 )
        ->and( substr_count( $html, 'class="apui-card ec-skeleton-card"' ) )->toBe( 3 )
        ->and( substr_count( $html, 'ec-skeleton-card__media' ) )->toBe( 3 )
        ->and( substr_count( $html, 'data-shape="text"' ) )->toBe( 9 )
        ->and( substr_count( $html, 'ec-skeleton-card__button' ) )->toBe( 3 )
        ->and( $html )->not->toContain( 'style=' )
        ->not->toContain( 'class="skeleton' );
} );

it( 'shows between one and four cards', function ( int $count, int $expected ): void {
    $html = view( 'ecommerce-storefront::livewire.product.related-products-placeholder', [ 'title' => 'Recently viewed', 'count' => $count ] )->render();

    expect( substr_count( $html, 'class="ec-suggestions__item"' ) )->toBe( $expected )
        ->and( $html )->toContain( 'data-columns="' . $expected . '"' );
} )->with( [
    'none'  => [ 0, 1 ],
    'one'   => [ 1, 1 ],
    'four'  => [ 4, 4 ],
    'more'  => [ 9, 4 ],
] );
