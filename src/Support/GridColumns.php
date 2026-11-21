<?php

/**
 * Grid column counts.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

/**
 * The column count (1–6) a grid block or component may ask for. The grids
 * lay themselves out from the clamped count with `data-columns` (see
 * resources/css/storefront.css).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 * @since      2.0.0 `classes()` and `large()` are gone; grids use `data-columns`.
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
