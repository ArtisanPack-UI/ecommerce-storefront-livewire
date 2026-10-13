<?php

/**
 * A media-library item stand-in.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Fixtures\Media;

/**
 * An image with a URL per conversion, served by the `apGetMedia()` stub.
 *
 * @since 1.0.0
 */
class FakeMedia
{
    /**
     * Registered items by id.
     *
     * @since 1.0.0
     *
     * @var array<int, self>
     */
    public static array $items = [];

    /**
     * @since 1.0.0
     *
     * @param  int          $width     The original's width.
     * @param  int          $height    The original's height.
     * @param  string|null  $alt_text  Alternative text.
     */
    public function __construct( public int $width, public int $height, public ?string $alt_text = null )
    {
    }

    /**
     * Registers an item under `$id`.
     *
     * @since 1.0.0
     *
     * @param  int   $id     Media id.
     * @param  self  $media  The item.
     *
     * @return void
     */
    public static function register( int $id, self $media ): void
    {
        self::$items[ $id ] = $media;
    }

    /**
     * @since 1.0.0
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return true;
    }

    /**
     * The URL of a conversion.
     *
     * @since 1.0.0
     *
     * @param  string  $size  Conversion name.
     *
     * @return string
     */
    public function imageUrl( string $size ): string
    {
        return 'https://media.example.test/' . $size . '.jpg';
    }
}
