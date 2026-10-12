# ArtisanPack UI Ecommerce Storefront (Livewire) Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package
follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-10-12

The first stable release of the Livewire storefront for the ArtisanPack UI
ecommerce engine. It runs on engine 1.1, Laravel 12 or 13, and Livewire 3.6+ or 4.
It calls the engine's services in-process and owns no tables of its own.
The package is licensed under MIT.

### Added

- **Routes and embeddable components:** storefront and account routes (prefixes configurable, `routes_enabled` to turn them off) whose page controller wraps each screen in a configurable layout. Every screen is also a Livewire component a host can mount in its own pages. A standalone layout ships for hosts without one.
- **Catalog:**
  - catalog, category, and tag pages with sort, pagination, and state kept in the URL;
  - filters with facet counts, and a filter drawer on small screens;
  - search with facets through the engine's `SearchProvider`, and a header search with live suggestions.
- **Product pages** for simple, variable, grouped, bundled, and digital products:
  - gallery, summary, details, quantity, and add to cart;
  - a variation picker with swatches;
  - reviews with a rating histogram and a submit form;
  - related products, upsells, and cross-sells.
- **Cart:** a cart page with coupons, totals, a shipping estimate, and unsellable lines called out; a header cart button and mini-cart; a slide-out cart drawer; a currency switcher; and a prompt to merge carts on login.
- **Checkout:**
  - a multi-step wizard or a single page, chosen by the store owner;
  - contact, address, shipping, payment, and review steps, with guest checkout rules and stock reservations;
  - optional account creation at checkout through a replaceable `CreatesCustomerAccounts` action;
  - an order confirmation page.
- **Payments:** a payment driver registry, a Stripe Payment Element driver with 3D Secure, and a generic redirect driver. Gateways that implement the engine's `RendersClientPayment` contract plug in without storefront changes. Card data never enters Livewire state.
- **Customer accounts:** dashboard and account navigation (including links other satellites add to the engine's `AccountMenuRegistry`), order history and detail, address book, downloads and licence keys, profile and notification preferences, and claiming guest orders.
- **Guest order lookup:** email plus order number opens a signed order page.
- **Visual-editor blocks** when `artisanpack-ui/visual-editor` is installed: Product Grid, Category Grid, Related Products, Recently Viewed, Single Product, Product Gallery, Price, Add to Cart, Reviews, Cart Contents, and Checkout Steps, plus editable product, category, cart, and checkout templates and patterns.
- **Display components:** price, money, stock status, rating summary, address, product card, and empty state.
- **SEO:** titles, descriptions, canonical URLs, Open Graph tags, and `Product` and `BreadcrumbList` JSON-LD. Search, cart, checkout, and account pages are `noindex`. With `artisanpack-ui/seo` installed, its schema collector prints the structured data.
- **Extension points:** registries for purchase forms and payment drivers, and filters and actions for catalog filters and sorts, product sections, cart sections, checkout steps, header actions, and more.
- **Performance:** listings load in a fixed number of queries, the category tree and filter counts are cached and cleared on catalog changes, related sections load lazily, and images carry a `srcset` from `artisanpack-ui/media-library` when it is installed.
- **Translations:** English, Spanish, French, and German, with a translation lint in CI.
- **Accessibility** targeting WCAG 2.2 AA: a skip link, focus management in the cart drawer and checkout, an error summary, live regions, a combobox header search, native radio swatches, and prices, stock, and ratings in text. Known issues in `artisanpack-ui/livewire-ui-components` markup are listed in the accessibility docs.
- **Command:** `ecommerce-storefront:install` publishes config, prints the Tailwind `@source` line and the layout include, checks for the host's login and register routes, and warns when no payment gateway is configured.
- **Satellite lifecycle:** the package registers with the engine's `SatelliteRegistry` and registers nothing while it is uninstalled.
- **Browser test suite** (Pest 4 and Playwright): browsing, adding to the cart, guest and registered checkout, the account, keyboard flows, and an axe check on every screen.
- **Releases** run the engine's satellite contract verification (`--allow-empty`: the storefront registers no engine contract implementations) and attach the signed report to the GitHub release. A tag whose report isn't signed gets no release.

### Security

- Placing an order carries a signed one-time action token, so a double submit places one order.
- Cart, checkout, review, search, currency, order-lookup, and guest-order-claim actions count toward the engine's rate limiters.
- Guest order pages use signed tokens and send `Referrer-Policy: same-origin`; cart, checkout, and account pages send `Cache-Control: no-store, private`.
- Store-owner HTML (product and category descriptions) is filtered in htmLawed's safe mode before it is printed.
