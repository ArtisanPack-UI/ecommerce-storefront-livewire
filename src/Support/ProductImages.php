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

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductImage;
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
     * Override for tests: true/false forces the answer, null detects.
     *
     * @since 1.0.0
     *
     * @var bool|null
     */
    private static ?bool $fake = null;

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
     * Forces {@see self::libraryInstalled()} (null restores detection).
     *
     * @since 1.0.0
     *
     * @param  bool|null  $installed  Forced answer.
     *
     * @return void
     */
    public static function fake( ?bool $installed ): void
    {
        self::$fake = $installed;
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

        $gallery = $product->relationLoaded( 'images' ) ? $product->images : $product->images()->get();

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
        $images = self::gallery( $product );
        $urls   = array_column( $images, 'url' );

        $variantMedia = $product->variants()->whereNotNull( 'image_media_id' )->orderBy( 'position' )->orderBy( 'id' )->pluck( 'image_media_id' );

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
     * A media-library image with its `srcset`, or null.
     *
     * @since 1.0.0
     *
     * @param  int|null            $mediaId  Media id.
     * @param  string              $alt      Alternative text.
     * @param  array<int, string>  $sizes    Sizes for the `srcset`, smallest first.
     *
     * @return array{url: string, srcset: string|null, full: string, alt: string}|null
     */
    private static function fromMedia( ?int $mediaId, string $alt, array $sizes = self::CARD_SIZES ): ?array
    {
        if ( null === $mediaId || ! self::libraryInstalled() ) {
            return null;
        }

        try {
            $media = apGetMedia( $mediaId );

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

            $full = self::safeUrl( $media->imageUrl( 'full' ) );
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

        return [
            'url'    => (string) array_key_first( $sources ),
            'srcset' => count( $srcset ) > 1 ? implode( ', ', $srcset ) : null,
            'full'   => $full ?? (string) array_key_last( $sources ),
            'alt'    => '' !== trim( (string) ( $media->alt_text ?? '' ) ) ? (string) $media->alt_text : $alt,
        ];
    }
}
