<?php

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\User;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the package, the engine, and Livewire in Testbench.
| Unit tests run without a Laravel application.
|
*/

pest()->extend( Tests\TestCase::class )
    ->use( RefreshDatabase::class )
    ->in( 'Feature' );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

if ( ! function_exists( 'makeUser' ) ) {
    /**
     * Creates a host-app user.
     *
     * @param  array<string, mixed>  $attributes  Attribute overrides.
     */
    function makeUser( array $attributes = [] ): User
    {
        static $sequence = 0;
        $sequence++;

        return User::query()->create( $attributes + [
            'name'     => 'User ' . $sequence,
            'email'    => 'user' . $sequence . '-' . uniqid() . '@example.test',
            'password' => 'secret',
        ] );
    }
}

if ( ! function_exists( 'makeProduct' ) ) {
    /**
     * Creates an active product with a current price.
     *
     * @param  int                   $amount      Price in minor units.
     * @param  array<string, mixed>  $attributes  Product attribute overrides.
     * @param  int|null              $compareAt   Compare-at price in minor units.
     * @param  string                $currency    Price currency.
     */
    function makeProduct( int $amount = 1900, array $attributes = [], ?int $compareAt = null, string $currency = 'USD' ): Product
    {
        $product = Product::factory()->create( $attributes );

        ProductPrice::factory()->forPriceable( $product )->create( [
            'currency'          => $currency,
            'price_amount'      => $amount,
            'compare_at_amount' => $compareAt,
        ] );

        return $product;
    }
}
