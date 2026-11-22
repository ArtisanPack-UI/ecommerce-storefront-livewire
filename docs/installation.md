---
title: Installation
menu_order: 20
---

# Installation

Everything you need to install the storefront and wire it into your application.

## In this section

- [Requirements](installation/requirements.md) - PHP, Laravel, Livewire, the engine, and optional packages
- [Configuration](installation/configuration.md) - Every configuration key and the store settings that override them
- [Front-End Setup](installation/front-end.md) - The design system, Tailwind sources, Vite entries, Stripe.js, and a Content Security Policy
- [Layouts](installation/layouts.md) - The package layout, your own Blade layout, or a component layout

## Install via Composer

```bash
composer require artisanpack-ui/ecommerce-storefront-livewire
```

The service provider is auto-discovered. The package has no migrations: it reads and writes the engine's tables through the engine's services.

## Run the install command

```bash
php artisan ecommerce-storefront:install
```

It:

1. Publishes the config to `config/artisanpack/ecommerce-storefront-livewire.php`. If that file already exists it asks before overwriting it; `--force` overwrites without asking.
2. Prints the Tailwind `@source` lines to add to your main stylesheet. The design system's lines and npm packages are in [Front-End Setup](installation/front-end.md).
3. Prints the line your own layout needs (`@include( 'ecommerce-storefront::partials.global' )`), the `@stack( 'head' )` reminder, and the wrapper view for component layouts. See [Layouts](installation/layouts.md).
4. Checks that the routes named in `auth.login_route` and `auth.register_route` exist. The host owns sign-in and registration; the storefront only links to them.
5. Checks that a payment gateway is registered and configured. The engine's Stripe gateway counts as configured once both `artisanpack.ecommerce.gateways.stripe.secret_key` and `publishable_key` are set; other gateways count once registered.
6. When `artisanpack-ui/visual-editor` is installed, says whether storefront pages render through its templates (`visual_editor.templates`).

The checks only warn. Nothing fails the install. See [Artisan Commands](reference/commands.md).

## Publishing

| Tag | Publishes to | Use it to |
| --- | --- | --- |
| `ecommerce-storefront-config` | `config/artisanpack/ecommerce-storefront-livewire.php` | Change defaults (the install command does this) |
| `ecommerce-storefront-views` | `resources/views/vendor/ecommerce-storefront` | Override Blade views |
| `ecommerce-storefront-lang` | `lang/vendor/ecommerce-storefront` | Override translations |

```bash
php artisan vendor:publish --tag=ecommerce-storefront-views
```

The config loads under `artisanpack.ecommerce-storefront-livewire`:

```php
config( 'artisanpack.ecommerce-storefront-livewire.storefront.route_prefix' ); // 'shop'
```

Views load under the `ecommerce-storefront::` namespace. Translations are not namespaced: they are JSON catalogues (`lang/{locale}.json`) keyed by their English text, as with `__( 'Add to cart' )`. A published catalogue in `lang/vendor/ecommerce-storefront` overrides the package's strings, and your app's own `lang/{locale}.json` overrides both. Prefer the [extension points](extending.md) over published views: a published view does not pick up fixes in later releases.

## Routes

The storefront registers two route groups:

| Group | Path prefix | Route names | Middleware |
| --- | --- | --- | --- |
| Storefront (catalog, product, search, cart, checkout, confirmation, order lookup) | `storefront.route_prefix` (`shop`) | `artisanpack.ecommerce.storefront.*` | `storefront.middleware` (`web`) |
| Customer account | `account.route_prefix` (`account`) | `artisanpack.ecommerce.account.*` | `account.middleware` (`web`, `auth`) |

Every route is a controller action returning a page view, so `php artisan route:cache` works. Set `storefront.routes_enabled` to `false` to register your own routes and embed the components; that turns off both groups. See [Embedding Components](extending/embedding-components.md).

## Uninstalling

The storefront registers itself with the engine's `SatelliteRegistry`. It owns no tables, columns, migrations, or product types, so it ships no uninstaller. When the engine marks the satellite uninstalled, the provider wires nothing: no routes, views, components, settings, or hooks.

## Next steps

- [Front-End Setup](installation/front-end.md) — required before the storefront is styled
- [Layouts](installation/layouts.md) — render the shop inside your site's layout
- [Configuration](installation/configuration.md) — every key you can tune
- [Getting Started](getting-started.md) — install to a seeded demo store
