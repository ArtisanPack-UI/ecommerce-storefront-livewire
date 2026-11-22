<?php

declare( strict_types=1 );

use ArtisanPack\LivewireUiComponents\Support\RenderedIds;
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

    it( 'marks a required group and links its error to the group', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-swatches name="size" legend="Size" id-prefix="ec-size" required error="Choose a size to add this to your cart." aria-describedby="other-error" :options="[ [ \'value\' => \'m\', \'label\' => \'M\' ] ]" />' );

        expect( $html )->toContain( '<legend class="ec-swatches__legend">Size<span class="ec-swatches__required" aria-hidden="true">*</span></legend>' )
            ->toContain( 'aria-describedby="other-error ec-size-error"' )
            ->toContain( 'data-invalid' )
            ->toContain( 'required' )
            ->toContain( 'aria-invalid="true"' )
            ->toContain( '<p id="ec-size-error" class="ec-swatches__error" data-swatches-error>' )
            ->toContain( 'Choose a size to add this to your cart.' );
    } );

    it( 'shows no error or star by default', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-swatches name="size" legend="Size" error=" " :options="[ [ \'value\' => \'m\', \'label\' => \'M\' ] ]" />' );

        expect( $html )->not->toContain( 'ec-swatches__required' )
            ->not->toContain( 'data-swatches-error' )
            ->not->toContain( 'aria-invalid' )
            ->not->toContain( 'aria-describedby' );
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
            ->toContain( '$wire.$watch( &#039;quantities.5&#039;' )
            ->toContain( 'this.$refs.input.valueAsNumber' )
            ->not->toContain( 'parseInt' );

        expect( (string) $this->blade( '<x-artisanpack-ec-quantity id="qty" />' ) )->not->toContain( '$wire.$watch' );
    } );
} );

describe( 'price range', function (): void {
    it( 'renders the two-thumb design-system Range in minor units, named by the legend', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-price-range :min="1000" :max="9000" currency="EUR" from-model="priceMin" to-model="priceMax" label="Price" id-prefix="filters-price" />' );

        expect( $html )
            ->toContain( '<legend id="filters-price-legend" class="ec-price-range__legend">Price</legend>' )
            ->toContain( 'class="apui-range ec-price-range__slider"' )
            ->toContain( 'data-dual' )
            ->toContain( 'data-show-value="never"' )
            ->toContain( 'aria-labelledby="filters-price-legend filters-price-range-start-label"' )
            ->toContain( 'aria-labelledby="filters-price-legend filters-price-range-end-label"' )
            ->toContain( '<span class="apui-sr-only" id="filters-price-range-start-label">Lowest price</span>' )
            ->toContain( '<span class="apui-sr-only" id="filters-price-range-end-label">Highest price</span>' )
            ->toContain( 'x-bind:aria-valuetext="money(' )
            ->toContain( "entangle( 'priceMin' )" )
            ->toContain( "entangle( 'priceMax' )" )
            ->and( substr_count( $html, 'min="1000"' ) )->toBe( 2 )
            ->and( substr_count( $html, 'max="9000"' ) )->toBe( 2 );
    } );

    it( 'pairs the thumbs with a prefixed input for each bound in major units, "to" between them', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-price-range :min="1050" :max="9000" currency="USD" from-model="priceMin" to-model="priceMax" label="Price" id-prefix="p" />' );

        expect( $html )
            ->toMatch( '/<span data-scope="input" data-part="prefix">\$<\/span>\s*<input[^>]*aria-label="Lowest price"[^>]*x-bind:value="major\( low\(\) \)"[^>]*data-price-min/' )
            ->toMatch( '/<span data-scope="input" data-part="prefix">\$<\/span>\s*<input[^>]*aria-label="Highest price"[^>]*x-bind:value="major\( high\(\) \)"[^>]*data-price-max/' )
            ->toContain( 'min="10.5" max="90" step="1"' )
            ->toMatch( '/data-price-min.*<span class="ec-price-range__to" aria-hidden="true">to<\/span>.*data-price-max/s' );
    } );

    it( 'uses the currency\'s own symbol and minor units', function ( string $currency, string $prefix, string $bounds ): void {
        expect( (string) $this->blade( '<x-artisanpack-ec-price-range :min="0" :max="5000" :currency="$currency" from-model="a" to-model="b" />', [ 'currency' => $currency ] ) )
            ->toContain( '<span data-scope="input" data-part="prefix">' . $prefix . '</span>' )
            ->toContain( $bounds );
    } )->with( [
        'euro'  => [ 'EUR', '€', 'divisor: 100, decimals: 2' ],
        'yen'   => [ 'JPY', '¥', 'divisor: 1, decimals: 0' ],
        'dinar' => [ 'KWD', 'KWD', 'divisor: 1000, decimals: 3' ],
    ] );

    it( 'places the thumbs and inputs at the current bounds for the first render', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-price-range :min="1000" :max="9000" currency="USD" from-model="a" to-model="b" :from="2500" :to="20000" />' );

        expect( $html )
            ->toContain( 'value: JSON.parse(\'[2500,9000]\')' )
            ->toContain( 'value="2500"' )
            ->toContain( 'value="9000"' )
            ->toMatch( '/data-price-min[^>]*value="25\.00"/' )
            ->toMatch( '/data-price-max[^>]*value="90\.00"/' );
    } );

    it( 'leaves no DaisyUI or Tailwind classes in the price range', function (): void {
        expect( classTokens( (string) $this->blade( '<x-artisanpack-ec-price-range :min="0" :max="9000" currency="EUR" from-model="priceMin" to-model="priceMax" label="Price" />' ) ) )
            ->not->toContain( 'range', 'range-sm', 'flex', 'flex-col', 'gap-3', 'text-xs', 'text-sm', 'tabular-nums' );
    } );
} );

