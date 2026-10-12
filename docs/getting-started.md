---
title: Getting Started
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

Nothing it checks stops the install. See [Installation](Installation).

## 2. Add the storefront to Tailwind

The package ships no CSS or JavaScript build. Add the printed lines to your Tailwind entry (for example `resources/css/app.css`), next to the component library's views:

```css
@source "../../vendor/artisanpack-ui/ecommerce-storefront-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-storefront-livewire/src/**/*.php";
```

Then build:

```bash
npm run build
```

See [Front-End Setup](Installation-Front-End) for the full entry, including daisyUI.

## 3. Pick a layout

Out of the box, pages use the package's own layout (`ecommerce-storefront::layouts.app`), with a header, search box, cart button, and footer. To render the shop inside your site's header and footer, set `storefront.layout` to your layout and add one include to it:

```blade
@include( 'ecommerce-storefront::partials.global' )
```

See [Layouts](Installation-Layouts), including the one-line wrapper for `<x-app-layout>`-style component layouts.

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

To take payments, enable a gateway in the engine (for Stripe: `ECOMMERCE_STRIPE_ENABLED`, `ECOMMERCE_STRIPE_SECRET_KEY`, `ECOMMERCE_STRIPE_PUBLISHABLE_KEY`). See [Payment Drivers](Checkout-Payment-Drivers).

## Next steps

- [Configuration](Installation-Configuration) — every config key and store setting
- [Layouts](Installation-Layouts) — use your own layout
- [Screens](Usage-Screens) — what each page does
- [Checkout](Checkout) — layouts, guest checkout, and payment
- [Extending](Extending) — hooks, embedding components, and Blade components
