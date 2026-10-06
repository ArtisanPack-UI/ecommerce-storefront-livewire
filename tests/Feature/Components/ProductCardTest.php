<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Inventory\StockStatus;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductImage;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use Illuminate\Database\Eloquent\Model;

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.productCard' );
} );

/**
 * Renders a card for `$product`.
 *
 * @param  array<string, mixed>  $props  Extra props.
 */
function renderCard( Product $product, string $props = '' ): string
{
    return (string) test()->blade( '<x-artisanpack-ec-sf-product-card :product="$product" ' . $props . ' />', [ 'product' => $product ] );
}

it( 'shows the name linked to the product page, the price, the stock state, and the image', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Ceramic mug', 'slug' => 'ceramic-mug', 'meta' => [ 'featured_image_url' => 'https://cdn.example.test/mug.jpg' ] ], 2500 );

    $html = renderCard( $product );

    expect( $html )
        ->toContain( 'href="' . route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'ceramic-mug' ] ) . '"' )
        ->toContain( 'Ceramic mug' )
        ->toContain( 'Sale price: was $25.00, now $19.00' )
        ->toContain( 'In stock' )
        ->toContain( 'src="https://cdn.example.test/mug.jpg"' )
        ->toContain( 'alt="Ceramic mug"' )
        ->toContain( 'loading="lazy"' )
        ->toContain( '<h3' );
} );

it( 'offers quick add for a simple product that can be bought', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Ceramic mug' ] );

    expect( renderCard( $product ) )
        ->toContain( 'wire:click="quickAdd( ' . $product->id . ' )"' )
        ->toContain( 'aria-label="Add Ceramic mug to cart"' );
} );

it( 'leaves quick add off for products that need choices, can\'t be bought, or when disabled', function ( Closure $make, string $props ): void {
    expect( renderCard( $make(), $props ) )->not->toContain( 'data-quick-add' );
} )->with( [
    'variable'     => [ fn (): Product => makeProduct( 1900, [ 'type' => 'variable' ] ), '' ],
    'unpriced'     => [ fn (): Product => Product::factory()->create(), '' ],
    'out of stock' => [ function (): Product {
        $product = makeProduct();
        InventoryItem::factory()->create( [ 'stockable_type' => $product->getMorphClass(), 'stockable_id' => $product->id, 'track_inventory' => true, 'allow_backorder' => false, 'quantity_on_hand' => 0 ] );

        return $product;
    }, '' ],
    'switched off' => [ fn (): Product => makeProduct(), ':quick-add="false"' ],
] );

it( 'uses the price and stock the caller passes instead of looking them up', function (): void {
    $product = makeProduct( 1900 );

    $html = (string) $this->blade(
        '<x-artisanpack-ec-sf-product-card :product="$product" :stock="$stock" />',
        [ 'product' => $product, 'stock' => new StockStatus( StockStatus::OUT_OF_STOCK ) ],
    );

    expect( $html )->toContain( 'Out of stock' )->not->toContain( 'data-quick-add' );
} );

it( 'falls back to the first gallery image and to a placeholder', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Lamp' ] );
    ProductImage::factory()->create( [ 'product_id' => $product->id, 'media_id' => null, 'image_url' => 'https://cdn.example.test/lamp.jpg', 'alt_text' => 'A brass lamp', 'position' => 0 ] );

    expect( renderCard( $product->fresh( 'images' ) ) )
        ->toContain( 'src="https://cdn.example.test/lamp.jpg"' )
        ->toContain( 'alt="A brass lamp"' );

    expect( renderCard( makeProduct() ) )->not->toContain( '<img' );
} );

it( 'never renders an image URL that isn\'t http(s)', function (): void {
    $product = makeProduct( 1900, [ 'meta' => [ 'featured_image_url' => 'javascript:alert(1)' ] ] );

    expect( renderCard( $product ) )->not->toContain( 'javascript:' );
} );

it( 'skips media-library lookups when the library is absent', function (): void {
    ProductImages::fake( false );

    $product = makeProduct( 1900, [ 'featured_image_media_id' => 42, 'meta' => [ 'featured_image_url' => 'https://cdn.example.test/a.jpg' ] ] );

    expect( ProductImages::card( $product ) )->toBe( [ 'url' => 'https://cdn.example.test/a.jpg', 'srcset' => null, 'alt' => $product->name ] );
} );

it( 'shows the rating only when the product has reviews', function (): void {
    expect( renderCard( makeProduct( 1900, [ 'avg_rating' => 4.5, 'reviews_count' => 8 ] ) ) )
        ->toContain( 'Rated 4.5 out of 5' )
        ->toContain( '8 reviews' );

    expect( renderCard( makeProduct() ) )->not->toContain( 'No reviews yet' );
} );

it( 'lets a satellite add badges and change the link through the productCard filter', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.productCard', static function ( array $card, Product $product ): array {
        $card['badges'][] = 'New';
        $card['url']      = '/custom/' . $product->slug;

        return $card;
    }, 10 );

    $product = makeProduct( 1900, [ 'slug' => 'teapot' ] );

    expect( renderCard( $product ) )
        ->toContain( 'New' )
        ->toContain( 'href="/custom/teapot"' );
} );

it( 'ignores filtered values of the wrong type', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.productCard', static fn ( array $card ): array => [
        ...$card,
        'price'     => 'free!',
        'stock'     => 'lots',
        'quick_add' => 'yes',
        'badges'    => [ 'Hot', 42, '' ],
        'name'      => [ 'not', 'a', 'string' ],
    ] );

    $product = makeProduct( 1900, [ 'name' => 'Teapot' ] );

    expect( renderCard( $product ) )
        ->toContain( '$19.00' )
        ->toContain( 'In stock' )
        ->toContain( 'Teapot' )
        ->toContain( 'Hot' )
        ->toContain( 'data-quick-add' );
} );

it( 'uses the requested heading level', function (): void {
    expect( renderCard( makeProduct(), ':heading-level="2"' ) )->toContain( '<h2' );
} );

it( 'renders a lone card without lazy loading its gallery', function (): void {
    Model::preventLazyLoading();

    $product = makeProduct( 1900 );
    ProductImage::factory()->create( [ 'product_id' => $product->id, 'media_id' => null, 'image_url' => 'https://cdn.example.test/solo.jpg' ] );

    try {
        expect( renderCard( Product::query()->find( $product->id ) ) )->toContain( 'https://cdn.example.test/solo.jpg' );
    } finally {
        Model::preventLazyLoading( false );
    }
} );
