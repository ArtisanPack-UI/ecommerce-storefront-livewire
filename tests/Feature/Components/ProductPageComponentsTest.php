<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\ProductImage;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;

describe( 'swatches', function (): void {
    it( 'renders native radios with colour, image, and text swatches', function (): void {
        $this->blade( '<x-artisanpack-ec-swatches name="colour" legend="Colour" :options="$options" />', [ 'options' => [
            [ 'value' => 'red', 'label' => 'Red', 'swatch' => '#ff0000', 'selected' => true ],
            [ 'value' => 'leaf', 'label' => 'Leaf', 'swatch' => 'https://cdn.example.test/leaf.png' ],
            [ 'value' => 'plain', 'label' => 'Plain', 'count' => 4 ],
            [ 'value' => 'gone', 'label' => 'Gone', 'disabled' => true, 'reason' => 'Out of stock' ],
        ] ] )
            ->assertSee( '<legend class="mb-2 text-sm font-semibold">Colour</legend>', false )
            ->assertSee( 'type="radio"', false )
            ->assertSee( 'background-color: #ff0000', false )
            ->assertSee( 'src="https://cdn.example.test/leaf.png"', false )
            ->assertSee( '(4)' )
            ->assertSee( 'title="Out of stock"', false )
            ->assertSee( 'checked', false )
            ->assertSee( 'disabled', false );
    } );

    it( 'uses checkboxes for multiple choices', function (): void {
        $this->blade( '<x-artisanpack-ec-swatches name="size" legend="Size" multiple :options="[ [ \'value\' => \'m\', \'label\' => \'M\' ] ]" />' )
            ->assertSee( 'type="checkbox"', false )
            ->assertSee( 'name="size[]"', false );
    } );

    it( 'drops unsafe swatches', function (): void {
        $this->blade( '<x-artisanpack-ec-swatches name="x" legend="X" :options="$options" />', [ 'options' => [
            [ 'value' => 'a', 'label' => 'A', 'swatch' => 'red; background:url(javascript:alert(1))' ],
            [ 'value' => 'b', 'label' => 'B', 'swatch' => 'javascript:alert(1)' ],
        ] ] )
            ->assertDontSee( 'javascript', false )
            ->assertDontSee( 'background-color', false );
    } );
} );

describe( 'quantity', function (): void {
    it( 'renders a labelled stepper and links its error', function (): void {
        $this->blade( '<x-artisanpack-ec-quantity id="qty" label="Quantity" item-name="Mug" error="Too many" />' )
            ->assertSee( '<label for="qty"', false )
            ->assertSee( 'aria-label="Decrease quantity of Mug"', false )
            ->assertSee( 'aria-label="Increase quantity of Mug"', false )
            ->assertSee( 'aria-describedby="qty-error"', false )
            ->assertSee( 'Too many' );
    } );
} );

describe( 'price range', function (): void {
    it( 'renders two labelled ranges in minor units', function (): void {
        $this->blade( '<x-artisanpack-ec-price-range :min="1000" :max="9000" currency="EUR" from-model="priceMin" to-model="priceMax" label="Price" />' )
            ->assertSee( 'Lowest price' )
            ->assertSee( 'Highest price' )
            ->assertSee( 'min="1000"', false )
            ->assertSee( 'max="9000"', false )
            ->assertSee( "entangle( 'priceMin' )", false );
    } );
} );

describe( 'gallery', function (): void {
    it( 'renders the images, thumbnails, zoom, and a lightbox', function (): void {
        $this->blade( '<x-artisanpack-ec-gallery :images="$images" name="Mug" />', [ 'images' => [
            [ 'url' => 'https://cdn.example.test/1.jpg', 'alt' => 'Front' ],
            [ 'url' => 'https://cdn.example.test/2.jpg', 'alt' => 'Back' ],
            [ 'url' => 'javascript:alert(1)', 'alt' => 'Bad' ],
        ] ] )
            ->assertSee( 'alt="Front"', false )
            ->assertSee( 'data-gallery-thumbnail="1"', false )
            ->assertDontSee( 'data-gallery-thumbnail="2"', false )
            ->assertDontSee( 'javascript:alert', false )
            ->assertSee( 'data-gallery-zoom', false )
            ->assertSee( '<dialog', false );
    } );

    it( 'shows a placeholder without images', function (): void {
        $this->blade( '<x-artisanpack-ec-gallery :images="[]" name="Mug" />' )
            ->assertSee( 'data-gallery="empty"', false )
            ->assertSee( 'No image available' );
    } );

    it( 'builds the gallery from the featured image and gallery rows without duplicates', function (): void {
        $product = makeProduct( 1000, [ 'name' => 'Mug', 'meta' => [ 'featured_image_url' => 'https://cdn.example.test/a.jpg' ] ] );
        ProductImage::factory()->create( [ 'product_id' => $product->id, 'media_id' => null, 'image_url' => 'https://cdn.example.test/a.jpg', 'position' => 0 ] );
        ProductImage::factory()->create( [ 'product_id' => $product->id, 'media_id' => null, 'image_url' => 'https://cdn.example.test/b.jpg', 'alt_text' => 'Side', 'position' => 1 ] );

        $images = ProductImages::gallery( $product );

        expect( array_column( $images, 'url' ) )->toBe( [ 'https://cdn.example.test/a.jpg', 'https://cdn.example.test/b.jpg' ] )
            ->and( $images[0]['alt'] )->toBe( 'Mug' )
            ->and( $images[1]['alt'] )->toBe( 'Side' )
            ->and( $images[1]['full'] )->toBe( 'https://cdn.example.test/b.jpg' );
    } );
} );
