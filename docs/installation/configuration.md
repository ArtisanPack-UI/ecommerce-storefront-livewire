---
title: Configuration
---

# Configuration

`php artisan ecommerce-storefront:install` publishes the config to `config/artisanpack/ecommerce-storefront-livewire.php`. You can also publish it with `php artisan vendor:publish --tag=ecommerce-storefront-config`. It loads under the runtime key `artisanpack.ecommerce-storefront-livewire`:

```php
config( 'artisanpack.ecommerce-storefront-livewire.catalog.per_page' ); // 24
```

Every key has a default, so you only need the keys you change. Some choices are also [store settings](#store-settings) that the admin can change without touching config.

## Storefront routes

| Key | Default | What it controls |
| --- | --- | --- |
| `storefront.routes_enabled` | `true` | Register the storefront and account routes. Set to `false` to register your own routes and embed the Livewire components. This turns off both route groups, and the storefront then no longer points the engine's guest order links (`artisanpack.ecommerce.checkout.order_view_url`) at its order page. |
| `storefront.route_prefix` | `'shop'` | URL prefix for the catalog, product, search, cart, checkout, confirmation, and order lookup pages. Leading and trailing slashes are trimmed. |
| `storefront.middleware` | `[ 'web' ]` | Middleware on every storefront route. |
| `storefront.layout` | `'ecommerce-storefront::layouts.app'` | The view every storefront and account page extends. Point it at your own layout to adopt its header and footer. An empty value falls back to the package layout. See [Layouts](Installation-Layouts). |
| `storefront.rtl_locales` | `[ 'ar', 'he', 'fa', 'ur' ]` | Languages written right to left. The standalone layout sets `<html dir="rtl">` when the app locale's language is in this list (`ar_EG` and `ar-EG` follow `ar`). A host layout can do the same with `Support\TextDirection::for()`; see [Layouts](Installation-Layouts). |

Route names are always `artisanpack.ecommerce.storefront.*` (for example `artisanpack.ecommerce.storefront.product`).

When the storefront registers its routes and `artisanpack.ecommerce.checkout.order_view_url` is empty, the provider sets that engine key to `{APP_URL}/{route_prefix}/order/{token}`, so links in guest confirmation emails open the storefront's signed order page. A value you set yourself is left alone.

The link is built from `app.url`, not from the request that boots the app: the engine bakes it into queued emails, and a payment webhook can arrive on an internal host or plain `http`. Set `APP_URL` to the store's public URL (scheme and host). Only when `app.url` is empty does the current request's URL stand in.

## Customer account routes

| Key | Default | What it controls |
| --- | --- | --- |
| `account.route_prefix` | `'account'` | URL prefix for the account dashboard, orders, addresses, downloads, profile, and order claim pages. |
| `account.middleware` | `[ 'web', 'auth' ]` | Middleware on every account route. Add `verified` here if you need it. Every entry is also registered as Livewire persistent middleware, so it runs again on each Livewire update from an account page. A middleware group of your own is expanded into its members; `web`, and any group Livewire's update route already runs, are left out. |
| `account.claim_requires_verified_email` | `true` | Who may claim guest orders. A claim attaches every unclaimed guest order placed with the account's email, so by default only users whose model implements `MustVerifyEmail` and who have verified may claim. Set to `false` only when your user model doesn't verify emails and you accept that any account can claim orders placed with its address. A `MustVerifyEmail` user who hasn't verified is refused either way. |

Route names are always `artisanpack.ecommerce.account.*` (for example `artisanpack.ecommerce.account.orders.index`).

## Customer authentication

The host owns sign-in, registration, and the profile and password screens. A route name that doesn't exist hides its link.

| Key | Default | What it controls |
| --- | --- | --- |
| `auth.login_route` | `'login'` | Sign-in link in the header, at checkout, on the order lookup page, and on product reviews |
| `auth.register_route` | `'register'` | "Create an account" links at checkout and on the order confirmation page |
| `auth.profile_route` | `'settings.profile'` | The host's profile screen, linked from the account dashboard and profile page |
| `auth.password_route` | `'settings.password'` | The host's password screen, linked from the same places |
| `auth.create_account_action` | `ArtisanPackUI\EcommerceStorefrontLivewire\Actions\CreateCustomerAccount` | The class that creates an account at checkout. It must implement `Contracts\CreatesCustomerAccounts`; any other value turns account creation at checkout off. See [Account Creation](Checkout-Account-Creation). |

## Catalog

| Key | Default | What it controls |
| --- | --- | --- |
| `catalog.per_page` | `24` | Products per page when the URL does not say otherwise. A value not in `per_page_values` falls back to the smallest offered size. |
| `catalog.per_page_values` | `[ 12, 24, 48 ]` | The page sizes shoppers can choose. Values outside 1–100 are dropped. |
| `catalog.default_sort` | `'newest'` | The default sort: `newest`, `price`, `-price`, `popularity`, `rating`, or `name`. A key the `ap.ecommerceStorefrontLivewire.catalog.sorts` filter removed falls back to the first sort offered. |
| `catalog.show_stock_count` | `false` | Low-stock products say "Only 3 left" instead of "Low stock". The engine's `artisanpack.ecommerce.inventory.show_quantity` must be on for the count to be known. |
| `catalog.filter_attributes` | `null` | The attribute keys shoppers can filter by, for example `[ 'colour', 'size' ]`. `null` offers every attribute. |

## Reviews

| Key | Default | What it controls |
| --- | --- | --- |
| `reviews.per_page` | `5` | Approved reviews per page on the product page. Clamped to 1–50. |

Who may review, and moderation, are engine settings (`artisanpack.ecommerce.reviews`).

## Related products

| Key | Default | What it controls |
| --- | --- | --- |
| `related.limit` | `4` | How many related products, upsells, and cart cross-sells to suggest. Clamped to 1–12. |

## Cart

| Key | Default | What it controls |
| --- | --- | --- |
| `cart.after_add` | `'drawer'` | What happens after "Add to cart" on the product page or a product card's quick add: `drawer` opens the cart drawer, `toast` shows a toast, `none` only updates the cart count. |

## Checkout

| Key | Default | What it controls |
| --- | --- | --- |
| `checkout.layout` | `'multi_step'` | `multi_step` (one step at a time) or `single_page` (every section on one page). Any other value means `multi_step`. Overridden by the `storefront.checkout_layout` [store setting](#store-settings). See [Checkout Layouts](Checkout-Layouts). |
| `checkout.terms_url` | `null` | The terms and conditions page shoppers must accept before placing an order: an `http(s)` URL or a path on this site (such as `/terms`). `null`, or any other value, shows no checkbox. Overridden by the `storefront.terms_url` [store setting](#store-settings). |

## Guest order lookup

| Key | Default | What it controls |
| --- | --- | --- |
| `order_lookup.link_ttl_minutes` | `60` | How long the order link opened by a successful lookup keeps working (at least 1). Links in guest confirmation emails follow the engine's `artisanpack.ecommerce.checkout.order_view_ttl_days` instead. |

## Payment drivers

| Key | Default | What it controls |
| --- | --- | --- |
| `payments.drivers` | `[]` | Extra payment drivers: `driver => Livewire component` (name or class), keyed by the `driver` a gateway's client config names. The core `redirect` and `stripe-payment-element` drivers are always registered; an entry with the same key replaces one. |

```php
'payments' => [
    'drivers' => [
        'my-gateway-element' => App\Livewire\Payments\MyGatewayElement::class,
    ],
],
```

See [Payment Drivers](Checkout-Payment-Drivers).

## Performance

| Key | Default | What it controls |
| --- | --- | --- |
| `performance.cache_ttl` | `600` | Seconds the category tree and catalog filter counts stay cached. They are also cleared whenever a product, category, tag, or stock level changes. `0` turns the cache off; do this when the `ap.ecommerce.product.listQuery` filter shows different shoppers different products. |

See [Performance](Advanced-Performance).

## Visual editor

Used only when `artisanpack-ui/visual-editor` is installed.

| Key | Default | What it controls |
| --- | --- | --- |
| `visual_editor.blocks` | `true` | Register the commerce blocks and patterns with the editor. |
| `visual_editor.templates` | `false` | Render storefront pages through saved visual-editor templates. Off by default so installing the editor doesn't change an existing store. Also needs the blocks and `artisanpack-ui/cms-framework`. |

See [Visual Editor](Visual-Editor).

## Store settings

Some storefront behaviour is a store setting: a value the store owner changes from the admin's Settings screen (`artisanpack-ui/ecommerce-admin-livewire`), stored by the engine's settings repository. The storefront reads these through `ecommerceSetting()`. A stored value wins; without one, the setting falls back to config.

The storefront adds two settings to the engine's **Checkout** group:

| Setting | Admin label | Falls back to | Effect |
| --- | --- | --- | --- |
| `storefront.checkout_layout` | Checkout layout | `checkout.layout` (this package) | Multi-step or single-page checkout |
| `storefront.terms_url` | Terms and conditions page | `checkout.terms_url` (this package) | The terms checkbox at checkout. Only an `http(s)` URL or a site path (not `//host`) is accepted. |

The storefront also follows these engine settings. Engine settings fall back to the same key under `artisanpack.ecommerce`:

| Setting | Falls back to | Effect in the storefront |
| --- | --- | --- |
| `checkout.guest_checkout` | `artisanpack.ecommerce.checkout.guest_checkout` (`allowed`) | `allowed`: guests check out freely. `required_account`: guests must create an account during checkout. `disabled`: shoppers must sign in. See [Guest Checkout](Checkout-Guest-Checkout). |
| `checkout.account_creation` | `artisanpack.ecommerce.checkout.account_creation` | Offer guests an optional "create an account" box at checkout. See [Account Creation](Checkout-Account-Creation). |
| `base_currency` | `artisanpack.ecommerce.base_currency` | The currency `<x-artisanpack-ec-money>` uses when an amount has none of its own |
| `digital.download_limit` | `artisanpack.ecommerce.digital.download_limit` | Downloads per link, shown on digital products that don't set their own |
| `licenses.activations_limit` | `artisanpack.ecommerce.licenses.activations_limit` | Activations per licence key, shown on digital products that don't set their own |

The engine applies stored settings to the config repository, so a `config()` read of these engine keys also sees the stored value.

## Settings that live elsewhere

| Engine key | Effect in the storefront |
| --- | --- |
| `artisanpack.ecommerce.inventory.show_quantity` | Needed for `catalog.show_stock_count` to show a count |
| `artisanpack.ecommerce.reviews.require_purchase` | Only shoppers who bought the product can review it |
| `artisanpack.ecommerce.checkout.order_view_ttl_days` | How long order links in guest confirmation emails work |
| `artisanpack.ecommerce.currency.cookie` | The cookie the currency switcher stores the shopper's currency in |
| `artisanpack.ecommerce.store.country` | The default country in address forms |
| `artisanpack.ecommerce.gateways.stripe.*` | Stripe keys; see [Payment Drivers](Checkout-Payment-Drivers) |
