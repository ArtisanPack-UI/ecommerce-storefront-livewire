<?php

/**
 * Recently Viewed block.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\RecentlyViewed;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\GridColumns;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontContext;

/**
 * `artisanpack-commerce/recently-viewed`: the shopper's recently viewed
 * products. Registered only when the recently-viewed satellite is
 * installed and active. Renders Product\RecentlyViewed.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class RecentlyViewedBlock extends StorefrontBlock
{
    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return 'recently-viewed';
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function title(): string
    {
        return __( 'Recently Viewed' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function description(): string
    {
        return __( 'The products the shopper looked at last.' );
    }

    /**
     * Only with the recently-viewed satellite.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function available(): bool
    {
        $satellites = app( SatelliteRegistry::class );

        return $satellites->has( RecentlyViewed::SATELLITE ) && $satellites->isActive( RecentlyViewed::SATELLITE );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function attributes(): array
    {
        return [
            'limit'   => [ 'type' => 'number', 'default' => 4, 'apControl' => [ 'control' => 'range', 'label' => __( 'Number of products' ), 'min' => 1, 'max' => 12 ] ],
            'columns' => [ 'type' => 'number', 'default' => 4, 'apControl' => [ 'control' => 'range', 'label' => __( 'Columns' ), 'min' => GridColumns::MIN, 'max' => GridColumns::MAX ] ],
            'heading' => [ 'type' => 'string', 'default' => '', 'apControl' => [ 'control' => 'text', 'label' => __( 'Heading' ) ] ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function icon(): string
    {
        return 'backup';
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function keywords(): array
    {
        return [ __( 'history' ) ];
    }

    /**
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $attrs  Clamped attributes.
     *
     * @return string|null
     */
    protected function html( array $attrs ): ?string
    {
        return view( 'ecommerce-storefront::blocks.livewire', [
            'component' => 'artisanpack-ecommerce-storefront-recently-viewed',
            'params'    => [
                'product' => app( StorefrontContext::class )->product(),
                'limit'   => $attrs['limit'],
                'columns' => $attrs['columns'],
                'heading' => '' === $attrs['heading'] ? null : $attrs['heading'],
            ],
        ] )->render();
    }
}
