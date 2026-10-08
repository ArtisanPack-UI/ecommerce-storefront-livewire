<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductImage;
use ArtisanPackUI\Ecommerce\Models\ProductReview;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\AddToCartBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\ProductGalleryBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\ProductPriceBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\ProductReviewsBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\SingleProductBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\SimpleForm;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Reviews;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Show;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontContext;
use Livewire\Livewire;

/**
 * Renders a product block the way visual-editor's renderer does.
 *
 * @param  array<string, mixed>  $attrs  Raw attributes.
 */
function renderProductBlock( StorefrontBlock $block, array $attrs = [] ): string
{
    return $block->render( $block->validateAttrs( $attrs ) );
}

/**
 * An approved review.
 */
function blockReview( Product $product, int $rating, string $title ): ProductReview
{
    return ProductReview::factory()->approved()->create( [
        'product_id'  => $product->id,
        'rating'      => $rating,
        'title'       => $title,
        'approved_at' => now(),
    ] );
}

beforeEach( function (): void {
    $this->lamp = makeProduct( 2500, [ 'name' => 'Desk lamp', 'slug' => 'desk-lamp' ], 3000 );
    $this->mug  = makeProduct( 900, [ 'name' => 'Mug', 'slug' => 'mug' ] );

    foreach ( [ 'front', 'side' ] as $position => $view ) {
        ProductImage::factory()->create( [ 'product_id' => $this->lamp->id, 'image_url' => "https://cdn.example.test/lamp-{$view}.jpg", 'alt_text' => "Lamp {$view}", 'position' => $position ] );
    }
} );

it( 'renders the full product component for the page\'s product', function (): void {
    app( StorefrontContext::class )->setProduct( $this->lamp );

    expect( renderProductBlock( new SingleProductBlock() ) )
        ->toContain( 'data-commerce-block="single-product"' )
        ->toContain( 'data-product-page="' . $this->lamp->id . '"' )
        ->toContain( 'Desk lamp' );
} );

it( 'renders a chosen product standalone, on any page', function ( string $class, string $marker ): void {
    $html = renderProductBlock( app( $class ), [ 'productId' => $this->mug->id ] );

    expect( $html )->toContain( $marker );
} )->with( [
    'single product' => [ SingleProductBlock::class, 'data-product-page' ],
    'gallery'        => [ ProductGalleryBlock::class, 'data-gallery' ],
    'price'          => [ ProductPriceBlock::class, '$9.00' ],
    'add to cart'    => [ AddToCartBlock::class, 'data-add-to-cart' ],
    'reviews'        => [ ProductReviewsBlock::class, 'id="reviews"' ],
] );

it( 'doesn\'t count an editor preview as a product view', function (): void {
    $views = 0;
    addAction( 'ap.ecommerce.product.viewed', function () use ( &$views ): void {
        $views++;
    } );

    Livewire::test( Show::class, [ 'product' => $this->lamp, 'recordView' => false ] );
    Livewire::test( Show::class, [ 'product' => $this->lamp ] );

    removeAllActions( 'ap.ecommerce.product.viewed' );

    expect( $views )->toBe( 1 );
} );

it( 'shows the gallery with thumbnails below, beside, or hidden, and zoom on or off', function (): void {
    app( StorefrontContext::class )->setProduct( $this->lamp );

    $below  = renderProductBlock( new ProductGalleryBlock() );
    $beside = renderProductBlock( new ProductGalleryBlock(), [ 'thumbnails' => 'start', 'zoom' => false ] );
    $none   = renderProductBlock( new ProductGalleryBlock(), [ 'thumbnails' => 'none' ] );

    expect( $below )->toContain( 'data-gallery-thumbnails="bottom"' )->toContain( 'data-gallery-thumbnail="1"' )->toContain( 'data-gallery-zoom' )->toContain( 'Lamp side' )
        ->and( $beside )->toContain( 'data-gallery-thumbnails="start"' )->toContain( 'sm:flex-row-reverse' )->not->toContain( 'data-gallery-zoom' )->toContain( 'data-gallery-stage' )
        ->and( $none )->not->toContain( 'data-gallery-thumbnail="1"' )
        ->and( renderProductBlock( new ProductGalleryBlock(), [ 'thumbnails' => 'diagonal' ] ) )->toContain( 'data-gallery-thumbnails="bottom"' );
} );

