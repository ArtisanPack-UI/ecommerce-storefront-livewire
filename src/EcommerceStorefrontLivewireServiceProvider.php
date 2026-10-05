<?php

/**
 * Ecommerce storefront (Livewire) service provider.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire;

use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps the Livewire storefront satellite.
 *
 * Registers the package with the engine's `SatelliteRegistry` first. When the
 * satellite has been uninstalled (`register()` returns false) nothing else is
 * wired: no views, routes, components, navigation, or hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class EcommerceStorefrontLivewireServiceProvider extends ServiceProvider
{
    /**
     * The package version reported to the engine's `SatelliteRegistry`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const VERSION = '1.0.0';

    /**
     * The Composer package name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PACKAGE_NAME = 'artisanpack-ui/ecommerce-storefront-livewire';

    /**
     * The view and translation namespace.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const VIEW_NAMESPACE = 'ecommerce-storefront';

    /**
     * Registers the package configuration.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/artisanpack/ecommerce-storefront-livewire.php',
            'artisanpack.ecommerce-storefront-livewire',
        );
    }

    /**
     * Registers the satellite and, when it is active, wires the storefront.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        if ( ! $this->registerSatellite() ) {
            return;
        }

        $this->registerPublishing();
        $this->registerViews();
        $this->registerTranslations();
    }

    /**
     * The descriptor this package registers with the engine (spec §14).
     *
     * The storefront owns no tables, columns, migrations, meta namespaces, or
     * product types, so it ships no uninstaller.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public static function satelliteDescriptor(): array
    {
        return [
            'package_name'    => self::PACKAGE_NAME,
            'version'         => self::VERSION,
            'label'           => __( 'Storefront (Livewire)' ),
            'migration_paths' => [],
            'config_keys'     => [ 'artisanpack.ecommerce-storefront-livewire' ],
            'meta_namespaces' => [],
            'tables'          => [],
            'columns'         => [],
            'product_types'   => [],
        ];
    }

    /**
     * Registers the package with the engine's `SatelliteRegistry`.
     *
     * @since 1.0.0
     *
     * @return bool True when the satellite is active.
     */
    protected function registerSatellite(): bool
    {
        return $this->app->make( SatelliteRegistry::class )->register( self::satelliteDescriptor() );
    }

    /**
     * Registers the config, views, and translations publish tags.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerPublishing(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../config/artisanpack/ecommerce-storefront-livewire.php' => config_path( 'artisanpack/ecommerce-storefront-livewire.php' ),
            ],
            'ecommerce-storefront-config',
        );

        $this->publishes(
            [
                __DIR__ . '/../resources/views' => resource_path( 'views/vendor/' . self::VIEW_NAMESPACE ),
            ],
            'ecommerce-storefront-views',
        );

        $this->publishes(
            [
                __DIR__ . '/../lang' => $this->app->langPath( 'vendor/' . self::VIEW_NAMESPACE ),
            ],
            'ecommerce-storefront-lang',
        );
    }

    /**
     * Loads the package views under the `ecommerce-storefront` namespace.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom( __DIR__ . '/../resources/views', self::VIEW_NAMESPACE );
    }

    /**
     * Loads the JSON translation catalogues.
     *
     * Published catalogues in `lang/vendor/ecommerce-storefront` are loaded too so a
     * host can override individual strings.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerTranslations(): void
    {
        $this->loadJsonTranslationsFrom( __DIR__ . '/../lang' );
        $this->loadJsonTranslationsFrom( $this->app->langPath( 'vendor/' . self::VIEW_NAMESPACE ) );
    }
}
