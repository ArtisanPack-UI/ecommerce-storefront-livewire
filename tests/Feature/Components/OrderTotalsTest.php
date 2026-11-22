<?php

declare( strict_types=1 );

/*
 * partials/order-totals (#88): a meta list of subtotal, discounts,
 * shipping and tax, a Divider, then the total; tokens only.
 */

/**
 * Totals in the shape DescribesCart::totals() and
 * DescribesOrder::orderTotals() return.
 *
 * @param  array<string, mixed>  $overrides  Keys to replace.
 *
 * @return array<string, mixed>
 */
function orderTotalsFixture( array $overrides = [] ): array
{
    return $overrides + [
        'currency'  => 'USD',
        'subtotal'  => 24600,
        'discounts' => [ [ 'label' => 'Discount (WELCOME10)', 'amount' => 2460, 'free_shipping' => false ] ],
        'discount'  => 2460,
        'shipping'  => [ 'state' => 'chosen', 'label' => 'Standard', 'amount' => 600 ],
        'tax'       => [ 'amount' => 1827, 'estimated' => false, 'inclusive' => false ],
        'total'     => 24567,
        'refunded'  => 0,
    ];
}

it( 'lists the rows, a divider and the large total on the design system', function (): void {
    $this->blade( "@include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => \$totals ] )", [ 'totals' => orderTotalsFixture() ] )
        ->assertSee( 'class="ec-totals"', false )
        ->assertSee( 'data-cart-totals', false )
        ->assertSeeInOrder( [ 'Subtotal', '$246.00', 'Discount (WELCOME10)', 'Minus', '$24.60', 'Shipping', 'Standard', '$6.00', 'Tax', '$18.27', 'apui-divider', 'Total', '$245.67' ], false )
        ->assertSee( 'class="ec-totals__discount"', false )
        ->assertSee( 'data-cart-shipping-method', false )
        ->assertSee( 'ec-totals__row ec-totals__total', false )
        ->assertSee( 'data-cart-total', false )
        ->assertSee( 'class="apui-sr-only"', false )
        ->assertDontSee( 'data-order-refunded', false )
        ->assertDontSee( 'base-content', false )
        ->assertDontSee( 'class="sr-only"', false );
} );

it( 'says "Calculated at checkout" before an address, and "Tax (estimated)"', function (): void {
    $totals = orderTotalsFixture( [
        'discounts' => [],
        'shipping'  => [ 'state' => 'pending', 'label' => null, 'amount' => 0 ],
        'tax'       => [ 'amount' => 0, 'estimated' => true, 'inclusive' => false ],
    ] );

    $this->blade( "@include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => \$totals ] )", [ 'totals' => $totals ] )
        ->assertSee( 'data-cart-shipping="pending"', false )
        ->assertSee( 'Tax (estimated)' )
        ->assertSee( 'data-cart-tax="estimated"', false )
        ->assertSeeInOrder( [ 'Shipping', 'Calculated at checkout', 'Tax (estimated)', 'Calculated at checkout' ] )
        ->assertSee( 'class="ec-totals__pending"', false )
        ->assertDontSee( 'data-cart-discount', false );
} );

it( 'takes the caller\'s pending text, and shows free shipping and included tax', function (): void {
    $totals = orderTotalsFixture( [
        'discounts' => [ [ 'label' => 'FREESHIP', 'amount' => 0, 'free_shipping' => true ] ],
        'shipping'  => [ 'state' => 'free', 'label' => null, 'amount' => 0 ],
        'tax'       => [ 'amount' => 0, 'estimated' => true, 'inclusive' => true ],
    ] );

    $this->blade( "@include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => \$totals, 'taxPending' => 'Shown on the next step' ] )", [ 'totals' => $totals ] )
        ->assertSeeInOrder( [ 'FREESHIP', 'Free shipping', 'Shipping', 'Free', 'Included tax (estimated)', 'Shown on the next step' ] )
        ->assertDontSee( 'Minus' );
} );

it( 'leaves out shipping for an order with nothing to ship', function (): void {
    $totals = orderTotalsFixture( [ 'shipping' => [ 'state' => 'none', 'label' => null, 'amount' => 0 ] ] );

    $this->blade( "@include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => \$totals ] )", [ 'totals' => $totals ] )
        ->assertDontSee( 'data-cart-shipping', false );
} );

it( 'lists a placed order\'s refunds after the total', function (): void {
    $totals = orderTotalsFixture( [ 'refunded' => 1200 ] );

    $this->blade( "@include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => \$totals ] )", [ 'totals' => $totals ] )
        ->assertSeeInOrder( [ 'Total', '$245.67', 'data-order-refunded', 'Refunded', 'Minus', '$12.00' ], false );
} );

it( 'renders a cart\'s totals, which carry no refunds', function (): void {
    $totals = orderTotalsFixture();
    unset( $totals['refunded'] );

    $this->blade( "@include( 'ecommerce-storefront::partials.order-totals', [ 'totals' => \$totals ] )", [ 'totals' => $totals ] )
        ->assertSee( '$245.67' )
        ->assertDontSee( 'Refunded' );
} );
