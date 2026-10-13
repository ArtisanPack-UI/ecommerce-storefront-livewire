<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\AddToCartBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\ProductGridBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\ProductPriceBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlock;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlocks;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontContext;
use Illuminate\Support\Facades\Route;

/**
 * Registers a stand-in for visual-editor's preview route that renders
 * `$block` the way the editor's preview endpoint does.
 */
function fakePreviewRoute( StorefrontBlock $block ): void
{
    Route::post( '_test/blocks/preview', static function () use ( $block ): string {
        $attrs = $block->validateAttrs( (array) request()->input( 'attributes', [] ) );

        abort_unless( $block->authorize( auth()->user(), $attrs ), 403 );

        return $block->render( $attrs );
    } )->name( StorefrontBlock::PREVIEW_ROUTE );

    app( 'router' )->getRoutes()->refreshNameLookups();
}

it( 'holds the page\'s product, category, and order per request', function (): void {
    $product  = makeProduct( 1000, [ 'name' => 'Lamp', 'slug' => 'lamp' ] );
    $category = ProductCategory::factory()->create( [ 'slug' => 'lighting' ] );
    CategoryTree::flush();

    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'lamp' ] ) )->assertOk();

    expect( app( StorefrontContext::class )->product()?->is( $product ) )->toBeTrue();

    $this->get( route( 'artisanpack.ecommerce.storefront.category', [ 'path' => 'lighting' ] ) )->assertOk();

    expect( app( StorefrontContext::class )->category()?->is( $category ) )->toBeTrue();

    $order = placedOrder();
    $token = ArtisanPackUI\Ecommerce\Support\OrderViewToken::for( $order );

    $this->get( route( 'artisanpack.ecommerce.storefront.order-view', [ 'token' => $token ] ) )->assertOk();

    expect( app( StorefrontContext::class )->order()?->is( $order ) )->toBeTrue();
} );

it( 'starts every request with an empty context', function (): void {
    app( StorefrontContext::class )->setProduct( makeProduct() );

    app()->forgetScopedInstances();

    expect( app( StorefrontContext::class )->product() )->toBeNull();
} );

it( 'clamps attributes to their schema and drops unknown ones', function (): void {
    $block = new ProductGridBlock();

    expect( $block->validateAttrs( [
        'source'     => 'everything',
        'sort'       => 'random',
        'limit'      => 999,
        'columns'    => '0',
        'showPrice'  => 'false',
        'showRating' => [ 'nope' ],
        'heading'    => "  Big\x00 sale  ",
        'tag'        => [ 'array' ],
        'onclick'    => 'alert(1)',
    ] ) )->toBe( [
        'source'        => 'newest',
        'category'      => '',
        'tag'           => '',
        'ids'           => '',
        'sort'          => 'newest',
        'limit'         => 24,
        'columns'       => 1,
        'showPrice'     => false,
        'showRating'    => true,
        'showAddToCart' => true,
        'heading'       => 'Big  sale',
    ] );
} );

it( 'clamps strings, numbers, and the button text', function (): void {
    $attrs = ( new AddToCartBlock() )->validateAttrs( [ 'productId' => '-4', 'buttonText' => str_repeat( 'x', 300 ), 'showQuantity' => 0 ] );

    expect( $attrs['productId'] )->toBe( 0 )
        ->and( mb_strlen( $attrs['buttonText'] ) )->toBe( 60 )
        ->and( $attrs['showQuantity'] )->toBeFalse()
        ->and( ( new ProductGridBlock() )->validateAttrs( [ 'limit' => 4.5, 'columns' => '3' ] ) )->toMatchArray( [ 'limit' => 8, 'columns' => 3 ] );
} );

it( 'lets signed-in editors preview and nobody else', function (): void {
    $block = new ProductPriceBlock();

    expect( $block->authorize( null, [] ) )->toBeFalse()
        ->and( $block->authorize( makeUser(), [] ) )->toBeTrue();

    fakePreviewRoute( $block );

    $this->postJson( '_test/blocks/preview', [ 'attributes' => [] ] )->assertForbidden();
} );

it( 'escapes attribute output', function (): void {
    makeProduct( 1000, [ 'name' => 'Lamp' ] );

    $html = ( new ProductGridBlock() )->render( ( new ProductGridBlock() )->validateAttrs( [ 'heading' => '<script>alert(1)</script>' ] ) );

    expect( $html )->not->toContain( '<script>alert(1)</script>' )
        ->toContain( '&lt;script&gt;alert(1)&lt;/script&gt;' );
} );

