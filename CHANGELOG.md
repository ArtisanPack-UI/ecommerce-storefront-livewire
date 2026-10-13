# ArtisanPack UI Ecommerce Storefront (Livewire) Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package
follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-10-13

The first stable release of the Livewire storefront for the ArtisanPack UI
ecommerce engine. It runs on engine 1.1, Laravel 12 or 13, and Livewire 3.6+ or 4.
It calls the engine's services in-process and owns no tables of its own.
The package is licensed under MIT. It requires PHP 8.3+ with `ext-intl`.

### Added

- **Routes and embeddable components:** storefront and account routes (prefixes configurable, `routes_enabled` to turn them off) whose page controller wraps each screen in a configurable layout. Every screen is also a Livewire component a host can mount in its own pages. A standalone layout ships for hosts without one. Custom middleware groups in `account.middleware` are re-run on Livewire updates, and malformed query strings render the page instead of erroring.
- **Catalog:**
  - catalog, category, and tag pages with sort, pagination, and state kept in the URL;
  - filters with facet counts, and a filter drawer on small screens;
  - search with facets through the engine's `SearchProvider`, and a header search with live suggestions;
  - prices shown for the signed-in customer, a price slider whose step follows the currency, and category counts of distinct products;
  - products of uninstalled product types are left out.
- **Product pages** for simple, variable, grouped, bundled, and digital products:
  - gallery, summary, details, quantity, and add to cart;
  - a variation picker with swatches;
  - reviews with a rating histogram and a submit form;
  - related products, upsells, and cross-sells;
  - quick add from product cards for simple products.
- **Cart:** a cart page with coupons, totals, a shipping estimate, "Only N left" notes, and unsellable lines called out; a header cart button and mini-cart; a lazily loaded slide-out cart drawer; a currency switcher that follows the open cart; and a prompt to merge carts on login that warns when the guest cart has expired.
- **Checkout:**
  - a multi-step wizard or a single page, chosen by the store owner;
  - contact, address, shipping, review, and payment steps, in that order, with guest checkout rules and stock reservations;
  - the order is placed when the payment is confirmed (or on "Place order" for a free order), and steps can't be changed after that;
  - when the payment webhook places the order first, the shopper still lands on the confirmation and the reviewed order note is kept; an order held for fraud review shows a "reviewing" notice on the confirmation;
  - satellite steps can declare a server-side `complete` callable; unreachable steps are ignored, and every step is re-checked before the order is placed;
  - optional account creation at checkout through a replaceable `CreatesCustomerAccounts` action, which never takes over an existing customer record for an unverified email;
  - marketing consent stored on the order (`meta.marketing_consent`) and on the customer's `accepts_marketing`, including for webhook-placed orders;
  - an order confirmation page.
- **Payments:** a payment driver registry, a Stripe Payment Element driver with 3D Secure, and a generic redirect driver. Gateways that implement the engine's `RendersClientPayment` contract plug in without storefront changes. Card data never enters Livewire state.
- **Customer accounts:** dashboard and account navigation (including links other satellites add to the engine's `AccountMenuRegistry`), order history and detail, address book, downloads and licence keys, profile and notification preferences, and claiming guest orders. Claiming requires a verified email by default (`account.claim_requires_verified_email`).
- **Guest order lookup:** email plus order number opens a signed order page. Order-view links are built from `app.url`.
- **Visual-editor blocks** when `artisanpack-ui/visual-editor` is installed: Product Grid, Category Grid, Related Products, Recently Viewed, Single Product, Product Gallery, Price, Add to Cart, Reviews, Cart Contents, and Checkout Steps, plus editable product, category, cart, and checkout templates and patterns.
- **Blade components** (`x-artisanpack-ec-*`): price, price range, money, stock status, rating summary and input, address and address form, product card, gallery, swatches, quantity, category grid, search box, skeleton, empty state, account shell, and `field`, which links a control's error and hint to it.
- **SEO:** titles, descriptions, canonical URLs, Open Graph tags, and `Product` and `BreadcrumbList` JSON-LD. Search, cart, checkout, and account pages are `noindex`. With `artisanpack-ui/seo` installed, its schema collector prints the structured data.
- **Extension points:** registries for purchase forms and payment drivers, and filters and actions for catalog filters and sorts, product sections, cart sections, checkout steps, header actions, and more.
- **Performance:** listings load in a fixed number of queries with product media preloaded, the category tree and filter counts are cached and cleared on catalog changes, related sections and the cart drawer load lazily, and images carry a `srcset` from `artisanpack-ui/media-library` when it is installed.
- **Translations:** English, Spanish, French, and German, with a translation lint in CI.
- **Accessibility** targeting WCAG 2.2 AA:
  - a skip link, live regions, a combobox header search, native radio swatches, and prices, stock, and ratings in text;
  - field errors and hints linked to their fields, and error summaries on every form; a refused add to cart is announced;
  - focus management in the cart drawer and checkout, and focus kept when the focused control is removed;
  - a named product gallery that announces image changes, and a visible focus ring on the rating input;
  - on phones, a search link, no sideways scroll at 320px, and no hidden tab stops;
  - link buttons do full page loads;
  - right-to-left layouts for the locales in `storefront.rtl_locales`.

  Known issues in `artisanpack-ui/livewire-ui-components` markup are listed in the accessibility docs.
- **Command:** `ecommerce-storefront:install` publishes config, prints the Tailwind `@source` lines and the layout include, checks for the host's login and register routes, and warns when no payment gateway is configured.
- **Satellite lifecycle:** the package registers with the engine's `SatelliteRegistry` and registers nothing while it is uninstalled.
- **Browser test suite** (Pest 4 and Playwright): browsing, adding to the cart, guest and registered checkout, the account, keyboard flows, and an axe check on every screen.
- **Packaging:** `artisanpack-ui/security` `^1.0.3|^2.0`; conflicts with `artisanpack-ui/visual-editor` below 1.8; suggests `artisanpack-ui/seo` and `artisanpack-ui/cms-framework`.
- **Releases** run the engine's satellite contract verification (`--allow-empty`: the storefront registers no engine contract implementations) and attach the signed report to the GitHub release. A tag whose report isn't signed, or that doesn't match the provider's `VERSION` and a CHANGELOG section, gets no release. Each release imports `docs/` and this changelog into the documentation site.

### Security

- Placing an order carries a signed one-time action token, so a double submit places one order.
- Cart, checkout, address, review, search, currency, order-lookup, and guest-order-claim actions count toward the engine's rate limiters.
- Download and stream links are temporary signed URLs, valid for 10 minutes and 4 hours.
- Guest order pages use signed tokens and send `Referrer-Policy: same-origin`; cart, checkout, and account pages send `Cache-Control: no-store, private`.
- Store-owner HTML (product and category descriptions) is filtered in htmLawed's safe mode before it is printed. Review text is stored as plain text.
- The flash toast script carries the CSP nonce.

[Unreleased]: https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/releases/tag/v1.0.0
