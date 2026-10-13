<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Concerns\DisablesStorefrontRoutes;

uses( DisablesStorefrontRoutes::class );

it( 'registers no storefront or account routes when routes are disabled', function (): void {
    expect( Route::has( 'artisanpack.ecommerce.storefront.catalog' ) )->toBeFalse()
        ->and( Route::has( 'artisanpack.ecommerce.account.dashboard' ) )->toBeFalse();
} );

it( 'still lets a host embed the components', function (): void {
    $product = makeProduct( 1500, [ 'name' => 'Embedded mug' ] );

    Livewire::test( 'artisanpack-ecommerce-storefront-catalog' )
        ->assertOk()
        ->assertSee( 'Embedded mug' )
        // No product route, so the card shows the name without a link.
        ->assertDontSee( 'data-product-link', false );

    expect( $product->exists )->toBeTrue();
} );
