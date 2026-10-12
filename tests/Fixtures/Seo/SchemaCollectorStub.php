<?php

/**
 * Stand-in for artisanpack-ui/seo's schema collector.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\SEO\Support;

if ( ! class_exists( __NAMESPACE__ . '\\SchemaCollector', false ) ) {
    /**
     * Collects schema entries for the request.
     *
     * @since 1.0.0
     */
    class SchemaCollector
    {
        /**
         * Collected entries.
         *
         * @since 1.0.0
         *
         * @var array<int, array<string, mixed>>
         */
        public array $entries = [];

        /**
         * Adds an entry.
         *
         * @since 1.0.0
         *
         * @param  array<string, mixed>  $schema  The entry.
         *
         * @return void
         */
        public function add( array $schema ): void
        {
            $this->entries[] = $schema;
        }
    }
}
