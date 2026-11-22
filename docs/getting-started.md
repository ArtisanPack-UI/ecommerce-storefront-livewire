---
title: Getting Started
menu_order: 10
---

# Getting Started

Get from `composer require` to a working shop. This assumes the `artisanpack-ui/ecommerce` engine is installed and migrated.

## 1. Install

```bash
composer require artisanpack-ui/ecommerce-storefront-livewire
php artisan ecommerce-storefront:install
```

The service provider is auto-discovered. The install command:

- publishes `config/artisanpack/ecommerce-storefront-livewire.php`;
- prints the Tailwind `@source` lines and the layout snippets;
- warns when your `login` or `register` route is missing, when no payment gateway is registered or configured, and when `artisanpack-ui/visual-editor` is installed but storefront templates are off.

Nothing it checks stops the install. See [Installation](installation.md).

## 2. Set up the front end

The package ships no CSS or JavaScript build. Your application's Vite build loads the ArtisanPack UI design system and scans the storefront's views. Install the design system:

```bash
npm install @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1
```

Add it and the printed `@source` lines to your main stylesheet (for example `resources/css/app.css`). The `@layer` line comes first:

```css
@layer theme, base, apui, components, utilities;
@import 'tailwindcss';
@import '@artisanpack-ui/css';

@source "../../vendor/artisanpack-ui/ecommerce-storefront-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-storefront-livewire/src/**/*.php";
```

Register the behavior engine in `resources/js/app.js`:

```js
import apuiBehavior from '@artisanpack-ui/behavior/alpine';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
```

Then build:

```bash
npm run build
```

See [Front-End Setup](installation/front-end.md) for the full setup, including the component library's `@source` lines and a Content Security Policy.

## 3. Pick a layout

Out of the box, pages use the package's own layout (`ecommerce-storefront::layouts.app`), with a header, search box, cart button, and footer. To render the shop inside your site's header and footer, set `storefront.layout` to your layout and add one include to it:

```blade
@include( 'ecommerce-storefront::partials.global' )
```

See [Layouts](installation/layouts.md), including the one-line wrapper for `<x-app-layout>`-style component layouts.

## 4. Seed a demo store (optional)

The engine ships a demo seeder that creates products, customers, and orders:

```bash
php artisan ecommerce:seed-demo
```

| Option | Default | Meaning |
| --- | --- | --- |
| `--products` | `50` | Number of products |
| `--orders` | `200` | Number of orders, placed over the last 90 days |
| `--seed` | random | Same seed, same store |
| `--fresh` | off | Empty the engine's data tables first |
| `--force` | off | Skip the confirmation prompt; required in production |

Do not run it against a live store.

## 5. Visit the shop

Open `/shop`. The prefix is `storefront.route_prefix` in the config. From there you can browse the catalog, open a product, add it to the cart, and check out. Signed-in customers find their orders, addresses, and downloads under `/account`.

To take payments, enable a gateway in the engine (for Stripe: `ECOMMERCE_STRIPE_ENABLED`, `ECOMMERCE_STRIPE_SECRET_KEY`, `ECOMMERCE_STRIPE_PUBLISHABLE_KEY`). See [Payment Drivers](checkout/payment-drivers.md).

## Next steps

- [Configuration](installation/configuration.md): every config key and store setting.
- [Layouts](installation/layouts.md): use your own layout.
- [Screens](usage/screens.md): what each page does.
- [Checkout](checkout.md) — layouts, guest checkout, and payment
- [Extending](extending.md) — hooks, embedding components, and Blade components
