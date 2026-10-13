---
title: ArtisanPack UI Ecommerce Storefront (Livewire) Documentation Home
---

# ArtisanPack UI Ecommerce Storefront (Livewire) Documentation

Welcome to the documentation for the ArtisanPack UI Ecommerce Storefront. The package is the customer-facing Livewire storefront for the `artisanpack-ui/ecommerce` engine: catalog, product pages, cart, checkout, customer accounts, guest order lookup, and search, built from `artisanpack-ui/livewire-ui-components`.

It registers storefront and account routes that work right after install, and every screen is also a Livewire component you can embed in your own pages. It calls the engine's services in-process and owns no tables of its own.

## Table of Contents

- **Getting Started**
  - [Quick Start Guide](Getting-Started)

- **Installation**
  - [Installation Overview](Installation)
  - [Requirements](Installation-Requirements)
  - [Configuration](Installation-Configuration)
  - [Front-End Setup](Installation-Front-End)
  - [Layouts](Installation-Layouts)

- **Usage**
  - [Usage Overview](Usage)
  - [Screens](Usage-Screens)

- **Checkout**
  - [Checkout Overview](Checkout)
  - [Checkout Layouts](Checkout-Layouts)
  - [Guest Checkout](Checkout-Guest-Checkout)
  - [Account Creation at Checkout](Checkout-Account-Creation)
  - [Payment Drivers](Checkout-Payment-Drivers)

- **Visual Editor**
  - [Visual Editor Overview](Visual-Editor)
  - [Blocks](Visual-Editor-Blocks)
  - [Templates and Patterns](Visual-Editor-Templates)

- **Extending**
  - [Extending Overview](Extending)
  - [Embedding Components](Extending-Embedding-Components)
  - [Blade Components](Extending-Blade-Components)
  - [Hooks Reference](Extending-Hooks-Reference)

- **Quality**
  - [Accessibility](Accessibility)
  - [Localization](Localization)
  - [SEO](Seo)

- **Advanced**
  - [Advanced Overview](Advanced)
  - [Artisan Commands](Advanced-Artisan-Commands)
  - [Performance](Advanced-Performance)
  - [Browser Tests](Advanced-Browser-Tests)

## Features

- **Every shopper screen**: Catalog with filters, sorting, and pagination; category and tag pages; product pages for simple, variable, grouped, bundled, and digital products; reviews; related products, upsells, and cross-sells; search with facets and a live header search.
- **Cart everywhere**: A cart page with coupons and a shipping estimate, a header cart button, and a slide-out cart drawer.
- **Checkout two ways**: A multi-step wizard or a single page, chosen by the store owner, with guest checkout rules and optional account creation.
- **Pluggable payments**: A Stripe Payment Element driver, a redirect driver, and a registry for other gateways' payment UIs. Card data never touches Livewire state.
- **Customer accounts**: Order history and detail, address book, downloads and licence keys, profile and notification preferences, and claiming guest orders.
- **Guest order lookup**: Email plus order number opens a signed order view.
- **Visual-editor blocks**: Commerce blocks and editable product, category, cart, and checkout templates when `artisanpack-ui/visual-editor` is installed.
- **Embeddable**: Turn the routes off and mount the components in your own pages.
- **Extension points**: Registries for purchase forms and payment drivers, and filters for catalog filters and sorts, product sections, cart sections, checkout steps, header actions, and more.
- **Accessible and translated**: Targets WCAG 2.2 AA, checked with axe and keyboard tests in a real browser. Ships English, Spanish, French, and German.

## Quick Example

```bash
composer require artisanpack-ui/ecommerce-storefront-livewire
php artisan ecommerce-storefront:install
php artisan ecommerce:seed-demo   # optional demo store
```

Add the package's views to your Tailwind sources (the install command prints the line), then open `/shop`.

## Support

For support, please open an issue on the [GitHub repository](https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire).

## License

This package is open-source software licensed under the MIT license.
