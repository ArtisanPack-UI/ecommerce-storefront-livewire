<?php

/**
 * Text direction for a locale.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

/**
 * Whether a locale is written right to left, from the
 * `storefront.rtl_locales` config (language codes; `ar`, `he`, `fa`, and
 * `ur` by default). The standalone layout uses it for `<html dir>`; a
 * host layout can do the same:
 *
 *     <html lang="…" dir="{{ \ArtisanPackUI\EcommerceStorefrontLivewire\Support\TextDirection::for() }}">
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class TextDirection
{
    /**
     * The right-to-left languages used when the config sets none.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const DEFAULT_RTL_LOCALES = [ 'ar', 'he', 'fa', 'ur' ];

    /**
     * `rtl` or `ltr` for a locale (the app locale by default). Only the
     * language part counts, so `ar_EG` and `ar-EG` follow `ar`.
     *
     * @since 1.0.0
     *
     * @param  string|null  $locale  The locale, e.g. `ar` or `en_US`.
     *
     * @return string
     */
    public static function for( ?string $locale = null ): string
    {
        $language = strtolower( (string) strtok( str_replace( '_', '-', $locale ?? app()->getLocale() ), '-' ) );
        $rtl      = array_map(
            static fn ( mixed $code ): string => strtolower( (string) $code ),
            (array) config( 'artisanpack.ecommerce-storefront-livewire.storefront.rtl_locales', self::DEFAULT_RTL_LOCALES ),
        );

        return in_array( $language, $rtl, true ) ? 'rtl' : 'ltr';
    }
}
