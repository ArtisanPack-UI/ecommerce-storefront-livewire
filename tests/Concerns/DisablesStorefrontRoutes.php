<?php

declare( strict_types=1 );

namespace Tests\Concerns;

/**
 * Boots the package with `storefront.routes_enabled` off.
 */
trait DisablesStorefrontRoutes
{
    /**
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     */
    protected function defineEnvironment( $app ): void
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'artisanpack.ecommerce-storefront-livewire.storefront.routes_enabled', false );
    }
}
