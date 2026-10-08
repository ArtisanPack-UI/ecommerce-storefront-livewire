<?php

/**
 * Customer account routes.
 *
 * Paths sit under `account.route_prefix`, names under
 * `artisanpack.ecommerce.account.`, and every route runs
 * `account.middleware` (`web`, `auth` by default). The non-group entries
 * are also registered as Livewire persistent middleware, so update requests
 * from these pages are re-checked (spec §6).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\Http\Controllers\StorefrontPageController;
use Illuminate\Support\Facades\Route;

Route::prefix( trim( (string) config( 'artisanpack.ecommerce-storefront-livewire.account.route_prefix', 'account' ), '/' ) )
    ->middleware( (array) config( 'artisanpack.ecommerce-storefront-livewire.account.middleware', [ 'web', 'auth' ] ) )
    ->name( 'artisanpack.ecommerce.account.' )
    ->group( static function (): void {
        Route::get( '/', [ StorefrontPageController::class, 'accountDashboard' ] )->name( 'dashboard' );
        Route::get( 'orders', [ StorefrontPageController::class, 'accountOrders' ] )->name( 'orders.index' );
        Route::get( 'orders/{order}', [ StorefrontPageController::class, 'accountOrder' ] )->where( 'order', '[A-Za-z0-9_\-]+' )->name( 'orders.show' );
        Route::get( 'addresses', [ StorefrontPageController::class, 'accountAddresses' ] )->name( 'addresses' );
        Route::get( 'downloads', [ StorefrontPageController::class, 'accountDownloads' ] )->name( 'downloads' );
        Route::get( 'downloads/{download}', [ StorefrontPageController::class, 'accountDownloadFile' ] )->whereNumber( 'download' )->name( 'downloads.file' );
        Route::get( 'downloads/{download}/stream', [ StorefrontPageController::class, 'accountDownloadStream' ] )->whereNumber( 'download' )->name( 'downloads.stream' );
        Route::get( 'profile', [ StorefrontPageController::class, 'accountProfile' ] )->name( 'profile' );
        Route::get( 'claim', [ StorefrontPageController::class, 'accountClaim' ] )->name( 'claim' );
    } );
