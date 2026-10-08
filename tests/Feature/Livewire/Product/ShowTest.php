<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductAttributeValue;
use ArtisanPackUI\Ecommerce\Models\ProductImage;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\SimpleForm;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\VariableForm;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Show;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\SafeHtml;
use Livewire\Component;
use Livewire\Livewire;

afterEach( function (): void {
    removeAllActions( 'ap.ecommerce.product.viewed' );
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.product.sections' );
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.product.shippingReturns' );
} );

it( 'renders the gallery, summary, purchase form, and details', function (): void {
    $product = makeProduct( 2500, [
        'name'              => 'Linen shirt',
        'sku'               => 'LIN-1',
        'short_description' => '<p>Cool and <em>crisp</em>.</p>',
        'description'       => '<p>Woven in Portugal.</p>',
        'avg_rating'        => 4.0,
        'reviews_count'     => 3,
    ], 3000 );

    ProductImage::factory()->create( [ 'product_id' => $product->id, 'media_id' => null, 'image_url' => 'https://cdn.example.test/front.jpg', 'alt_text' => 'Front view', 'position' => 0 ] );
    ProductImage::factory()->create( [ 'product_id' => $product->id, 'media_id' => null, 'image_url' => 'https://cdn.example.test/back.jpg', 'alt_text' => 'Back view', 'position' => 1 ] );

    $attribute = ProductAttribute::factory()->create( [ 'product_id' => $product->id, 'key' => 'fabric', 'label' => 'Fabric', 'is_variation' => false ] );
    ProductAttributeValue::factory()->create( [ 'product_attribute_id' => $attribute->id, 'value' => 'linen', 'label' => 'Linen' ] );

    Livewire::test( Show::class, [ 'product' => $product ] )
        ->assertOk()
        ->assertSeeHtml( '<h1 id="ec-product-' . $product->id . '-name" class="text-3xl font-bold">Linen shirt</h1>' )
        ->assertSeeHtml( 'src="https://cdn.example.test/front.jpg"' )
        ->assertSeeHtml( 'alt="Back view"' )
        ->assertSeeHtml( 'data-gallery-thumbnail="1"' )
        ->assertSeeHtml( 'data-gallery-lightbox' )
        ->assertSeeHtml( 'href="#reviews"' )
        ->assertSee( '3 reviews' )
        ->assertSee( 'Sale price: was $30.00, now $25.00' )
        ->assertSeeHtml( '<em>crisp</em>' )
        ->assertSee( 'In stock' )
        ->assertSee( 'SKU: LIN-1' )
        ->assertSeeLivewire( SimpleForm::class )
        ->assertSee( 'Woven in Portugal.' )
        ->assertSee( 'Fabric' )
        ->assertSee( 'Linen' );
} );

it( 'shows an out-of-stock product with a disabled button', function (): void {
    $product = makeProduct( 2500, [ 'name' => 'Sold out mug' ] );
    setStock( $product, 0 );

    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) )
        ->assertOk()
        ->assertSee( 'Out of stock' )
        ->assertSee( 'This product is out of stock.' );
} );

it( 'fires the product viewed hook once with the signed-in customer', function (): void {
    $seen = [];

    addAction( 'ap.ecommerce.product.viewed', static function ( Product $product, $customer ) use ( &$seen ): void {
        $seen[] = [ $product->id, $customer?->id ];
    } );

    $product = makeProduct();

    Livewire::test( Show::class, [ 'product' => $product ] )->set( 'openSection', 'specifications' );

    expect( $seen )->toBe( [ [ $product->id, null ] ] );
} );

it( 'keeps rendering when a viewed listener fails', function (): void {
    addAction( 'ap.ecommerce.product.viewed', static function (): void {
        throw new RuntimeException( 'Listener down.' );
    } );

    Livewire::test( Show::class, [ 'product' => makeProduct() ] )->assertOk();
} );

it( 'is a 404 for a hidden product or one whose type is missing', function ( Closure $make ): void {
    Livewire::test( Show::class, [ 'product' => $make() ] )->assertNotFound();
} )->with( [
    'draft'        => fn (): Product => Product::factory()->draft()->create(),
    'missing type' => fn (): Product => Product::factory()->create( [ 'type' => 'uninstalled-satellite-type' ] ),
] );

it( 'says when a product type has no purchase form', function (): void {
    $registry = new ProductFormRegistry();
    $this->app->instance( ProductFormRegistry::class, $registry );

    Livewire::test( Show::class, [ 'product' => makeProduct() ] )
        ->assertSee( 'This product can\'t be purchased online' )
        ->assertSeeHtml( 'data-product-unavailable' );
} );