it( 'reads the product from the page context', function (): void {
    $lamp = makeProduct( 2500, [ 'name' => 'Lamp' ] );
    makeProduct( 900, [ 'name' => 'Newer mug' ] );

    app( StorefrontContext::class )->setProduct( $lamp );

    expect( ( new ProductPriceBlock() )->render( [ 'productId' => 0 ] ) )->toContain( '$25.00' )->not->toContain( 'data-commerce-sample' );
} );

it( 'prefers a chosen, storefront-visible productId', function (): void {
    $lamp  = makeProduct( 2500, [ 'name' => 'Lamp' ] );
    $mug   = makeProduct( 900, [ 'name' => 'Mug' ] );
    $draft = Product::factory()->draft()->create();

    app( StorefrontContext::class )->setProduct( $lamp );

    expect( ( new ProductPriceBlock() )->render( [ 'productId' => $mug->id ] ) )->toContain( '$9.00' )
        ->and( ( new ProductPriceBlock() )->render( [ 'productId' => $draft->id ] ) )->toContain( '$25.00' );
} );

it( 'renders nothing on the site without a product', function (): void {
    makeProduct( 900, [ 'name' => 'Mug' ] );

    expect( ( new ProductPriceBlock() )->render( [ 'productId' => 0 ] ) )->toBe( '' );
} );

it( 'previews the newest product as a labelled sample', function (): void {
    $this->travel( -1 )->days();
    makeProduct( 2500, [ 'name' => 'Old lamp' ] );
    $this->travelBack();
    makeProduct( 900, [ 'name' => 'New mug' ] );

    fakePreviewRoute( new ProductPriceBlock() );

    $this->actingAs( makeUser() )
        ->postJson( '_test/blocks/preview', [ 'attributes' => [] ] )
        ->assertOk()
        ->assertSee( 'data-commerce-sample', false )
        ->assertSee( 'Sample product' )
        ->assertSee( '$9.00' );
} );

it( 'previews the product a preview request names through the products resource', function (): void {
    $lamp = makeProduct( 2500, [ 'name' => 'Lamp' ] );
    makeProduct( 900, [ 'name' => 'Newer mug' ] );

    fakePreviewRoute( new ProductPriceBlock() );

    $this->actingAs( makeUser() )
        ->postJson( '_test/blocks/preview', [ 'attributes' => [], 'context' => [ 'resource' => 'products', 'id' => $lamp->id ] ] )
        ->assertOk()
        ->assertSee( '$25.00' )
        ->assertDontSee( 'Sample product' );
} );

it( 'shows editors a notice when there is nothing to preview', function (): void {
    fakePreviewRoute( new ProductPriceBlock() );

    $this->actingAs( makeUser() )
        ->postJson( '_test/blocks/preview', [ 'attributes' => [] ] )
        ->assertOk()
        ->assertSee( 'data-commerce-block-notice', false )
        ->assertSee( 'There are no products to show yet.' );
} );

it( 'reports an engine failure and renders nothing on the site, a notice in the editor', function (): void {
    $lamp = makeProduct( 2500, [ 'name' => 'Lamp' ] );

    $this->mock( ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver::class )
        ->shouldReceive( 'for' )->andThrow( new RuntimeException( 'Pricing is down.' ) );

    expect( ( new ProductPriceBlock() )->render( [ 'productId' => $lamp->id ] ) )->toBe( '' );

    fakePreviewRoute( new ProductPriceBlock() );

    $this->actingAs( makeUser() )
        ->postJson( '_test/blocks/preview', [ 'attributes' => [ 'productId' => $lamp->id ] ] )
        ->assertOk()
        ->assertSee( 'This block couldn' );
} );

it( 'names every block under the commerce namespace with a description and icon', function ( string $class ): void {
    $block = app( $class );

    expect( $block->name() )->toStartWith( 'artisanpack-commerce/' )
        ->and( $block->metadata() )->toHaveKeys( [ 'title', 'description', 'category', 'icon', 'keywords', 'attributes' ] )
        ->and( $block->validateAttrs( [] ) )->toHaveKeys( array_keys( $block->attributes() ) );
} )->with( StorefrontBlocks::BLOCKS );
