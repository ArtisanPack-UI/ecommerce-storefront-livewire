<?php

/**
 * Grid column classes.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

/**
 * Tailwind grid classes for a chosen column count (1–6), written out in
 * full here so the host's Tailwind build (whose `@source` covers `src/`)
 * finds them.
 *
 * Small screens get at most two columns; the chosen count applies from
 * `lg` up.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class GridColumns
{
    /**
     * The fewest columns.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MIN = 1;

    /**
     * The most columns.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX = 6;

    /**
     * Classes for a grid of `$columns` columns.
     *
     * @since 1.0.0
     *
     * @param  int  $columns  Columns (clamped to 1–6).
     *
     * @return string
     */
    public static function classes( int $columns ): string
    {
        return match ( self::clamp( $columns ) ) {
            1       => 'grid-cols-1',
            2       => 'grid-cols-2',
            3       => 'grid-cols-2 lg:grid-cols-3',
            5       => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
            6       => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
            default => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
        };
    }

    /**
     * The `lg` column class alone, for lists that scroll sideways below
     * `md` (related products).
     *
     * @since 1.0.0
     *
     * @param  int  $columns  Columns (clamped to 1–6).
     *
     * @return string
     */
    public static function large( int $columns ): string
    {
        return match ( self::clamp( $columns ) ) {
            1       => 'md:grid-cols-1 lg:grid-cols-1',
            2       => 'md:grid-cols-2 lg:grid-cols-2',
            3       => 'md:grid-cols-2 lg:grid-cols-3',
            5       => 'md:grid-cols-2 lg:grid-cols-5',
            6       => 'md:grid-cols-2 lg:grid-cols-6',
            default => 'md:grid-cols-2 lg:grid-cols-4',
        };
    }

    /**
     * `$columns` clamped to {@see self::MIN}–{@see self::MAX}.
     *
     * @since 1.0.0
     *
     * @param  int  $columns  Columns.
     *
     * @return int
     */
    public static function clamp( int $columns ): int
    {
        return max( self::MIN, min( self::MAX, $columns ) );
    }
}
