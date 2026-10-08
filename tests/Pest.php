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

if ( ! function_exists( 'setStock' ) ) {
    /**
     * Tracks stock for a product or variant.
     *
     * @param  Illuminate\Database\Eloquent\Model  $stockable  Product or variant.
     * @param  int                                  $quantity   Units on hand.
     */
    function setStock( Illuminate\Database\Eloquent\Model $stockable, int $quantity ): void
    {
        ArtisanPackUI\Ecommerce\Models\InventoryItem::factory()->create( [
            'stockable_type'    => $stockable->getMorphClass(),
            'stockable_id'      => $stockable->getKey(),
            'track_inventory'   => true,
            'allow_backorder'   => false,
            'quantity_on_hand'  => $quantity,
            'quantity_reserved' => 0,
        ] );
    }
}

if ( ! function_exists( 'makeVariableProduct' ) ) {
    /**
     * Creates a variable product with colour × size variants.
     *
     * `$variants` maps `colour/size` to the variant's price and, optionally,
     * stock (`[ 'price' => 2000, 'stock' => 0 ]`; no stock key leaves it
     * untracked). Combinations not listed don't exist.
     *
     * @param  array<string, array{price: int, stock?: int, image?: int}>  $variants    Variants.
     * @param  array<string, mixed>                                       $attributes  Product attribute overrides.
     * @param  string                                                     $skuPrefix   Variant SKU prefix (SKUs are unique).
     *
     * @return array{product: Product, groups: array<string, ArtisanPackUI\Ecommerce\Models\ProductAttribute>, values: array<string, ArtisanPackUI\Ecommerce\Models\ProductAttributeValue>, variants: array<string, ArtisanPackUI\Ecommerce\Models\ProductVariant>}
     */
    function makeVariableProduct( array $variants, array $attributes = [], string $skuPrefix = 'SHIRT' ): array
    {
        $product = Product::factory()->variable()->create( $attributes + [ 'name' => 'Linen shirt' ] );

        $colour = ArtisanPackUI\Ecommerce\Models\ProductAttribute::factory()->create( [ 'product_id' => $product->id, 'key' => 'colour', 'label' => 'Colour', 'position' => 0 ] );
        $size   = ArtisanPackUI\Ecommerce\Models\ProductAttribute::factory()->create( [ 'product_id' => $product->id, 'key' => 'size', 'label' => 'Size', 'position' => 1 ] );

        $values = [];

        foreach ( [ 'red' => [ 'Red', '#ff0000' ], 'blue' => [ 'Blue', '#0000ff' ] ] as $value => [ $label, $swatch ] ) {
            $values[ $value ] = ArtisanPackUI\Ecommerce\Models\ProductAttributeValue::factory()->create( [ 'product_attribute_id' => $colour->id, 'value' => $value, 'label' => $label, 'swatch' => $swatch, 'position' => count( $values ) ] );
        }

        foreach ( [ 'm' => 'M', 'l' => 'L', 'xl' => 'XL' ] as $value => $label ) {
            $values[ $value ] = ArtisanPackUI\Ecommerce\Models\ProductAttributeValue::factory()->create( [ 'product_attribute_id' => $size->id, 'value' => $value, 'label' => $label, 'position' => count( $values ) ] );
        }

        $made = [];

        foreach ( $variants as $combination => $spec ) {
            [ $c, $s ] = explode( '/', $combination );

            $variant = ArtisanPackUI\Ecommerce\Models\ProductVariant::factory()->create( [
                'product_id'     => $product->id,
                'name'           => $values[ $c ]->label . ' / ' . $values[ $s ]->label,
                'sku'            => strtoupper( $skuPrefix . '-' . $c . '-' . $s ),
                'image_media_id' => $spec['image'] ?? null,
                'position'       => count( $made ),
            ] );

            ProductPrice::factory()->forPriceable( $variant )->create( [ 'currency' => 'USD', 'price_amount' => $spec['price'], 'compare_at_amount' => null ] );

            foreach ( [ [ $colour, $values[ $c ] ], [ $size, $values[ $s ] ] ] as [ $group, $value ] ) {
                ArtisanPackUI\Ecommerce\Models\ProductVariantOptionValue::query()->create( [
                    'product_variant_id'         => $variant->id,
                    'product_attribute_id'       => $group->id,
                    'product_attribute_value_id' => $value->id,
                ] );
            }

            if ( array_key_exists( 'stock', $spec ) ) {
                setStock( $variant, $spec['stock'] );
            }

            $made[ $combination ] = $variant;
        }

        return [ 'product' => $product, 'groups' => [ 'colour' => $colour, 'size' => $size ], 'values' => $values, 'variants' => $made ];
    }
}
