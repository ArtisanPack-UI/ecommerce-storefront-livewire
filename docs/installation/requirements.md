---
title: Requirements
---

# Requirements

## Core requirements

- **PHP 8.3+** with the `intl` extension
- **Laravel 12 or 13**
- **Livewire 3.6+ or 4**

Composer installs these ArtisanPack UI packages with it:

| Package | Version | Why |
| --- | --- | --- |
| `artisanpack-ui/ecommerce` | `^1.1` | The engine: catalog queries, carts, checkout, orders, payment gateways, settings |
| `artisanpack-ui/livewire-ui-components` | `^2.1` | Every input, button, drawer, modal, and toast |
| `artisanpack-ui/core` | `^1.0` | Shared helpers |
| `artisanpack-ui/hooks` | `^1.2` | The filters used for [extension](Extending) |
| `artisanpack-ui/security` | `^1.0.3` or `^2.0` | Output escaping and sanitization |

## Front-end requirements

The package ships no build. Your application needs:

- **Tailwind CSS 4** with **daisyUI 5** (pin `daisyui` to `~5.0`; see [Front-End Setup](Installation-Front-End))
- **Vite** with `laravel-vite-plugin`

The storefront needs no npm packages of its own. Alpine comes with Livewire, and Stripe.js loads from Stripe at runtime.

## Authentication routes

The host owns sign-in, registration, and the profile and password screens. The storefront links to these route names:

| Config key | Default | Used for |
| --- | --- | --- |
| `auth.login_route` | `login` | Header "Sign in" link, checkout sign-in, order lookup, reviews |
| `auth.register_route` | `register` | Checkout and order confirmation "create an account" links |
| `auth.profile_route` | `settings.profile` | Links from the account dashboard and profile screen |
| `auth.password_route` | `settings.password` | Links from the account dashboard and profile screen |

A missing route hides its link. A Laravel starter kit provides all four names. `php artisan ecommerce-storefront:install` warns when the login or register route is missing. See [Configuration](Installation-Configuration#customer-authentication).

## Payment gateway

Shoppers can't pay until the engine has a payment gateway registered and configured. The engine ships Stripe (`ECOMMERCE_STRIPE_ENABLED`, `ECOMMERCE_STRIPE_SECRET_KEY`, `ECOMMERCE_STRIPE_PUBLISHABLE_KEY`). See [Payment Drivers](Checkout-Payment-Drivers).

## Optional packages

The storefront detects these at runtime and uses them when present:

| Package | Minimum version | What it adds |
| --- | --- | --- |
| `artisanpack-ui/visual-editor` | `1.8` (older versions conflict) | Commerce blocks (product grid, single product, add to cart, cart contents, checkout steps, and more) and block patterns. With `visual_editor.templates` on and `artisanpack-ui/cms-framework` installed, editable product, category, tag, catalog, cart, checkout, and search templates. See [Visual Editor](Visual-Editor). |
| `artisanpack-ui/media-library` | — | Responsive product, variant, and category images with a `srcset` from the library's image sizes. Without it, images come from http(s) image URLs. |
| `artisanpack-ui/seo` | `1.4` | The storefront's JSON-LD goes to the SEO package's schema collector, printed by `<x-seo:schema />`. Meta tags are still rendered by the storefront. See [SEO](Seo). |
| `artisanpack-ui/cms-framework` | `2.11` (required by visual-editor 1.8) | Editable storefront templates: with `visual_editor.templates` on, pages resolve saved templates through its template resolver and active theme. |
| `artisanpack-ui/livewire-drag-and-drop` | — | Reorderable lists for satellites that need them, such as a wishlist. The storefront itself does not use it. |
