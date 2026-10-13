<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\PaymentDriverRegistry;
use Livewire\Livewire;
use RuntimeException;
use Tests\Browser\Support\TestCardDriver;
use Tests\Fixtures\Gateways\FakeClientGateway;

/**
 * Base case for the browser suite.
 *
 * Serves the Testbench app from `tests/Browser/workbench/public`, where
 * `npm run build:browser` writes the asset bundle, loads that bundle in
 * the standalone storefront layout, seeds a small, repeatable demo store,
 * and registers a test-card gateway whose payment UI is
 * {@see TestCardDriver}, so checkout runs without a payment provider.
 */
abstract class BrowserTestCase extends TestCase
{
    /**
     * The workbench entries the storefront layout loads.
     *
     * @var array<int, string>
     */
    public const VITE_ENTRIES = [
        'tests/Browser/workbench/resources/css/app.css',
    ];

    /**
     * The test-card gateway every checkout pays with.
     *
     * @var FakeClientGateway
     */
    protected FakeClientGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        if ( ! is_file( public_path( 'build/manifest.json' ) ) ) {
            throw new RuntimeException( 'Build the browser asset bundle first: npm ci && npm run build:browser' );
        }

        addFilter( 'ap.ecommerceStorefrontLivewire.layout.viteEntries', static fn (): array => self::VITE_ENTRIES );

        $this->artisan( 'ecommerce:seed-demo', [ '--products' => 12, '--orders' => 5, '--seed' => 44, '--force' => true ] )->assertSuccessful();

        $this->gateway = new FakeClientGateway( 'test-card', 'Test card', TestCardDriver::DRIVER );

        app( PaymentGatewayRegistry::class )->register( 'test-card', $this->gateway );

        Livewire::component( 'storefront-test-card-driver', TestCardDriver::class );
        app( PaymentDriverRegistry::class )->register( TestCardDriver::DRIVER, 'storefront-test-card-driver' );
    }

    protected function tearDown(): void
    {
        removeAllFilters( 'ap.ecommerceStorefrontLivewire.layout.viteEntries' );

        parent::tearDown();
    }

    protected function defineEnvironment( $app ): void
    {
        parent::defineEnvironment( $app );

        $app->usePublicPath( __DIR__ . '/Browser/workbench/public' );

        $app['config']->set( 'app.name', 'Demo Store' );
        $app['config']->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    }
}