it( 'shows the display price with the sale', function (): void {
    app( StorefrontContext::class )->setProduct( $this->lamp );

    expect( renderProductBlock( new ProductPriceBlock() ) )->toContain( '$25.00' )->toContain( '$30.00' );
} );

it( 'renders the product\'s purchase form with its options', function (): void {
    app( StorefrontContext::class )->setProduct( $this->lamp );

    $default = renderProductBlock( new AddToCartBlock() );
    $custom  = renderProductBlock( new AddToCartBlock(), [ 'showQuantity' => false, 'buttonText' => '<b>Buy now</b>' ] );

    expect( $default )->toContain( 'data-purchase-form="simple"' )->toContain( 'Add to cart' )->toContain( 'ec-product-' . $this->lamp->id . '-quantity' )
        ->and( $custom )->not->toContain( 'ec-product-' . $this->lamp->id . '-quantity' )
        ->toContain( '&lt;b&gt;Buy now&lt;/b&gt;' )
        ->not->toContain( '<b>Buy now</b>' );
} );

it( 'adds one to the cart from a form without the quantity stepper', function (): void {
    Livewire::test( SimpleForm::class, [ 'product' => $this->mug, 'showQuantity' => false, 'buttonText' => '  Grab it  ' ] )
        ->assertSet( 'buttonText', 'Grab it' )
        ->assertSee( 'Grab it' )
        ->call( 'addToCart' )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated' );

    expect( (int) app( StorefrontCart::class )->current()?->items()->sum( 'quantity' ) )->toBe( 1 );
} );

it( 'still validates the quantity of a form without the stepper', function (): void {
    Livewire::test( SimpleForm::class, [ 'product' => $this->mug, 'showQuantity' => false ] )
        ->set( 'quantity', 0 )
        ->call( 'addToCart' )
        ->assertHasErrors( [ 'quantity' ] )
        ->assertSeeHtml( 'data-form-error' );
} );

it( 'says when a product can\'t be bought online', function (): void {
    $this->app->instance( ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry::class, new ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry() );

    expect( renderProductBlock( new AddToCartBlock(), [ 'productId' => $this->mug->id ] ) )->toContain( 'data-product-unavailable' );
} );

it( 'pages reviews by the block\'s setting and can hide the form', function (): void {
    foreach ( range( 1, 3 ) as $index ) {
        blockReview( $this->lamp, 5, 'Review ' . $index );
    }

    app( StorefrontContext::class )->setProduct( $this->lamp );

    $paged = renderProductBlock( new ProductReviewsBlock(), [ 'perPage' => 2 ] );

    expect( substr_count( $paged, 'data-review="' ) )->toBe( 2 )
        ->and( $paged )->toContain( 'data-review-cta' )
        ->and( renderProductBlock( new ProductReviewsBlock(), [ 'showForm' => false ] ) )->not->toContain( 'data-review-cta' );
} );

it( 'won\'t open or submit the review form when the block hides it', function (): void {
    Livewire::test( Reviews::class, [ 'product' => $this->lamp, 'allowForm' => false ] )
        ->call( 'openForm' )
        ->assertSet( 'showForm', false )
        ->set( 'rating', 5 )
        ->set( 'body', 'Lovely' )
        ->call( 'submit' )
        ->assertSet( 'submitted', false );

    expect( ProductReview::query()->count() )->toBe( 0 );
} );

it( 'renders nothing for a product block on a page without a product', function ( string $class ): void {
    expect( renderProductBlock( app( $class ) ) )->toBe( '' );
} )->with( [ SingleProductBlock::class, ProductGalleryBlock::class, ProductPriceBlock::class, AddToCartBlock::class, ProductReviewsBlock::class ] );
