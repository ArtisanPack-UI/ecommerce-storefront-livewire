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

if ( ! function_exists( 'checkoutCart' ) ) {
    /**
     * The shopper's cart, holding `$quantity` of each product (a new $19
     * product when none are given).
     *
     * @param  array<int, Product>  $products  Products to add.
     */
    function checkoutCart( array $products = [], int $quantity = 1 ): ArtisanPackUI\Ecommerce\Models\Cart
    {
        $cart = app( ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart::class )->current( true );

        foreach ( [] === $products ? [ makeProduct( 1900, [ 'name' => 'Mug' ] ) ] : $products as $product ) {
            app( ArtisanPackUI\Ecommerce\Services\StorefrontCartService::class )->addItem( $cart, $product->id, null, $quantity );
        }

        return $cart->refresh();
    }
}

if ( ! function_exists( 'checkoutShipping' ) ) {
    /**
     * A shipping zone for `$countries` with flat-rate methods, label =>
     * amount (or [ amount, delivery estimate ]).
     *
     * @param  array<string, array{0: int, 1: string}|int>  $methods    Methods.
     * @param  array<int, string>                           $countries  Country codes.
     */
    function checkoutShipping( array $methods = [ 'Standard' => 500 ], array $countries = [ 'US', 'CA' ] ): void
    {
        $zone = ArtisanPackUI\Ecommerce\Models\ShippingZone::factory()->create( [ 'name' => implode( '/', $countries ), 'country_codes' => $countries ] );

        foreach ( array_keys( $methods ) as $position => $label ) {
            [ $amount, $estimate ] = is_array( $methods[ $label ] ) ? $methods[ $label ] : [ $methods[ $label ], null ];

            ArtisanPackUI\Ecommerce\Models\ShippingMethod::factory()->create( [
                'zone_id'  => $zone->id,
                'key'      => 'flat-rate',
                'label'    => $label,
                'config'   => array_filter( [ 'amount' => $amount, 'delivery_estimate' => $estimate ], static fn ( mixed $value ): bool => null !== $value ),
                'position' => $position,
            ] );
        }
    }
}

if ( ! function_exists( 'checkoutGateway' ) ) {
    /**
     * Registers a fake gateway whose payment UI is the fake driver
     * (registered as `fake-driver`).
     */
    function checkoutGateway( string $key = 'fake', string $label = 'Fake card', string $driver = 'fake-driver' ): Tests\Fixtures\Gateways\FakeClientGateway
    {
        $gateway = new Tests\Fixtures\Gateways\FakeClientGateway( $key, $label, $driver );

        app( ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry::class )->register( $key, $gateway );

        if ( ! app( ArtisanPackUI\EcommerceStorefrontLivewire\Registries\PaymentDriverRegistry::class )->has( 'fake-driver' ) ) {
            Livewire\Livewire::component( 'fake-payment-driver', Tests\Fixtures\Livewire\FakePaymentDriver::class );
            app( ArtisanPackUI\EcommerceStorefrontLivewire\Registries\PaymentDriverRegistry::class )->register( 'fake-driver', 'fake-payment-driver' );
        }

        return $gateway;
    }
}

if ( ! function_exists( 'checkoutAddress' ) ) {
    /**
     * A complete US address for the checkout's address form.
     *
     * @param  array<string, string>  $overrides  Fields to change.
     *
     * @return array<string, string>
     */
    function checkoutAddress( array $overrides = [] ): array
    {
        return $overrides + [
            'first_name'   => 'Ada',
            'last_name'    => 'Lovelace',
            'company'      => '',
            'phone'        => '',
            'country_code' => 'US',
            'address1'     => '1 Main Street',
            'address2'     => '',
            'city'         => 'Springfield',
            'region'       => '',
            'region_code'  => 'IL',
            'postal_code'  => '62701',
        ];
    }
}
