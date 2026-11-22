# ArtisanPack UI Ecommerce Storefront (Livewire) Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package
follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [2.0.0] - 2026-11-22

2.0 rebuilds every screen, block, and Blade component on the ArtisanPack UI
design system through `livewire-ui-components` 3.0. Routes, route names,
Livewire component names, config keys, and hooks are unchanged unless listed
below. It still runs on engine `^1.1`; engine 1.4 or later turns on rich
product options, "Notify me", line summaries, and the engine's hold for
stranded payments.

> **Breaking:** daisyUI is gone, published views and translations from 1.x
> must be republished, and some Blade component props changed. Read
> [Upgrading from 1.x to 2.0](docs/upgrading.md) before updating.

### Added

- **Design system:** every screen, block, and `x-artisanpack-ec-*` component is rebuilt on `livewire-ui-components` 3.0 and the `@artisanpack-ui/css` and `@artisanpack-ui/behavior` packages, with light and dark themes and right-to-left layouts. `ecommerce-storefront:install` prints the front-end setup.
- **Package layout:** a store navigation ("Shop all" and the top-level categories), a footer with link columns, a menu drawer on phones, and a slim secure header on the checkout page. Change its links with the `ap.ecommerceStorefrontLivewire.layout.navigation` and `ap.ecommerceStorefrontLivewire.layout.footerLinks` filters.
- **Rich product options** (engine 1.4+): swatches, option metadata, conditional fields (`visible_when`), and live price quotes on the options form.
- **"Notify me"** (engine 1.4+): a back-in-stock signup in place of the sold-out notice. Turn it off with the `ap.ecommerceStorefrontLivewire.product.backInStockEnabled` filter.
- **Line summaries:** cart, checkout, and order lines describe the options chosen, using the engine's line descriptions (engine 1.4+).
- **Toaster:** storefront toasts go through the component library's toaster, rendered by the new `<x-artisanpack-ec-toaster>`. `SendsToasts` helpers take an optional `$options` argument (variant, icon, and one action).
- **Account "Sign out":** the account navigation posts to the new `auth.logout_route` config key, and is hidden when that route doesn't exist.
- **Embedded components** take an optional `show-title` property that renders the page's h1.
- **Config keys:** `storefront.tagline`, `storefront.nav_categories`, `auth.logout_route`, `catalog.description`, `cart.free_shipping_threshold`, `product.variation_select_after`, and `search.keyboard_shortcut` (turns off the header search's "/" shortcut).
- **Extension points:** filters for the add-to-cart row, the product highlights, the sold-out notice, the payment method cards, and the store navigation; and the `ap.ecommerceStorefrontLivewire.checkout.paymentMismatch` and `ap.ecommerceStorefrontLivewire.checkout.marketingConsentPending` actions.
- **Empty state:** `<x-artisanpack-ec-empty-state>` takes a `live` prop that makes it a status region; it is off by default.
- **Translations:** Brazilian Portuguese (`pt_BR`), Japanese (`ja`), Simplified Chinese (`zh_CN`), Italian (`it`), and Arabic (`ar`) catalogues, machine-translated and awaiting native-speaker review. Arabic uses all six plural forms, and Japanese and Chinese a single form. Translations now load early, so strings resolved during boot are translated.

### Changed

- **Requires** `artisanpack-ui/livewire-ui-components` `^3.0` (was `^2.1`).
- **Variation pickers:** an attribute with more than `product.variation_select_after` (8) values is drawn as a select instead of swatches.
- **Claiming guest orders:** a successful claim stays on the page with a success message and a "View orders" link instead of redirecting.
- **Checkout:** upcoming steps are shown, muted, in the multi-step layout, and the checkout page uses the slim secure header.
- **Page titles:** the address book's heading is "Saved addresses" and the claim page's is "Claim orders".
- **`ToastPayload::make()`:** the fourth argument is now an options array, not a CSS class string.
- **Theming:** colours come from the design system's `--apui-*` tokens instead of daisyUI theme colours.

- **Product and category descriptions** are cleaned with the security package's safe profile: custom elements, form controls, `<template>`, and `class`, `style`, event-handler, Alpine (`x-*`, `@*`, `:*`) and `wire:*` attributes are removed, and only `http`, `https`, `mailto` and `tel` links are kept.
- **Customer records** are only created from the profile and address pages once a `MustVerifyEmail` user has verified their email.
- **Marketing consent:** the order's `meta.marketing_consent` now includes `applied`. A guest checkout no longer opts in an existing customer record that hadn't opted in; `checkout.marketingConsentPending` fires instead.
- **Inlined stylesheet** is minified.

### Removed

- **daisyUI** support and classes.
- **Blade component props and methods** replaced by the design system's `data-columns` grids: `gridClass`, `classes()`, and `large()`.

### Fixed

- **Security:**
  - Product and category descriptions could carry script through custom elements with Alpine directives (stored XSS).
  - A guest-order claim checked the user's current verified email but claimed orders under the customer record's older email, so another person's guest orders could be claimed.
  - Unbounded price and attribute filters on the catalog and search pages could flood the cache and multiply queries.
  - Profile saves and the checkout contact and review steps weren't rate-limited, and the review step revealed whether an email had an account.
- **Checkout and payments:**
  - A payment confirmed for an earlier cart total, or for a payment session the cart had since replaced, told the shopper to pay again and could charge them twice with no order. The checkout now records it, tells the shopper not to pay again, and won't set up another payment. Stripe checks the form is still current before charging. With engine 1.4+, an order the engine holds for a stranded payment (`meta.payment_mismatch`) goes to its confirmation like a fraud-review hold, never to payment again.
  - Browser back and forward could open steps the shopper hadn't reached, or reopen forms after payment.
  - Stripe's "Pay now" re-enabled while the order was being placed.
  - Changing the email after review skipped the account check, so the requested account silently failed.
  - Repeated payment and shipping errors weren't announced to screen readers.
- **Cart and product:**
  - The cart page kept a shipping estimate the engine had dropped.
  - Root-relative image URLs (for example `/storage/...`) were dropped, so product images could disappear.
  - Quick cart quantity changes could be lost when the shopper went straight to checkout, and re-opening the drawer showed stale quantities.
  - The cart page counted lines while the header and drawer counted items.
  - A variant without a price in the shopper's currency was called "out of stock", and grouped products ignored customer pricing.
- **Catalog and search:**
  - A search page past the last page showed "No results" beside a result count.
  - Hand-picked Product Grid products were cut by the sort before the limit, and all-digit slugs were read as ids.
  - The catalog canonical kept `?category=` instead of the category page, and price filter URLs now carry their currency.
  - Category and tag pages under the `product-archive` template rendered two h1s.
  - Catalog renders repeated slug lookups and uncached counts, and quick add re-rendered the product card.
- **Accounts:**
  - Below 1024px the account pages had no "Sign out".
  - Download links on an open downloads page expired after 10 minutes and showed a bare 403; Download now signs a fresh link on click, and an expired link returns to the downloads page.
  - The dashboard counted expired and used-up downloads as available.
- **Accessibility:**
  - The header search's "/" shortcut couldn't be turned off (WCAG 2.1.4).
  - Removing a filter chip, "Clear all", or the coupon dropped keyboard focus to the page.
  - Every cart quantity field was named "Quantity"; each now names its product.
  - Required option checkboxes and quantities weren't marked required, and the shipping-estimate error wasn't tied to its radio group.
  - The sticky header could cover the focused element, and selected swatches and chips lacked 3:1 contrast.
  - Every empty state was a live region; the checkout "order placed" state had no h1; checkout steps misused `aria-disabled`.
  - Header search throttled and failed messages weren't announced, and the price slider could announce $0.00 for an unset bound.
  - Order lookup, claim, address and profile fields used generic or dotted ids that could clash with the host page.
- **Front end:** related-products arrows pointed the wrong way in right-to-left layouts, card focus rings showed on mouse clicks and were clipped in the related-products scroller, and long chips overflowed at 320px.
- **Translations:** removed 10 strings 2.0 no longer uses (the six 1.x checkout step strings stay for published views).
- **Version:** the satellite registry now reports 2.0.0.

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

[Unreleased]: https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/releases/tag/v1.0.0