it( 'follows the variant the purchase form matches', function (): void {
    $made = makeVariableProduct( [
        'red/m'  => [ 'price' => 2000, 'stock' => 4 ],
        'blue/l' => [ 'price' => 2600, 'stock' => 0 ],
    ] );

    $component = Livewire::test( Show::class, [ 'product' => $made['product'] ] )
        ->assertSeeLivewire( VariableForm::class )
        ->assertSee( '$20.00' )
        ->assertSee( '$26.00' );

    $component->call( 'variantSelected', $made['product']->id, $made['variants']['blue/l']->id )
        ->assertSet( 'variantId', $made['variants']['blue/l']->id )
        ->assertSee( 'SKU: SHIRT-BLUE-L' )
        ->assertSee( 'Out of stock' )
        ->assertDontSee( '$20.00' );

    // Another product's event, or someone else's variant, is ignored.
    $component->call( 'variantSelected', $made['product']->id + 99, $made['variants']['red/m']->id )
        ->assertSet( 'variantId', $made['variants']['blue/l']->id )
        ->call( 'variantSelected', $made['product']->id, makeVariableProduct( [ 'red/m' => [ 'price' => 1 ] ], [], 'OTHER' )['variants']['red/m']->id )
        ->assertSet( 'variantId', null );
} );

it( 'opens on the variant from the query string', function (): void {
    $made = makeVariableProduct( [ 'red/m' => [ 'price' => 2000 ], 'blue/l' => [ 'price' => 2600 ] ] );

    Livewire::withQueryParams( [ 'variant' => $made['variants']['blue/l']->id ] )
        ->test( Show::class, [ 'product' => $made['product'] ] )
        ->assertSet( 'variantId', $made['variants']['blue/l']->id )
        ->assertSee( 'SKU: SHIRT-BLUE-L' );
} );

it( 'renders extra sections from the filter, in position order', function (): void {
    Livewire::component( 'test-product-section-a', new class extends Component {
        public Product $product;

        public string $label = '';

        public function render(): string
        {
            return '<div>Section {{ $label }} for {{ $product->name }}</div>';
        }
    } );

    addFilter( 'ap.ecommerceStorefrontLivewire.product.sections', static fn ( array $sections ): array => [
        ...$sections,
        'second' => [ 'component' => 'test-product-section-a', 'position' => 20, 'params' => [ 'label' => 'B' ] ],
        'first'  => [ 'component' => 'test-product-section-a', 'position' => 10, 'params' => [ 'label' => 'A' ] ],
        'broken' => [ 'position' => 5 ],
    ] );

    $html = Livewire::test( Show::class, [ 'product' => makeProduct( 1000, [ 'name' => 'Mug' ] ) ] )->html();

    expect( $html )->toContain( 'data-product-section="first"' )
        ->and( strpos( $html, 'data-product-section="first"' ) )->toBeLessThan( strpos( $html, 'data-product-section="second"' ) )
        ->and( $html )->not->toContain( 'data-product-section="broken"' );
} );

it( 'shows shipping and returns content from the filter, made safe', function (): void {
    addFilter( 'ap.ecommerceStorefrontLivewire.product.shippingReturns', static fn (): string => '<p>Free returns for <a href="javascript:alert(1)" onclick="x()">30 days</a>.</p><script>alert(1)</script>' );

    Livewire::test( Show::class, [ 'product' => makeProduct() ] )
        ->assertSee( 'Shipping & returns' )
        ->assertSee( 'Free returns for' )
        ->assertDontSeeHtml( '<script>' )
        ->assertDontSeeHtml( 'onclick' )
        ->assertDontSeeHtml( 'href="javascript:' );
} );

it( 'cleans descriptions before printing them', function (): void {
    expect( SafeHtml::clean( '<p style="color:red" onmouseover="x()">Hi <img src=x onerror=alert(1)></p><script>alert(1)</script><form><input></form>' ) )
        ->not->toContain( '<script' )
        ->not->toContain( 'onmouseover' )
        ->not->toContain( 'onerror' )
        ->not->toContain( 'style=' )
        ->not->toContain( '<form' )
        ->toContain( 'Hi' )
        ->and( SafeHtml::clean( '   ' ) )->toBeNull()
        ->and( SafeHtml::clean( null ) )->toBeNull();
} );

it( 'escapes the product name and SKU', function (): void {
    Livewire::test( Show::class, [ 'product' => makeProduct( 1000, [ 'name' => '<b>Bold</b> mug', 'sku' => '<i>X</i>' ] ) ] )
        ->assertDontSeeHtml( '<b>Bold</b>' )
        ->assertDontSeeHtml( '<i>X</i>' );
} );

it( 'renders the page route with the component', function (): void {
    $product = makeProduct( 1000, [ 'name' => 'Mug', 'slug' => 'mug' ] );

    $this->get( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => 'mug' ] ) )
        ->assertOk()
        ->assertSeeLivewire( Show::class )
        ->assertDontSeeHtml( 'data-screen-pending' );
} );
