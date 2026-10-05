<?php

/**
 * Ecommerce storefront (Livewire) configuration.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Storefront routes
    |--------------------------------------------------------------------------
    |
    | The public catalog, cart, and checkout routes. Set `routes_enabled` to
    | false to register your own routes and embed the Livewire components.
    |
    */
    'storefront' => [
        'routes_enabled' => true,
        'route_prefix'   => 'shop',
        'middleware'     => [ 'web' ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer account routes
    |--------------------------------------------------------------------------
    |
    | Order history, addresses, downloads, and preferences. These routes always
    | require an authenticated user; add `verified` here when needed.
    |
    */
    'account' => [
        'route_prefix' => 'account',
        'middleware'   => [ 'web', 'auth' ],
    ],
];
