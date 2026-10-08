<?php

/**
 * Storefront routes.
 *
 * Every route is a controller action returning a page view, so the routes
 * survive `route:cache`. Paths sit under `storefront.route_prefix`, names
 * under `artisanpack.ecommerce.storefront.`, and every route runs
 * `storefront.middleware` (spec §5.4).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\Http\Controllers\StorefrontPageController;
use Illuminate\Support\Facades\Route;

Route::prefix( trim( (string) config( 'artisanpack.ecommerce-storefront-livewire.storefront.route_prefix', 'shop' ), '/' ) )
    ->middleware( (array) config( 'artisanpack.ecommerce-storefront-livewire.storefront.middleware', [ 'web' ] ) )
    ->name( 'artisanpack.ecommerce.storefront.' )
    ->group( static function (): void {
        Route::get( '/', [ StorefrontPageController::class, 'catalog' ] )->name( 'catalog' );

        // `{path}` is the category's slug chain, e.g. `clothing/shirts`.
        Route::get( 'category/{path}', [ StorefrontPageController::class, 'category' ] )
            ->where( 'path', '[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*' )
            ->name( 'category' );

        Route::get( 'tag/{tag}', [ StorefrontPageController::class, 'tag' ] )->where( 'tag', '[A-Za-z0-9_\-]+' )->name( 'tag' );
        Route::get( 'products/{product}', [ StorefrontPageController::class, 'product' ] )->where( 'product', '[A-Za-z0-9_\-]+' )->name( 'product' );
        Route::get( 'search', [ StorefrontPageController::class, 'search' ] )->name( 'search' );
        Route::get( 'cart', [ StorefrontPageController::class, 'cart' ] )->name( 'cart' );
        Route::get( 'checkout', [ StorefrontPageController::class, 'checkout' ] )->name( 'checkout' );
        Route::get( 'checkout/return', [ StorefrontPageController::class, 'checkoutReturn' ] )->name( 'checkout.return' );
        Route::get( 'orders/{order}/confirmation', [ StorefrontPageController::class, 'confirmation' ] )->where( 'order', '[A-Za-z0-9_\-]+' )->name( 'confirmation' );
        Route::get( 'order-lookup', [ StorefrontPageController::class, 'lookup' ] )->name( 'lookup' );

        // `{token}` is the engine's signed order-view token (`{id}-{expiry}-{hmac}`).
        Route::get( 'order/{token}', [ StorefrontPageController::class, 'orderView' ] )->where( 'token', '[0-9]+-[0-9]+-[a-f0-9]{64}' )->name( 'order-view' );
    } );
