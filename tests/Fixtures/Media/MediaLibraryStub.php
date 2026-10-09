<?php

/**
 * Stand-in for artisanpack-ui/media-library's `apGetMedia()` helper.
 *
 * Unknown ids return null, which the storefront treats as "no media", so
 * loading this changes nothing for products without registered media.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Tests\Fixtures\Media\FakeMedia;

if ( ! function_exists( 'apGetMedia' ) ) {
    /**
     * A registered fake media item.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Media id.
     *
     * @return FakeMedia|null
     */
    function apGetMedia( int $id ): ?FakeMedia
    {
        return FakeMedia::$items[ $id ] ?? null;
    }
}
