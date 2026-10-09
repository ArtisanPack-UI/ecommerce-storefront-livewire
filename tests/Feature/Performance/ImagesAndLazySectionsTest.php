<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\RecentlyViewed;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\Fixtures\Media\FakeMedia;

beforeEach( function (): void {
    require_once dirname( __DIR__, 2 ) . '/Fixtures/Media/MediaLibraryStub.php';

    ProductImages::fake( true );
    config( [
        'artisanpack.media.image_sizes.medium.width' => 600,
        'artisanpack.media.image_sizes.large.width'  => 1200,
    ] );
} );

afterEach( function (): void {
    FakeMedia::$items = [];
    removeAllFilters( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds' );
} );

it( 'builds a srcset from the media library\'s conversions with the rendered size', function (): void {
    FakeMedia::register( 7, new FakeMedia( 2400, 1800, 'A kettle' ) );
    $product = makeProduct( 1000, [ 'featured_image_media_id' => 7 ] );

    expect( ProductImages::card( $product ) )->toMatchArray( [
        'url'    => 'https://media.example.test/medium.jpg',
        'srcset' => 'https://media.example.test/medium.jpg 600w, https://media.example.test/large.jpg 1200w',
        'alt'    => 'A kettle',
        'width'  => 600,
        'height' => 450,
    ] );
} );

it( 'sizes an image by its box when its own size is unknown', function (): void {
    expect( ProductImages::dimensions( null, 600, 600 ) )->toBe( [ 'width' => 600, 'height' => 600 ] )
        ->and( ProductImages::dimensions( [ 'url' => 'x', 'width' => null, 'height' => null ], 800, 600 ) )->toBe( [ 'width' => 800, 'height' => 600 ] )
        ->and( ProductImages::dimensions( [ 'url' => 'x', 'width' => 300, 'height' => 200 ], 800, 600 ) )->toBe( [ 'width' => 300, 'height' => 200 ] );
} );

it( 'keeps an original smaller than the conversion at its own size', function (): void {
    FakeMedia::register( 8, new FakeMedia( 400, 300 ) );

    expect( ProductImages::card( makeProduct( 1000, [ 'featured_image_media_id' => 8 ] ) ) )->toMatchArray( [ 'width' => 400, 'height' => 300 ] );
} );

it( 'reserves each product card image\'s space with width and height', function (): void {
    FakeMedia::register( 9, new FakeMedia( 2000, 2000 ) );
    $product = makeProduct( 1000, [ 'featured_image_media_id' => 9 ] );

    $html = Blade::render( '<x-artisanpack-ec-sf-product-card :product="$product" />', [ 'product' => $product ] );

    expect( $html )->toContain( 'width="600"' )
        ->toContain( 'height="600"' )
        ->toContain( 'srcset="https://media.example.test/medium.jpg 600w, https://media.example.test/large.jpg 1200w"' )
        ->toContain( 'sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw"' );
} );

it( 'gives cart thumbnails a small sizes hint and a fixed size', function (): void {
    FakeMedia::register( 10, new FakeMedia( 1200, 1200 ) );
    checkoutCart( [ makeProduct( 1000, [ 'featured_image_media_id' => 10 ] ) ] );

    Livewire::test( ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Index::class )
        ->assertSeeHtml( 'srcset="https://media.example.test/medium.jpg 600w, https://media.example.test/large.jpg 1200w" sizes="96px"' )
        ->assertSeeHtml( 'width="96" height="96"' );
} );

it( 'loads recently viewed products after the page, with skeletons until then', function (): void {
    $kettle = makeProduct( 1000, [ 'name' => 'Kettle' ] );
    addFilter( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds', static fn (): array => [ $kettle->id ] );

    $html = Blade::render( '<livewire:artisanpack-ecommerce-storefront-recently-viewed heading="Seen lately" :limit="3" />' );

    expect( $html )->toContain( 'data-related-placeholder' )
        ->toContain( 'Seen lately' )
        ->not->toContain( 'Kettle' );

    Livewire::withoutLazyLoading()->test( RecentlyViewed::class, [ 'limit' => 3 ] )->assertSee( 'Kettle' );
} );
