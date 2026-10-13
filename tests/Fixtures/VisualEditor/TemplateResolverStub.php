<?php

/**
 * Stand-in for cms-framework's site-editor template resolver.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;
use RuntimeException;

if ( ! class_exists( __NAMESPACE__ . '\\TemplateResolver', false ) ) {
    /**
     * Resolves the slugs in {@see self::$saved} to an entity with blocks;
     * throws when {@see self::$fails} is set.
     *
     * @since 1.0.0
     */
    class TemplateResolver
    {
        /**
         * Saved template slugs.
         *
         * @since 1.0.0
         *
         * @var array<int, string>
         */
        public static array $saved = [];

        /**
         * Whether lookups throw.
         *
         * @since 1.0.0
         *
         * @var bool
         */
        public static bool $fails = false;

        /**
         * The saved template for `$slug`, or null.
         *
         * @since 1.0.0
         *
         * @param  string  $slug  Template slug.
         *
         * @return object|null
         */
        public function resolve( string $slug ): ?object
        {
            if ( self::$fails ) {
                throw new RuntimeException( 'Template lookup failed.' );
            }

            return in_array( $slug, self::$saved, true ) ? (object) [ 'slug' => $slug, 'blocks' => [] ] : null;
        }
    }
}