describe( 'rating input', function (): void {
    it( 'is a radiogroup named by its legend, with five radios from "1 star" to "5 stars"', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-rating-input id="rate" label="Your rating" wire:model="rating" required />' );

        expect( $html )
            ->toMatch( '/<fieldset\s+class="apui-radio-group ec-rating-input"\s+id="rate"\s+role="radiogroup"\s+aria-required="true"/' )
            ->toContain( '<legend data-scope="radio-group" data-part="label">Your rating<span data-scope="radio-group" data-part="required" aria-hidden="true">*</span></legend>' )
            ->toContain( 'class="apui-rating ec-rating-input__stars" data-size="lg" data-interactive' )
            ->toContain( 'aria-label="1 star"' )
            ->toContain( 'aria-label="5 stars"' )
            ->and( substr_count( $html, 'type="radio"' ) )->toBe( 5 )
            ->and( substr_count( $html, 'name="rate"' ) )->toBe( 5 )
            ->and( substr_count( $html, 'wire:model="rating"' ) )->toBe( 5 )
            ->and( substr_count( $html, ' required ' ) )->toBe( 1 )
            ->and( substr_count( $html, 'data-part="star" aria-hidden="true"><svg' ) )->toBe( 5 );
    } );

    it( 'shows the chosen or hovered value as ":rating of 5", hidden from screen readers', function (): void {
        expect( (string) $this->blade( '<x-artisanpack-ec-rating-input id="rate" wire:model="rating" />' ) )
            ->toContain( ':rating of 5' )
            ->toContain( '.replace( \':rating\', preview || rating )' )
            ->toMatch( '/<span\s+class="ec-rating-input__value"\s+aria-hidden="true"/' )
            ->toContain( 'x-on:mouseenter="preview = 3"' )
            ->toContain( 'x-on:mouseleave="preview = 0"' )
            ->toContain( '<legend data-scope="radio-group" data-part="label">Rating</legend>' );
    } );

    it( 'links an error to the group and marks the radios invalid', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-rating-input id="rate" wire:model="rating" error="Choose a rating." />' );

        expect( $html )
            ->toContain( 'data-invalid aria-describedby="rate-error"' )
            ->toMatch( '/<p data-scope="radio-group" data-part="error" id="rate-error" data-rating-input-error><svg[^>]*>.*<\/svg>Choose a rating\.<\/p>/s' )
            ->and( substr_count( $html, 'aria-invalid="true"' ) )->toBe( 5 );

        expect( (string) $this->blade( '<x-artisanpack-ec-rating-input id="rate" wire:model="rating" />' ) )
            ->not->toContain( 'aria-describedby' )
            ->not->toContain( 'aria-invalid' )
            ->not->toContain( 'data-rating-input-error' );
    } );

    it( 'leaves no DaisyUI or Tailwind classes in the rating input', function (): void {
        expect( classTokens( (string) $this->blade( '<x-artisanpack-ec-rating-input id="rate" wire:model="rating" error="Oops" required />' ) ) )
            ->not->toContain( 'rating', 'rating-lg', 'mask', 'mask-star-2', 'flex', 'gap-3', 'text-sm', 'rounded-box' );
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
            ->and( $html )->toMatch( '/aria-label="Image 2 of 2: Mug"\s+x-apui:thumb/' )
            // The teleported lightbox takes the gallery's direction when it opens.
            ->and( $html )->toContain( 'layer.dir = getComputedStyle( this.$root ).direction' );
    } );

    it( 'keeps its part ids stable across renders', function (): void {
        $render = fn (): string => (string) $this->blade( '<x-artisanpack-ec-gallery :images="[ [ \'url\' => \'https://cdn.example.test/1.jpg\' ] ]" name="Mug" />' );

        preg_match( '/id="(ec-gallery-[a-f0-9]+)"/', $render(), $first );
        // A new request (or Livewire render) starts with no ids claimed.
        app()->instance( RenderedIds::class, new RenderedIds() );
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

describe( 'image URLs', function (): void {
    it( 'accepts same-origin root-relative paths and refuses everything unsafe', function ( string $url, ?string $expected ): void {
        expect( ProductImages::safeUrl( $url ) )->toBe( $expected );
    } )->with( [
        'root-relative'     => [ '/storage/a.jpg', '/storage/a.jpg' ],
        'absolute https'    => [ 'https://cdn.example.test/a.jpg', 'https://cdn.example.test/a.jpg' ],
        'protocol-relative' => [ '//evil.test/a.jpg', null ],
        'slash-backslash'   => [ '/\\evil.test/a.jpg', null ],
        'inner backslash'   => [ '/images\\a.jpg', null ],
        'whitespace'        => [ '/a b.jpg', null ],
        'control character' => [ "/a\x01.jpg", null ],
        'quote'             => [ '/a.jpg"onerror="x', null ],
        'javascript'        => [ 'javascript:alert(1)', null ],
        'data'              => [ 'data:image/png;base64,xx', null ],
        'relative'          => [ 'images/a.jpg', null ],
    ] );

    it( 'keeps a root-relative srcset', function (): void {
        expect( ProductImages::safeSrcset( '/a-480.jpg 480w, /a-960.jpg 960w' ) )->toBe( '/a-480.jpg 480w, /a-960.jpg 960w' )
            ->and( ProductImages::safeSrcset( '/a-480.jpg 480w, //evil.test/b.jpg 960w' ) )->toBeNull();
    } );

    it( 'shows a root-relative product image in the gallery and on the card', function (): void {
        $product = makeProduct( 1000, [ 'name' => 'Mug' ] );
        ProductImage::factory()->create( [ 'product_id' => $product->id, 'media_id' => null, 'image_url' => '/images/mug.jpg', 'position' => 0 ] );

        $this->blade( '<x-artisanpack-ec-gallery :images="$images" name="Mug" />', [ 'images' => ProductImages::gallery( $product->fresh() ) ] )
            ->assertSee( 'src="/images/mug.jpg"', false )
            ->assertDontSee( 'No image available' );

        $this->blade( '<x-artisanpack-ec-sf-product-card :product="$product" />', [ 'product' => $product->fresh( 'images' ) ] )
            ->assertSee( 'src="/images/mug.jpg"', false );
    } );
} );

describe( 'quantity label', function (): void {
    it( 'names the item in the hidden label', function (): void {
        $this->blade( '<x-artisanpack-ec-quantity id="qty" item-name="Oxbow mug" />' )
            ->assertSee( 'for="qty"', false )
            ->assertSee( 'Quantity, Oxbow mug' );
    } );

    it( 'still reads "Quantity" with neither a label nor an item name', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-quantity id="qty" />' );

        expect( $html )->toMatch( '#<label[^>]*for="qty"[^>]*>\s*Quantity\s*</label>#' );
    } );

    it( 'passes required to the input', function (): void {
        $html = (string) $this->blade( '<x-artisanpack-ec-quantity id="qty" label="Copies" :required="true" />' );

        expect( $html )->toContain( 'data-part="required"' )
            ->and( $html )->toMatch( '#<input[^>]*\brequired\b#' )
            ->and( $html )->not->toMatch( '#<div[^>]*class="ec-quantity"[^>]*\brequired\b#' );
    } );
} );
