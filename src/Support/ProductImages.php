<?php

/**
 * Product image helpers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArrayObject;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductImage;
use Closure;
use Throwable;

/**
 * Resolves product images whether or not `artisanpack-ui/media-library` is
 * installed (spec §4.2).
 *
 * With the library, a product's featured image is a media id and the card
 * gets a `srcset` built from the library's image sizes. Without it, images
 * are the `meta.featured_image_url` or gallery `image_url` values, which are
 * only used when they are http(s) URLs.
 *
 * Listings call {@see self::preload()} (or {@see self::preloadCards()})
 * first, so a page of cards looks its media up in one query rather than
 * one per card.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class ProductImages
{
    /**
     * The media-library sizes offered in a card's `srcset`, smallest first.
     * The first one is also the `src`.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const CARD_SIZES = [ 'medium', 'large' ];

    /**
     * The media-library sizes offered in a product page gallery's `srcset`,
     * smallest first. The first one is also the `src`; the lightbox and
     * zoom use the `full` size.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const DETAIL_SIZES = [ 'medium', 'large' ];

    /**
     * The media library's model, looked up by name so the library stays
     * optional.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private const MEDIA_MODEL = 'ArtisanPackUI\\MediaLibrary\\Models\\Media';

    /**
     * The container key of the request's preloaded media (bound `scoped`,
     * so it is dropped between requests and queued jobs).
     *
     * @since 1.0.0
     *
     * @var string
     */
    private const PRELOADED = 'artisanpack.ecommerce-storefront.preloaded-media';

    /**
     * Override for tests: true/false forces the answer, null detects.
     *
     * @since 1.0.0
     *
     * @var bool|null
     */
    private static ?bool $fake = null;

    /**
     * Override for tests: looks media items up by id (ids => items keyed
     * by id); null uses the library.
     *
     * @since 1.0.0
     *
     * @var (Closure(array<int, int>): iterable<int, object|null>)|null
     */
    private static ?Closure $fakeLookup = null;

    /**
     * Whether the media library is installed.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function libraryInstalled(): bool
    {
        return self::$fake ?? function_exists( 'apGetMedia' );
    }

    /**
     * Forces {@see self::libraryInstalled()} (null restores detection),
     * and optionally how media items are looked up: `$lookup` gets the ids
     * and returns the items keyed by id.
     *
     * @since 1.0.0
     *
     * @param  bool|null                                                   $installed  Forced answer.
     * @param  (Closure(array<int, int>): iterable<int, object|null>)|null  $lookup     Forced lookup.
     *
     * @return void
     */
    public static function fake( ?bool $installed, ?Closure $lookup = null ): void
    {
        self::$fake       = $installed;
        self::$fakeLookup = $lookup;
    }

    /**
     * Looks up media items in one query and keeps them for the rest of the
     * request, so the images built from them afterwards run no query each.
     * Ids already loaded are skipped; does nothing without the library.
     *
     * The items are kept per request (a `scoped` container binding), so
     * nothing leaks between requests, queued jobs, or tests.
     *
     * ```php
     * ProductImages::preload( $categories->pluck( 'image_media_id' ) );
     * ```
     *
     * @since 1.0.0
     *
     * @param  iterable<int, mixed>  $mediaIds  Media ids; empty and invalid ids are skipped.
     *
     * @return void
     */
    public static function preload( iterable $mediaIds ): void
    {
        if ( ! self::libraryInstalled() ) {
            return;
        }

        $loaded = self::preloaded();
        $ids    = [];

        foreach ( $mediaIds as $mediaId ) {
            $id = is_int( $mediaId ) || is_string( $mediaId ) ? filter_var( $mediaId, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] ) : false;

            if ( false !== $id && ! $loaded->offsetExists( $id ) ) {
                $ids[ $id ] = $id;
            }
        }

        if ( [] === $ids ) {
            return;
        }

        try {
            $found = self::lookup( array_values( $ids ) );
        } catch ( Throwable $exception ) {
            // Images are an aid: each one is looked up on its own instead.
            report( $exception );

            return;
        }

        foreach ( $ids as $id ) {
            $loaded[ $id ] = $found[ $id ] ?? null;
        }
    }

    /**
     * Preloads the media every card of `$products` may show: the featured
     * image and, when the gallery is loaded, the first gallery image.
     *
     * @since 1.0.0
     *
     * @param  iterable<int, mixed>  $products  Products (anything else is skipped).
     *
     * @return void
     */
    public static function preloadCards( iterable $products ): void
    {
        $ids = [];

        foreach ( $products as $product ) {
            if ( ! $product instanceof Product ) {
                continue;
            }

            $ids[] = $product->featured_image_media_id;

            if ( $product->relationLoaded( 'images' ) ) {
                $ids[] = $product->images->first()?->media_id;
            }
        }

        self::preload( $ids );
    }

    /**
     * The image a product card shows: its featured media item, its
     * `meta.featured_image_url`, or else its first gallery image.
     *
     * @since 1.0.0
     *
     * @param  Product  $product  Product (eager-load `images` to avoid a query per card).
     *
     * @return array{url: string, srcset: string|null, alt: string}|null
     */
    public static function card( Product $product ): ?array
    {
        $alt   = (string) $product->name;
        $media = self::fromMedia( $product->featured_image_media_id, $alt );

        if ( null !== $media ) {
            return $media;
        }

        $url = self::safeUrl( $product->meta['featured_image_url'] ?? null );

        if ( null !== $url ) {
            return [ 'url' => $url, 'srcset' => null, 'alt' => $alt ];
        }

        // A lone card (a block, a related product) may get a product without
        // its gallery loaded; query it rather than lazy-load, which hosts
        // that prevent lazy loading turn into an exception.
        $first = $product->relationLoaded( 'images' ) ? $product->images->first() : $product->images()->first();

        if ( ! $first instanceof ProductImage ) {
            return null;
        }

        $alt = '' !== trim( (string) $first->alt_text ) ? (string) $first->alt_text : $alt;

        return self::fromMedia( $first->media_id, $alt )
            ?? ( null === ( $url = self::safeUrl( $first->image_url ) ) ? null : [ 'url' => $url, 'srcset' => null, 'alt' => $alt ] );
    }

    /**
     * An http(s) URL, or null.
     *
     * @since 1.0.0
     *
     * @param  mixed  $url  Candidate.
     *
     * @return string|null
     */
    public static function safeUrl( mixed $url ): ?string
    {
        if ( ! is_string( $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return null;
        }

        return in_array( strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ), [ 'http', 'https' ], true ) ? $url : null;
    }

    /**
     * Every image a product page gallery shows, featured image first, then
     * the gallery in position order (duplicates of the featured image
     * dropped). Each image's alternative text is its own, else the product
     * name.
     *
     * @since 1.0.0
     *
     * @param  Product  $product  Product (eager-load `images` to avoid a query).
     *
     * @return array<int, array{key: string, url: string, srcset: string|null, full: string, alt: string, media_id: int|null}>
     */
    public static function gallery( Product $product ): array
    {
        return self::buildGallery( $product, $product->relationLoaded( 'images' ) ? $product->images : $product->images()->get() );
    }

    /**
     * The product page gallery: {@see self::gallery()}, then any variant
     * images not already in it, so choosing a variant can show its image.
     *
     * @since 1.0.0
     *
     * @param  Product  $product  Product.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function withVariants( Product $product ): array
    {
        $variantMedia = $product->variants()->whereNotNull( 'image_media_id' )->orderBy( 'position' )->orderBy( 'id' )->pluck( 'image_media_id' );

        $gallery      = $product->relationLoaded( 'images' ) ? $product->images : $product->images()->get();

        self::preload( [ $product->featured_image_media_id, ...$gallery->pluck( 'media_id' ), ...$variantMedia ] );

        $images = self::buildGallery( $product, $gallery );
        $urls   = array_column( $images, 'url' );

        foreach ( $variantMedia as $mediaId ) {
            $image = self::media( (int) $mediaId, (string) $product->name );

            if ( null !== $image && ! in_array( $image['url'], $urls, true ) ) {
                $images[] = $image + [ 'key' => 'media-' . $mediaId, 'media_id' => (int) $mediaId ];
                $urls[]   = $image['url'];
            }
        }

        return $images;
    }

    /**
     * One media-library image at product-page sizes (a variant's or a
     * category's image), or null without the library.
     *
     * @since 1.0.0
     *
     * @param  int|null  $mediaId  Media id.
     * @param  string    $alt      Alternative text when the media item has none.
     *
     * @return array{url: string, srcset: string|null, full: string, alt: string}|null
     */
    public static function media( ?int $mediaId, string $alt ): ?array
    {
        return self::fromMedia( $mediaId, $alt, self::DETAIL_SIZES );
    }

    /**
     * The `width` and `height` attributes for an image (spec §12), so the
     * browser reserves its space before it loads: the image's own size
     * when the media library knows it, else `$width` × `$height` (the
     * aspect ratio of the box the image fills).
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>|null  $image   An image from this class.
     * @param  int                        $width   Fallback width.
     * @param  int                        $height  Fallback height.
     *
     * @return array{width: int, height: int}
     */
    public static function dimensions( ?array $image, int $width, int $height ): array
    {
        $ownWidth  = $image['width'] ?? null;
        $ownHeight = $image['height'] ?? null;

        return is_int( $ownWidth ) && is_int( $ownHeight ) && $ownWidth > 0 && $ownHeight > 0
            ? [ 'width' => $ownWidth, 'height' => $ownHeight ]
            : [ 'width' => $width, 'height' => $height ];
    }

    /**
     * Builds {@see self::gallery()} from the product's gallery images.
     *
     * @since 1.0.0
     *
     * @param  Product               $product  Product.
     * @param  iterable<int, mixed>  $gallery  The product's gallery images, in position order.
     *
     * @return array<int, array{key: string, url: string, srcset: string|null, full: string, alt: string, media_id: int|null}>
     */
    private static function buildGallery( Product $product, iterable $gallery ): array
    {
        $name   = (string) $product->name;
        $images = [];
        $seen   = [];

        $add = static function ( ?array $image, ?int $mediaId, string $key ) use ( &$images, &$seen ): void {
            if ( null === $image || isset( $seen[ $image['url'] ] ) ) {
                return;
            }

            $seen[ $image['url'] ] = true;
            $images[]              = $image + [ 'key' => $key, 'media_id' => $mediaId ];
        };

        $featuredId = null === $product->featured_image_media_id ? null : (int) $product->featured_image_media_id;
        $featured   = self::fromMedia( $featuredId, $name, self::DETAIL_SIZES );

        if ( null === $featured && null !== ( $url = self::safeUrl( $product->meta['featured_image_url'] ?? null ) ) ) {
            $featured = [ 'url' => $url, 'srcset' => null, 'full' => $url, 'alt' => $name ];
        }

        $add( $featured, $featuredId, 'featured' );

        foreach ( $gallery as $image ) {
            if ( ! $image instanceof ProductImage ) {
                continue;
            }

            $alt     = '' !== trim( (string) $image->alt_text ) ? (string) $image->alt_text : $name;
            $mediaId = null === $image->media_id ? null : (int) $image->media_id;
            $url     = self::safeUrl( $image->image_url );

            $add(
                self::fromMedia( $mediaId, $alt, self::DETAIL_SIZES )
                    ?? ( null === $url ? null : [ 'url' => $url, 'srcset' => null, 'full' => $url, 'alt' => $alt ] ),
                $mediaId,
                'image-' . $image->id,
            );
        }

        return $images;
    }

    /**
     * A media-library image with its `srcset`, or null.
     *
     * @since 1.0.0
     *
     * @param  int|null            $mediaId  Media id.
     * @param  string              $alt      Alternative text.
     * @param  array<int, string>  $sizes    Sizes for the `srcset`, smallest first.
     *
     * @return array{url: string, srcset: string|null, full: string, alt: string, width: int|null, height: int|null}|null
     */
    private static function fromMedia( ?int $mediaId, string $alt, array $sizes = self::CARD_SIZES ): ?array
    {
        if ( null === $mediaId || ! self::libraryInstalled() ) {
            return null;
        }

        try {
            $loaded = self::preloaded();
            $media  = $loaded->offsetExists( $mediaId ) ? $loaded[ $mediaId ] : self::lookupOne( $mediaId );

            if ( null === $media || ! $media->isImage() ) {
                return null;
            }

            $sources = [];

            foreach ( $sizes as $size ) {
                $url   = self::safeUrl( $media->imageUrl( $size ) );
                $width = (int) config( 'artisanpack.media.image_sizes.' . $size . '.width', 0 );

                if ( null !== $url ) {
                    $sources[ $url ] = $width;
                }
            }

            $full     = self::safeUrl( $media->imageUrl( 'full' ) );
            $original = [ (int) ( $media->width ?? 0 ), (int) ( $media->height ?? 0 ) ];
        } catch ( Throwable ) {
            return null;
        }

        if ( [] === $sources ) {
            return null;
        }

        $srcset = [];

        foreach ( $sources as $url => $width ) {
            if ( $width > 0 ) {
                $srcset[] = $url . ' ' . $width . 'w';
            }
        }

        [ $width, $height ] = self::intrinsic( (int) reset( $sources ), $original[0], $original[1] );

        return [
            'url'    => (string) array_key_first( $sources ),
            'srcset' => count( $srcset ) > 1 ? implode( ', ', $srcset ) : null,
            'full'   => $full ?? (string) array_key_last( $sources ),
            'alt'    => '' !== trim( (string) ( $media->alt_text ?? '' ) ) ? (string) $media->alt_text : $alt,
            'width'  => $width,
            'height' => $height,
        ];
    }

    /**
     * The request's preloaded media items, keyed by id (null for an id the
     * library doesn't have).
     *
     * @since 1.0.0
     *
     * @return ArrayObject<int, object|null>
     */
    private static function preloaded(): ArrayObject
    {
        $app = app();

        if ( ! $app->bound( self::PRELOADED ) ) {
            $app->scoped( self::PRELOADED, static fn (): ArrayObject => new ArrayObject() );
        }

        return $app->make( self::PRELOADED );
    }

    /**
     * Media items by id, in one query when the library's model is there.
     *
     * @since 1.0.0
     *
     * @param  array<int, int>  $ids  Media ids.
     *
     * @return array<int, object|null>
     */
    private static function lookup( array $ids ): array
    {
        if ( null !== self::$fakeLookup ) {
            $items = [];

            foreach ( ( self::$fakeLookup )( $ids ) as $id => $item ) {
                $items[ (int) $id ] = $item;
            }

            return $items;
        }

        $model = self::MEDIA_MODEL;

        if ( class_exists( $model ) ) {
            return $model::query()->whereKey( $ids )->get()->keyBy( static fn ( object $media ): int => (int) $media->getKey() )->all();
        }

        // Without the model only the helper is there: one call per id.
        $items = [];

        foreach ( $ids as $id ) {
            $items[ $id ] = apGetMedia( $id );
        }

        return $items;
    }

    /**
     * One media item by id, or null.
     *
     * @since 1.0.0
     *
     * @param  int  $mediaId  Media id.
     *
     * @return object|null
     */
    private static function lookupOne( int $mediaId ): ?object
    {
        if ( null !== self::$fakeLookup ) {
            return self::lookup( [ $mediaId ] )[ $mediaId ] ?? null;
        }

        return apGetMedia( $mediaId );
    }

    /**
     * The rendered size of the first `srcset` source: its configured width
     * at the original's aspect ratio, the original's size when no width is
     * configured, or nulls when the original's size is unknown.
     *
     * @since 1.0.0
     *
     * @param  int  $sourceWidth     The first source's configured width (0 when unknown).
     * @param  int  $originalWidth   The original's width (0 when unknown).
     * @param  int  $originalHeight  The original's height (0 when unknown).
     *
     * @return array{0: int|null, 1: int|null}
     */
    private static function intrinsic( int $sourceWidth, int $originalWidth, int $originalHeight ): array
    {
        if ( $originalWidth <= 0 || $originalHeight <= 0 ) {
            return [ null, null ];
        }

        if ( $sourceWidth <= 0 || $sourceWidth >= $originalWidth ) {
            return [ $originalWidth, $originalHeight ];
        }

        return [ $sourceWidth, max( 1, (int) round( $sourceWidth * $originalHeight / $originalWidth ) ) ];
    }
}
