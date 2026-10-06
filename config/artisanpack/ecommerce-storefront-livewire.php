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
    | `layout` is the view every storefront page extends. Point it at your
    | own layout to adopt its header and footer; it must yield `title` and
    | `content` and include `ecommerce-storefront::partials.global`.
    |
    */
    'storefront' => [
        'routes_enabled' => true,
        'route_prefix'   => 'shop',
        'middleware'     => [ 'web' ],
        'layout'         => 'ecommerce-storefront::layouts.app',
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

    /*
    |--------------------------------------------------------------------------
    | Customer authentication
    |--------------------------------------------------------------------------
    |
    | The host owns sign-in and registration. The storefront links to these
    | route names and creates accounts at checkout through the action class.
    |
    */
    'auth' => [
        'login_route'           => 'login',
        'register_route'        => 'register',
        'create_account_action' => 'ArtisanPackUI\\EcommerceStorefrontLivewire\\Actions\\CreateCustomerAccount',
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    |
    | Page sizes and the default sort for product listings. With
    | `show_stock_count`, low-stock products say "Only 3 left" (the engine's
    | `inventory.show_quantity` must be on for the count to be known).
    |
    */
    'catalog' => [
        'per_page'         => 24,
        'per_page_values'  => [ 12, 24, 48 ],
        'default_sort'     => 'newest',
        'show_stock_count' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | `multi_step` or `single_page`. The engine setting
    | `storefront.checkout_layout` overrides this when set.
    |
    */
    'checkout' => [
        'layout' => 'multi_step',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment drivers
    |--------------------------------------------------------------------------
    |
    | Extra payment driver classes. The core drivers are always registered.
    |
    */
    'payments' => [
        'drivers' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Visual editor
    |--------------------------------------------------------------------------
    |
    | Used only when artisanpack-ui/visual-editor is installed. `templates`
    | renders storefront pages through visual-editor templates; it is off by
    | default so installing the editor doesn't change an existing store.
    |
    */
    'visual_editor' => [
        'blocks'    => true,
        'templates' => false,
    ],
];
