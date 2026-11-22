---
title: Overview
menu_order: 0
---

# Overview

The customer-facing Livewire storefront for the `artisanpack-ui/ecommerce` engine: catalog, product pages, cart, checkout, customer accounts, guest order lookup, and search, built on the ArtisanPack UI design system through `artisanpack-ui/livewire-ui-components`.

It registers storefront and account routes that work right after install, and every screen is also a Livewire component you can mount on your own pages. It calls the engine's services in-process and owns no tables of its own.

## Features

- **Every shopper screen**: Catalog with filters, sorting, and pagination; category and tag pages; product pages for simple, variable, grouped, bundled, and digital products; reviews; related products, upsells, and cross-sells; search with facets and a live header search.
- **Rich product options**: Swatches, choice cards with images, price differences in the shopper's currency, and a live price for the chosen options. Sold-out products offer a "Notify me" back-in-stock signup.
- **Cart everywhere**: A cart page with coupons and a shipping estimate, a header cart button, and a slide-out cart drawer with a free-shipping meter.
- **Checkout two ways**: A multi-step wizard or a single page, chosen by the store owner, with guest checkout rules and optional account creation.
- **Pluggable payments**: A Stripe Payment Element driver, a redirect driver, and a registry for other gateways' payment UIs. Card data never touches Livewire state.
- **Customer accounts**: Order history and detail, address book, downloads and licence keys, profile and notification preferences, and claiming guest orders.
- **Guest order lookup**: Email plus order number opens a signed order view.
- **Visual-editor blocks**: Commerce blocks and editable product, category, cart, and checkout templates when `artisanpack-ui/visual-editor` is installed.
- **Search engines**: Titles, descriptions, canonical URLs, Open Graph tags, and `Product` and `BreadcrumbList` JSON-LD.
- **Embeddable**: Turn the routes off and mount the components in your own pages.
- **Extension points**: Registries for purchase forms and payment drivers, and filters for catalog filters and sorts, product sections, cart sections, checkout steps, header actions, the store navigation, and more.
- **Accessible and translated**: Targets WCAG 2.2 AA, checked with axe and keyboard tests in a real browser, with light and dark themes and right-to-left layouts. Ships English, Spanish, French, German, Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic.

## Requirements

PHP 8.3+ with `ext-intl`, Laravel 12 or 13, Livewire 3.6+ or 4, the `artisanpack-ui/ecommerce` engine 1.1 or later, and a Vite build with Tailwind CSS 4 and the ArtisanPack UI design system. See [Requirements](installation/requirements.md).

## Quick install

```bash
composer require artisanpack-ui/ecommerce-storefront-livewire
php artisan ecommerce-storefront:install
php artisan ecommerce:seed-demo   # optional demo store
```

Then set up the front end (see [Front-End Setup](installation/front-end.md)) and open `/shop`.

## Next steps

- [Getting started](getting-started.md): from `composer require` to a working shop.
- [Installation](installation.md): requirements, configuration, the front-end build, and layouts.
- [Usage](usage.md): every storefront and account screen.
- [Checkout](checkout.md): layouts, guest checkout, account creation, and payment drivers.
- [Extending](extending.md): embed the components and change the storefront from your own package.
- [Reference](reference.md): hooks, Artisan commands, configuration, and components.
- [Upgrading](upgrading.md): moving from 1.x to 2.0.
