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
     * A media-library image with its `srcset`, or null.
     *
     * @since 1.0.0
     *
     * @param  int|null  $mediaId  Media id.
     * @param  string    $alt      Alternative text.
     *
     * @return array{url: string, srcset: string|null, alt: string}|null
     */
    private static function fromMedia( ?int $mediaId, string $alt ): ?array
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

            foreach ( self::CARD_SIZES as $size ) {
                $url   = self::safeUrl( $media->imageUrl( $size ) );
                $width = (int) config( 'artisanpack.media.image_sizes.' . $size . '.width', 0 );

                if ( null !== $url ) {
                    $sources[ $url ] = $width;
                }
            }
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
            'alt'    => '' !== trim( (string) ( $media->alt_text ?? '' ) ) ? (string) $media->alt_text : $alt,
        ];
    }
}
