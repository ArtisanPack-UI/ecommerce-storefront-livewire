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
    | The host owns sign-in, registration, and the profile and password
    | screens. The storefront links to these route names (a missing route
    | hides its link) and creates accounts at checkout through the action
    | class, which implements Contracts\CreatesCustomerAccounts.
    |
    */
    'auth' => [
        'login_route'           => 'login',
        'register_route'        => 'register',
        'profile_route'         => 'settings.profile',
        'password_route'        => 'settings.password',
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
    | `filter_attributes` lists the attribute keys shoppers can filter by
    | (e.g. [ 'colour', 'size' ]); null offers every attribute.
    |
    */
    'catalog' => [
        'per_page'          => 24,
        'per_page_values'   => [ 12, 24, 48 ],
        'default_sort'      => 'newest',
        'show_stock_count'  => false,
        'filter_attributes' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    |
    | Approved reviews shown per page on the product page. Who may review,
    | and moderation, are engine settings (`artisanpack.ecommerce.reviews`).
    |
    */
    'reviews' => [
        'per_page' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Related products
    |--------------------------------------------------------------------------
    |
    | How many related products, upsells, and cart cross-sells to suggest
    | (1–12).
    |
    */
    'related' => [
        'limit' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    |
    | What happens after "Add to cart" (product page or a product card's
    | quick add): `drawer` opens the cart drawer, `toast` shows a toast,
    | `none` only updates the cart count.
    |
    */
    'cart' => [
        'after_add' => 'drawer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | `layout` is `multi_step` or `single_page`. `terms_url` is the terms
    | and conditions page shoppers must accept before placing an order (an
    | http(s) URL or a path on this site; null shows no checkbox). The
    | engine settings `storefront.checkout_layout` and `storefront.terms_url`
    | (the admin's Checkout settings) override these when set.
    |
    */
    'checkout' => [
        'layout'    => 'multi_step',
        'terms_url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest order lookup
    |--------------------------------------------------------------------------
    |
    | `link_ttl_minutes` is how long the order link a successful lookup
    | opens keeps working. Links in guest confirmation emails follow the
    | engine's `checkout.order_view_ttl_days` instead.
    |
    */
    'order_lookup' => [
        'link_ttl_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment drivers
    |--------------------------------------------------------------------------
    |
    | Extra payment drivers: `driver => Livewire component` (name or class),
    | keyed by the `driver` a gateway's client config names. The core
    | `redirect` and `stripe-payment-element` drivers are always registered;
    | an entry here with the same key replaces one.
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
