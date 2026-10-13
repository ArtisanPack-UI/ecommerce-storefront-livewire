<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductRelation;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\RelatedProducts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Show;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerce.product.related' );
    removeAllFilters( 'ap.ecommerce.cart.crossSells' );
} );

/**
 * Links `$related` to `$product` as `$type`, in order.
 *
 * @param  array<int, Product>  $related  Products to link.
 */
function relate( Product $product, string $type, array $related ): void
{
    foreach ( $related as $position => $other ) {
        ProductRelation::query()->create( [ 'product_id' => $product->id, 'related_product_id' => $other->id, 'type' => $type, 'position' => $position ] );
    }
}

it( 'shows a product\'s upsells as cards, in order', function (): void {
    $phone = makeProduct( 50000, [ 'name' => 'Phone' ] );
    $pro   = makeProduct( 90000, [ 'name' => 'Phone Pro' ] );
    $max   = makeProduct( 99000, [ 'name' => 'Phone Max' ] );
    $draft = Product::factory()->draft()->create( [ 'name' => 'Hidden prototype' ] );

    relate( $phone, ProductRelation::UPSELL, [ $max, $draft, $pro ] );

    $html = Livewire::withoutLazyLoading()
        ->test( RelatedProducts::class, [ 'product' => $phone, 'type' => 'upsell' ] )
        ->assertOk()
        ->assertSee( 'You may also like' )
        ->assertSeeHtml( 'data-product-card="' . $max->id . '"' )
        ->assertSeeHtml( 'data-product-card="' . $pro->id . '"' )
        ->assertDontSee( 'Hidden prototype' )
        ->assertSeeHtml( 'aria-label="Next products"' )
        ->assertSeeHtml( 'aria-label="Previous products"' )
        ->html();

    expect( strpos( $html, 'Phone Max' ) )->toBeLessThan( strpos( $html, 'Phone Pro' ) );
} );

it( 'fills related products from shared categories', function (): void {
    $category = ProductCategory::factory()->create();
    $mug      = makeProduct( 1000, [ 'name' => 'Mug' ] );
    $cup      = makeProduct( 1000, [ 'name' => 'Cup' ] );
    makeProduct( 1000, [ 'name' => 'Unrelated lamp' ] );

    $mug->categories()->attach( $category );
    $cup->categories()->attach( $category );

    Livewire::withoutLazyLoading()
        ->test( RelatedProducts::class, [ 'product' => $mug ] )
        ->assertSee( 'Related products' )
        ->assertSee( 'Cup' )
        ->assertDontSee( 'Unrelated lamp' );
} );

it( 'renders nothing when there is nothing to suggest', function (): void {
    Livewire::withoutLazyLoading()
        ->test( RelatedProducts::class, [ 'product' => makeProduct(), 'type' => 'upsell' ] )
        ->assertOk()
        ->assertDontSeeHtml( '<section' )
        ->assertDontSee( 'You may also like' );
} );

it( 'limits the number of suggestions', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.related.limit', 2 );

    $product = makeProduct();
    relate( $product, ProductRelation::UPSELL, [ makeProduct(), makeProduct(), makeProduct() ] );

    $html = Livewire::withoutLazyLoading()->test( RelatedProducts::class, [ 'product' => $product, 'type' => 'upsell' ] )->html();

    expect( substr_count( $html, 'data-product-card=' ) )->toBe( 2 );
} );

it( 'falls back to related for an unknown type and accepts a custom heading', function (): void {
    $product = makeProduct();
    relate( $product, ProductRelation::RELATED, [ makeProduct( 1000, [ 'name' => 'Saucer' ] ) ] );

    Livewire::withoutLazyLoading()
        ->test( RelatedProducts::class, [ 'product' => $product, 'type' => 'nonsense', 'heading' => 'More mugs' ] )
        ->assertSet( 'type', ProductRelation::RELATED )
        ->assertSee( 'More mugs' )
        ->assertSee( 'Saucer' )
        ->assertDontSee( 'Related products' );
} );

