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
            ->assertSee( '<legend class="ec-swatches__legend">Colour<span class="ec-swatches__chosen" data-swatches-chosen>: Red</span></legend>', false )
            ->assertSee( 'class="ec-swatch"', false )
            ->assertSee( '<span class="ec-swatch__label">Red</span>', false )
            ->assertSee( '<span class="apui-sr-only">(Out of stock)</span>', false )
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
            ->assertSee( 'name="size[]"', false )
            ->assertDontSee( 'data-swatches-chosen', false );
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
        $this->blade( '<x-artisanpack-ec-quantity id="qty" label="Quantity" item-name="Mug" error="Only 3 available" />' )
            ->assertSee( 'class="apui-input', false )
            ->assertSee( 'for="qty"', false )
            ->assertSee( 'aria-label="Decrease quantity, Mug"', false )
            ->assertSee( 'aria-label="Increase quantity, Mug"', false )
            ->assertSee( 'aria-describedby="qty-error"', false )
            ->assertSee( 'aria-invalid="true"', false )
            ->assertSee( 'Only 3 available' );
    } );

    it( 'hides a missing label and takes its size from the context', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-quantity id="qty" size="xs" />' );

        expect( $html )->toContain( 'class="apui-sr-only"' )
            ->and( $html )->toContain( 'data-size="xs"' )
            ->and( $html )->toContain( 'data-size="sm"' );

        expect( (string) $this->blade( '<x-artisanpack-ec-quantity id="qty" size="huge" />' ) )->toContain( 'data-size="md"' )->not->toContain( 'data-size="huge"' );
    } );

    it( 'marks the buttons aria-disabled at the limits and leaves them focusable', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-quantity id="qty" :min="1" :max="3" />' );

        expect( $html )->toContain( 'x-bind:aria-disabled="atMin().toString()"' )
            ->and( $html )->toContain( 'x-bind:aria-disabled="atMax().toString()"' )
            ->and( $html )->toContain( 'this.value &gt;= 3' )
            ->and( $html )->toContain( 'min="1"' )
            ->and( $html )->toContain( 'max="3"' );

        expect( (string) $this->blade( '<x-artisanpack-ec-quantity id="qty" disabled />' ) )->toContain( 'disabled' );
    } );

    it( 're-reads the limits when the server changes the bound value', function (): void {
        expect( (string) $this->blade( '<x-artisanpack-ec-quantity id="qty" wire:model.live="quantities.5" />' ) )
            ->toContain( '$wire.$watch( &#039;quantities.5&#039;' );

        expect( (string) $this->blade( '<x-artisanpack-ec-quantity id="qty" />' ) )->not->toContain( '$wire.$watch' );
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
            ->assertSee( 'aria-label="Zoom image: Front"', false )
            ->assertSee( 'data-gallery-announcement', false )
            ->assertSee( 'aria-label="Image 2 of 2: Back"', false )
            ->assertSee( 'aria-current="true"', false )
            ->assertSee( 'data-gallery-counter', false )
            ->assertSee( 'apuiLightbox(', false )
            ->assertSee( 'class="apui-lightbox"', false )
            ->assertSee( 'x-apui:content', false );
    } );

    it( 'leaves out the zoom and lightbox when zoom is off, and the counter for one image', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-gallery :images="[ [ \'url\' => \'https://cdn.example.test/1.jpg\', \'alt\' => \'Front\' ] ]" name="Mug" :zoom="false" />' );

        expect( $html )->not->toContain( 'data-gallery-zoom' )
            ->and( $html )->not->toContain( 'apuiLightbox' )
            ->and( $html )->not->toContain( 'data-gallery-counter' )
            ->and( $html )->not->toContain( 'data-gallery-thumbnail="' );
    } );

    it( 'gives two galleries of the same images their own ids', function (): void {
        $html = (string) $this->blade( '<div><x-artisanpack-ec-gallery :images="$images" name="Mug" /><x-artisanpack-ec-gallery :images="$images" name="Mug" /></div>', [ 'images' => [
            [ 'url' => 'https://cdn.example.test/1.jpg' ],
            [ 'url' => 'https://cdn.example.test/2.jpg' ],
        ] ] );

        preg_match_all( '/<div[^>]+id="(ec-gallery-[a-f0-9]+(?:-\d+)?)"[^>]*data-gallery\b/s', $html, $matches );

        expect( $matches[1] )->toHaveCount( 2 )
            ->and( $matches[1][0] )->not->toBe( $matches[1][1] );
    } );

    it( 'names the lightbox and its thumbnails', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-gallery :images="$images" name="Mug" />', [ 'images' => [
            [ 'url' => 'https://cdn.example.test/1.jpg' ],
            [ 'url' => 'https://cdn.example.test/2.jpg' ],
        ] ] );

        expect( $html )->toContain( '1 / 2 · Mug' )
            ->and( $html )->toContain( '\u0022caption\u0022:\u0022Mug\u0022' )
            ->and( $html )->not->toContain( 'style="display: contents"' )
            ->and( substr_count( $html, 'aria-label="Image 2 of 2: Mug"' ) )->toBe( 2 )
            ->and( $html )->toMatch( '/aria-label="Image 2 of 2: Mug"\s+x-apui:thumb/' );
    } );

    it( 'keeps its part ids stable across renders', function (): void {
        $render = fn (): string => (string) $this->blade( '<x-artisanpack-ec-gallery :images="[ [ \'url\' => \'https://cdn.example.test/1.jpg\' ] ]" name="Mug" />' );

        preg_match( '/id="(ec-gallery-[a-f0-9]+)"/', $render(), $first );
        app( ArtisanPack\LivewireUiComponents\Support\RenderedIds::class )->reset( '' );
        preg_match( '/id="(ec-gallery-[a-f0-9]+)"/', $render(), $second );

        expect( $first[1] ?? null )->not->toBeNull()->toBe( $second[1] ?? null );
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
