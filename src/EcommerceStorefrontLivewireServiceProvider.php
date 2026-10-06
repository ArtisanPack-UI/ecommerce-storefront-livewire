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
use ArtisanPackUI\EcommerceStorefrontLivewire\Console\Commands\InstallCommand;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\MergePrompt;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
     * The view every page extends unless `storefront.layout` names another.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DEFAULT_LAYOUT = 'ecommerce-storefront::layouts.app';

    /**
     * The composed Blade components, keyed by their name after the
     * `artisanpack-ec-` prefix (spec §8.1). `money`, `address`,
     * `address-form`, and `empty-state` share their names and base props
     * with the admin's copies.
     *
     * @since 1.0.0
     *
     * @var array<string, class-string>
     */
    public const BLADE_COMPONENTS = [
        'address'         => Components\Address::class,
        'address-form'    => Components\AddressForm::class,
        'empty-state'     => Components\EmptyState::class,
        'money'           => Components\Money::class,
        'price'           => Components\Price::class,
        'rating-summary'  => Components\RatingSummary::class,
        'sf-product-card' => Components\ProductCard::class,
        'skeleton'        => Components\Skeleton::class,
        'stock-status'    => Components\StockStatus::class,
    ];

    /**
     * The package's Livewire components, keyed by component name.
     *
     * @since 1.0.0
     *
     * @var array<string, class-string>
     */
    public const LIVEWIRE_COMPONENTS = [
        'artisanpack-ecommerce-storefront-catalog'           => Catalog\Index::class,
        'artisanpack-ecommerce-storefront-cart-merge-prompt' => MergePrompt::class,
    ];

    /**
     * Registers the package configuration and the per-request cart resolver.
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

        $this->app->scoped( StorefrontCart::class );
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
        $this->registerCommands();
        $this->registerBladeComponents();
        $this->registerLayoutResolver();
        $this->registerLivewireComponents();
        $this->registerRoutes();
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
     * The middleware classes behind `account.middleware`, without groups
     * (`web`), which Livewire's update route already has.
     *
     * @since 1.0.0
     *
     * @return array<int, class-string>
     */
    public static function accountPersistentMiddleware(): array
    {
        $router  = app( 'router' );
        $aliases = $router->getMiddleware();
        $groups  = $router->getMiddlewareGroups();
        $classes = [];

        foreach ( (array) config( 'artisanpack.ecommerce-storefront-livewire.account.middleware', [] ) as $entry ) {
            if ( ! is_string( $entry ) || '' === $entry ) {
                continue;
            }

            $name = explode( ':', $entry, 2 )[0];

            if ( isset( $groups[ $name ] ) ) {
                continue;
            }

            $class = $aliases[ $name ] ?? $name;

            if ( is_string( $class ) && class_exists( $class ) ) {
                $classes[] = $class;
            }
        }

        return array_values( array_unique( $classes ) );
    }

    /**
     * Makes the account middleware (`auth`, `verified`, ...) persistent, so
     * Livewire re-runs it on every update request from an account page
     * (spec §6).
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function registerPersistentMiddleware(): void
    {
        $middleware = self::accountPersistentMiddleware();

        if ( [] !== $middleware ) {
            Livewire::addPersistentMiddleware( $middleware );
        }
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

    /**
     * Registers the Artisan commands.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ( ! $this->app->runningInConsole() ) {
            return;
        }

        $this->commands( [
            InstallCommand::class,
        ] );
    }

    /**
     * Registers the `<x-artisanpack-ec-…>` Blade components.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerBladeComponents(): void
    {
        foreach ( self::BLADE_COMPONENTS as $name => $class ) {
            Blade::component( 'artisanpack-ec-' . $name, $class );
        }
    }

    /**
     * Shares the layout every page view extends (spec §5.3): the configured
     * `storefront.layout`, or the package's standalone layout.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerLayoutResolver(): void
    {
        View::composer( self::VIEW_NAMESPACE . '::pages.*', static function ( ViewContract $view ): void {
            $layout = config( 'artisanpack.ecommerce-storefront-livewire.storefront.layout' );

            $view->with( 'ecommerceStorefrontLayout', is_string( $layout ) && '' !== $layout ? $layout : self::DEFAULT_LAYOUT );
        } );
    }

    /**
     * Registers the Livewire components and, once every middleware alias is
     * known, the account middleware as persistent.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerLivewireComponents(): void
    {
        foreach ( self::LIVEWIRE_COMPONENTS as $name => $class ) {
            Livewire::component( $name, $class );
        }

        $this->app->booted( fn () => $this->registerPersistentMiddleware() );
    }

    /**
     * Registers the storefront and account routes.
     *
     * Skipped when the routes are cached (the cache already holds them) or
     * when `storefront.routes_enabled` is false, for hosts that route and
     * embed the components themselves.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        if ( $this->app->routesAreCached() ) {
            return;
        }

        if ( ! (bool) config( 'artisanpack.ecommerce-storefront-livewire.storefront.routes_enabled', true ) ) {
            return;
        }

        $this->loadRoutesFrom( __DIR__ . '/../routes/storefront.php' );
        $this->loadRoutesFrom( __DIR__ . '/../routes/account.php' );
    }
}