it( 'shows skeleton cards until the section loads', function (): void {
    $component = new RelatedProducts();

    $html = $component->placeholder( [ 'type' => 'upsell' ] )->render();

    expect( $html )->toContain( 'data-skeleton="card"' )
        ->toContain( 'You may also like' )
        ->toContain( 'Loading suggestions…' );
} );

it( 'loads lazily on the product page, with upsells before related products', function (): void {
    $product = makeProduct();

    $html = Livewire::test( Show::class, [ 'product' => $product ] )
        ->assertSeeHtml( 'data-product-section="upsells"' )
        ->assertSeeHtml( 'data-product-section="related"' )
        ->html();

    expect( strpos( $html, 'data-product-section="reviews"' ) )->toBeLessThan( strpos( $html, 'data-product-section="upsells"' ) )
        ->and( strpos( $html, 'data-product-section="upsells"' ) )->toBeLessThan( strpos( $html, 'data-product-section="related"' ) );
} );

it( 'shows cross-sells for the cart\'s lines, without what is already in it', function (): void {
    $phone = makeProduct( 50000, [ 'name' => 'Phone' ] );
    $case  = makeProduct( 2000, [ 'name' => 'Phone case' ] );
    $cable = makeProduct( 1500, [ 'name' => 'Cable' ] );

    relate( $phone, ProductRelation::CROSS_SELL, [ $case, $cable ] );

    $cart = app( StorefrontCart::class )->current( true );
    app( StorefrontCartService::class )->addItem( $cart, $phone->id, null, 1 );
    app( StorefrontCartService::class )->addItem( $cart, $cable->id, null, 1 );

    Livewire::withoutLazyLoading()
        ->test( RelatedProducts::class, [ 'type' => 'cross_sell' ] )
        ->assertSee( 'Complete your order' )
        ->assertSee( 'Phone case' )
        ->assertDontSeeHtml( 'data-product-card="' . $cable->id . '"' );
} );

it( 'shows no cross-sells without a cart, and follows cart updates', function (): void {
    $component = Livewire::withoutLazyLoading()->test( RelatedProducts::class, [ 'type' => 'cross_sell' ] )
        ->assertDontSeeHtml( '<section' );

    expect( $component->instance()->getListeners() )->toBe( [ 'ecommerce-cart-updated' => '$refresh' ] )
        ->and( ( new RelatedProducts() )->getListeners() )->toBe( [] );
} );

it( 'quick-adds a suggested product to the cart', function (): void {
    $product = makeProduct();
    $case    = makeProduct( 2000, [ 'name' => 'Case' ] );
    relate( $product, ProductRelation::UPSELL, [ $case ] );

    Livewire::withoutLazyLoading()
        ->test( RelatedProducts::class, [ 'product' => $product, 'type' => 'upsell' ] )
        ->assertSeeHtml( 'data-quick-add="' . $case->id . '"' )
        ->call( 'quickAdd', $case->id )
        ->assertDispatched( 'ecommerce-cart-updated', count: 1 );
} );

it( 'hides the section when the engine or a listener fails', function (): void {
    $product = makeProduct();
    relate( $product, ProductRelation::UPSELL, [ makeProduct( 1000, [ 'name' => 'Case' ] ) ] );

    addFilter( 'ap.ecommerce.product.related', static function (): Collection {
        throw new RuntimeException( 'Recommendations down.' );
    } );

    Livewire::withoutLazyLoading()
        ->test( RelatedProducts::class, [ 'product' => $product, 'type' => 'upsell' ] )
        ->assertOk()
        ->assertDontSee( 'Case' );
} );

it( 'keeps its props out of the browser\'s reach', function ( string $property, mixed $value ): void {
    expect( fn () => Livewire::withoutLazyLoading()->test( RelatedProducts::class, [ 'product' => makeProduct() ] )->set( $property, $value ) )
        ->toThrow( CannotUpdateLockedPropertyException::class );
} )->with( [
    'type'  => [ 'type', 'upsell' ],
    'limit' => [ 'limit', 12 ],
] );
