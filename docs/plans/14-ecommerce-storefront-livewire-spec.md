# Package Spec: `artisanpack-ui/ecommerce-storefront-livewire`

**Status:** Approved v0.2 — decisions in §2 confirmed 2026-10-05
**Owner:** Jacob Martella
**Last updated:** 2026-10-05
**Tracker:** [ArtisanPack-UI/ecommerce#53](https://github.com/ArtisanPack-UI/ecommerce/issues/53)
**Parent plan:** `12-ecommerce-package-plan.md` — §3.2, §7, §8, §10.1, §11.2, §11.9, §15.4, §16.5, §16.6, §18 Phase 7
**Engine spec:** [`13-ecommerce-engine-spec.md`](https://github.com/ArtisanPack-UI/ecommerce/blob/main/docs/plans/13-ecommerce-engine-spec.md) (referred to below as "engine §N")
**Sibling:** [`15-ecommerce-admin-livewire-spec.md`](https://github.com/ArtisanPack-UI/ecommerce-admin-livewire/blob/main/docs/plans/15-ecommerce-admin-livewire-spec.md) (referred to below as "admin §N")

## 0. Purpose

This is the build spec for the Livewire storefront satellite: the customer-facing UI on top of the headless `artisanpack-ui/ecommerce` engine. It fixes the package identity, how the storefront reaches engine data, the routes and screens, the checkout and payment flow, the visual-editor blocks, and the work the engine and `livewire-ui-components` must do first. §15 breaks the build into issues.

When the parent plan and this spec disagree on storefront behaviour, this spec wins. When this spec and the engine spec disagree on a table, contract, hook, or ability, the engine spec wins.

Out of scope: the admin (`15-…`), the kanban UI (`16-…`), the React/Vue storefronts (#62, #65), and customer authentication screens (D9).

## 1. What the parent plan requires

| # | Deliverable (plan §10.1 / §11.2 / Phase 7) | Section |
|---|---|---|
| 1 | Catalog / category / tag pages with filters, sort, pagination | §7.1 |
| 2 | Single product page: gallery, variation picker, quantity, add to cart, reviews | §7.2 |
| 3 | Cart page + mini-cart + slide-out cart drawer | §7.3 |
| 4 | Checkout: multi-step wizard and single-page variant, store owner picks | §7.4 |
| 5 | Account: order history, order detail, addresses, digital downloads, subscriptions (when installed) | §7.5 |
| 6 | Guest order lookup | §7.6 |
| 7 | Search page with facets | §7.7 |
| 8 | Visual-editor commerce blocks and default templates (plan §11.2) | §11 |
| — | Browser tests: add to cart from PLP + PDP, guest and registered checkout, confirmation (plan §15.4) | §13 |

## 2. Decisions

### 2.1 Locked by the parent plan

- UI base is `artisanpack-ui/livewire-ui-components` only. Missing primitives are filed against the component library (§10); until they land the storefront composes them inside one Blade component so the swap is one file.
- Day-1 locales are `en`, `es`, `fr`, `de`. Every user-facing string goes through `__()` / `trans_choice()`.
- The package registers with the engine's `SatelliteRegistry` and honours the uninstall lifecycle.
- Checkout offers a multi-step wizard and a single-page variant; the store owner chooses.

### 2.2 Carried over from the admin spec (#52)

| # | Decision | Outcome |
|---|---|---|
| D1 | Data access | **In-process.** Livewire components call engine services, models, and policies. No HTTP round-trip to `/api/ecommerce/v1`. |
| D2 | Missing domain logic | **In the engine's 1.0 release** (not yet published), as services with the REST endpoints on top, so the React and Vue storefronts share them. §9 lists the issues. |
| D3 | Browser tests | **Pest 4 browser plugin**, so this package is `php: ^8.3` and Pest 4. |

### 2.3 Decided for this package (confirmed 2026-10-05)

| # | Decision | Outcome | Why |
|---|---|---|---|
| D4 | Pages | **Routes + components.** The package registers storefront and account routes (off with `routes_enabled`) whose controllers return page views wrapped in a configurable layout. Every screen is also a standalone Livewire component a host can embed in its own pages. | A store should work after `composer require`, but hosts with their own front end must be able to drop components in. `bookings` (components only) needs every host to write routes; the admin's controller → page-view pattern keeps `route:cache` working. |
| D5 | Payment entry | **Client-render contract.** The engine adds an optional `RendersClientPayment` gateway contract (E4); the storefront ships a payment-driver registry with a **Stripe Payment Element** driver and a generic **redirect** driver. | On-site card entry is expected; hosted-only redirects send shoppers away. A registry lets PayPal (#55), Square (#68), and BNPL (#93) satellites plug in without storefront changes. |
| D6 | Customer auth | **Host owns auth.** The storefront links to the host's login/register routes (configurable names), merges carts on `Login`, and offers optional account creation at checkout through a configurable action class. | Starter kits, cms-framework, and Fortify already own auth; a second set of screens would conflict. |
| D7 | Search | **Search + basic facets without a satellite.** The engine adds a `SearchProvider` contract with a database/Scout default that returns facets (E13); search satellites swap in. | The search page should always work; facets come from the same `CatalogQuery` the catalog uses. |
| D8 | Visual-editor blocks | **In this package**, registered only when `artisanpack-ui/visual-editor` is installed (soft dependency). The engine's misleading `suggest` text is fixed (E20). | Blocks render Livewire storefront components, so they belong with them. React/Vue storefronts would ship their own. |
| D9 | No auth screens | Login, registration, password reset, and email verification are out of scope (D6). | — |

## 3. Package identity

| Item | Value |
|---|---|
| Composer name | `artisanpack-ui/ecommerce-storefront-livewire` |
| Namespace | `ArtisanPackUI\EcommerceStorefrontLivewire\` |
| Service provider | `ArtisanPackUI\EcommerceStorefrontLivewire\EcommerceStorefrontLivewireServiceProvider` |
| Config file / key | `config/artisanpack/ecommerce-storefront-livewire.php` → `artisanpack.ecommerce-storefront-livewire` |
| View namespace | `ecommerce-storefront` |
| Livewire component names | `artisanpack-ecommerce-storefront-{screen}` (e.g. `artisanpack-ecommerce-storefront-product-show`) |
| Blade component prefix | `<x-artisanpack-ec-…>` (plan §20), shared naming with the admin; storefront components are prefixed `sf-` where a name would collide (e.g. `x-artisanpack-ec-sf-product-card`) |
| Route names | `artisanpack.ecommerce.storefront.*`, `artisanpack.ecommerce.account.*` |
| Hook prefix | `ap.ecommerceStorefrontLivewire.` |
| Translations | `lang/{en,es,fr,de}.json`, loaded with `loadJsonTranslationsFrom()` |
| Publish tags | `ecommerce-storefront-config`, `ecommerce-storefront-views`, `ecommerce-storefront-lang` |
| Block namespace (visual-editor) | `artisanpack-commerce/{block}` |
| License | GPL-3.0-or-later |

## 4. Dependencies

### 4.1 `require`

```json
{
    "php": "^8.3",
    "ext-bcmath": "*",
    "illuminate/support": "^12.0|^13.0",
    "livewire/livewire": "^3.6|^4.0",
    "artisanpack-ui/ecommerce": "^1.0",
    "artisanpack-ui/livewire-ui-components": "^2.1",
    "artisanpack-ui/core": "^1.0",
    "artisanpack-ui/hooks": "^1.2",
    "artisanpack-ui/security": "^1.0|^2.0"
}
```

### 4.2 Soft integrations (`suggest` + runtime detection)

| Package | What it enables |
|---|---|
| `artisanpack-ui/visual-editor` | Commerce blocks and editable product, category, cart, and checkout templates (§11). |
| `artisanpack-ui/media-library` | Responsive product images from the `product-card` / `product-detail` / `product-lightbox` conversions (plan §11.4); without it, `image_url`. |
| `artisanpack-ui/seo` | Meta tags and structured data through the SEO package when installed (S39). |
| `artisanpack-ui/livewire-drag-and-drop` | Reorderable lists for satellites such as wishlists (plan §10.1). |
| Search, subscription, wishlist, recently-viewed satellites | Detected through engine registries and hooks; nothing is hard-coded. |

### 4.3 Front-end

- **No package CSS or JS build.** The host adds the package views to its Tailwind sources (`@source`); the install command prints the line.
- Payment SDKs load from the provider's CDN at runtime (Stripe.js from `js.stripe.com`, as PCI requires); driver glue is inline Alpine in the driver's Blade view.
- A `package.json` / `vite.config.js` exists only for the browser-test workbench (export-ignored), as in the admin.

### 4.4 `require-dev`

Same as the admin: Pest 4 + `pest-plugin-laravel`, `pest-plugin-livewire`, `pest-plugin-browser`, Testbench `^10.2|^11.0`, code-style, code-style-pint, php-cs-fixer, phpcs installer.

## 5. Architecture

### 5.1 Layers

```
Route → StorefrontPageController (returns a page view)
          → page view  @extends( $ecommerceStorefrontLayout )   (or <x-ve-template> when templates are on)
              → <livewire:artisanpack-ecommerce-storefront-… />
                   → engine service / query  (CatalogQuery, StorefrontCartService, CheckoutService, …)
                   → engine policy check for owned data (orders, addresses, downloads)
```

- **Pages are controller actions** returning a view that embeds one Livewire component, so `route:cache` works and the layout can be swapped. Components are class-based (`src/Livewire/{Area}/…`), not Volt.
- **Writes go through engine services**; a component never assembles a multi-table write.
- **Reads** use engine query services (`CatalogQuery`, `PriceDisplayResolver`, `StockStatus`, …) so the three storefront families show the same data.
- **Embeddable:** every screen component takes its subject as a prop (`product`, `category`, `order`) so hosts can mount it anywhere.
- **Cross-component state** (cart count, drawer open, toasts) moves through Livewire events: `ecommerce-cart-updated`, `ecommerce-cart-open`, plus the `Toast` trait.

### 5.2 Things the REST layer did that the storefront must do itself

- **Cart identity** — the guest cart token lives in an encrypted cookie (name and lifetime from engine config, E7) and is resolved per request through the engine's `CurrentCart`. Signed-in shoppers get their customer cart; on `Login` the engine merges (plan §7.1) and the storefront presents the currency-mismatch choice when the merge is pending.
- **Rate limiting** — cart mutations, coupon attempts, review submission, guest lookup, and checkout finalize go through the engine's in-process limiter (E17) with the cart token or customer as the subject, not the `/livewire/update` IP.
- **Idempotency** — "Place order" carries a one-time action token minted at render and the engine's finalize is idempotent per cart (E3). Buttons use `wire:loading.attr="disabled"`.
- **Sanitization** — review text and order notes pass through `security` helpers before reaching the engine (plan §11.5).

### 5.3 Layout

- A view composer on `ecommerce-storefront::pages.*` sets `$ecommerceStorefrontLayout` from `storefront.layout` (default `ecommerce-storefront::layouts.app`).
- The package layout is minimal and themeable: `x-artisanpack-nav` header with brand, catalog link, search, account link, currency switcher, and cart button; footer; toast container; cart drawer mounted once. Hosts usually point `storefront.layout` at their own layout, which must yield `content` and `title` and render the `ecommerce-storefront::partials.global` include (cart drawer + toasts); the install command prints the snippet. Hosts with component layouts publish a one-line wrapper view (documented).
- When visual-editor templates are enabled (§11.4), product, category, catalog, cart, and checkout pages render `<x-ve-template :slug>` inside the same layout.

### 5.4 Routes

Storefront routes use `storefront.route_prefix` (default `shop`) and `storefront.middleware`; account routes use `account.route_prefix` (default `account`) and `account.middleware` (`web`, `auth`).

| Path | Name | Screen |
|---|---|---|
| `/shop` | `storefront.catalog` | Catalog (§7.1) |
| `/shop/category/{path}` | `storefront.category` | Category page; `{path}` is the slug chain |
| `/shop/tag/{tag}` | `storefront.tag` | Tag page |
| `/shop/products/{product}` | `storefront.product` | Product page (§7.2) |
| `/shop/search` | `storefront.search` | Search (§7.7) |
| `/shop/cart` | `storefront.cart` | Cart (§7.3) |
| `/shop/checkout` | `storefront.checkout` | Checkout (§7.4) |
| `/shop/checkout/return` | `storefront.checkout.return` | Payment redirect / 3DS return |
| `/shop/orders/{order}/confirmation` | `storefront.confirmation` | Confirmation (signed for guests) |
| `/shop/order-lookup` | `storefront.lookup` | Guest order lookup (§7.6) |
| `/account` | `account.dashboard` | Account (§7.5) |
| `/account/orders`, `/account/orders/{order}` | `account.orders.*` | Order history / detail |
| `/account/addresses` | `account.addresses` | Address book |
| `/account/downloads` | `account.downloads` | Downloads and license keys |
| `/account/profile` | `account.profile` | Profile and notification preferences |
| `/account/claim` | `account.claim` | Claim a guest order |

Products resolve by slug through `storefrontVisible()`; a product whose type is missing (plan §16.6) returns 404 on the storefront.

### 5.5 Config

```php
return [
    'storefront' => [
        'routes_enabled' => true,
        'route_prefix'   => 'shop',
        'middleware'     => [ 'web' ],
        'layout'         => 'ecommerce-storefront::layouts.app',
    ],
    'account' => [
        'route_prefix' => 'account',
        'middleware'   => [ 'web', 'auth' ],
    ],
    'auth' => [
        'login_route'           => 'login',
        'register_route'        => 'register',
        'create_account_action' => \ArtisanPackUI\EcommerceStorefrontLivewire\Actions\CreateCustomerAccount::class,
    ],
    'catalog' => [
        'per_page'        => 24,
        'per_page_values' => [ 12, 24, 48 ],
        'default_sort'    => 'newest',
        'show_stock_count' => false,   // "Only 3 left" below the low-stock threshold
    ],
    'checkout' => [
        'layout' => 'multi_step',      // or 'single_page'; overridden by the engine setting storefront.checkout_layout
    ],
    'payments' => [
        'drivers' => [],               // extra driver classes; core drivers are always registered
    ],
    'visual_editor' => [
        'blocks'    => true,
        'templates' => false,          // render pages through visual-editor templates
    ],
];
```

Store-owner choices (checkout layout, guest checkout, account creation at checkout) are read through `ecommerceSetting()` first so the admin's settings screens can change them, falling back to this config.

## 6. Authorization and privacy

- Catalog, product, cart, checkout, search, and lookup are public. Product visibility is `storefrontVisible()` only.
- Account screens require `auth`; each component resolves the shopper's `Customer` through `Customer::forUser()` and authorizes owned records through the engine's owner policies (E10): `$this->authorize( 'view', $order )`. Another customer's order, address, or download returns 404, not 403.
- Guest confirmation and lookup views authorize with a signed order-view token (E12), never by order number alone.
- Account routes are registered with `Livewire::addPersistentMiddleware()` so update requests are re-checked.
- Email, address, and phone are never written to logs or toasts; lookup failures give one message for "no order" and "wrong email".

## 7. Screens

### 7.1 Catalog, category, and tag pages

- Product grid of `x-artisanpack-ec-sf-product-card` (image, name, `DisplayPrice` with sale price, rating, stock badge, quick add for simple products).
- **Filters** (sidebar on desktop, `x-artisanpack-drawer` on mobile): category tree, tags, price range (U2), attribute values as checkboxes or swatches (U3), in stock, on sale, minimum rating. Facet counts come from `CatalogQuery` (E8). Active filters show as removable badges with "Clear all".
- **Sort:** relevance (search only), newest, price low→high / high→low, popularity, rating, name.
- **Pagination:** `x-artisanpack-pagination` with per-page choice; every filter, sort, and page lives in the query string (`#[Url]`) so results are linkable and back/forward works.
- Category page: breadcrumbs from the category tree, header with name, description, and image, sub-category chips, descendant products. Tag page: name and products.
- Empty state when nothing matches, with "Clear filters" (livewire-ui-components #111).
- Extension: filters `ap.ecommerceStorefrontLivewire.catalog.filters`, `.sorts`, `.productCard`.

### 7.2 Product page

- **Gallery** (U4): featured image + gallery with thumbnails, zoom, lightbox; switches to the selected variant's image.
- **Summary:** name, rating summary linking to reviews, `DisplayPrice` (sale, from-price, tax label), short description, stock status, SKU.
- **Purchase form:** quantity (U1) and add to cart. Per type:
  - `simple` — quantity only.
  - `variable` — variation picker (S10): one swatch or select group per variation attribute, unavailable combinations disabled through `VariantResolver::matrix()` (E9), price/stock/image/SKU update on change, selection in the query string.
  - `grouped` — child products with their own quantities.
  - `bundled` — fixed children listed; bundle price.
  - `digital` — download/licence notes; no shipping message.
  - Other types — fields from the engine's `ProvidesStorefrontOptions` contract (E16), or a product-type form registered in the storefront's `ProductFormRegistry`.
- **Details:** description (rendered with `kses()`), attributes table, shipping/returns content from a filter — in `x-artisanpack-tabs` or accordion (tabs pending livewire-ui-components #119).
- **Reviews** (S12): histogram, approved reviews paginated, verified badge, "Write a review" when `ReviewService::eligibility()` allows (E18), honeypot, rate limit `ecommerce.review.submit`, success message "awaiting moderation".
- **Related / upsells** (S13) from `RelatedProducts` (E19), lazy-loaded with skeletons (U5).
- Fires `ap.ecommerce.product.viewed` (E16).
- Extension: `ap.ecommerceStorefrontLivewire.product.sections` filter for extra sections (position + Livewire component).

### 7.3 Cart, mini-cart, and drawer

**Cart page**
- Lines: image, name, options, unit price, quantity (U1, debounced `updateItem`), line total, remove (with undo toast).
- Coupon field (`applyCoupon` / `removeCoupon`), engine coupon messages shown inline, rate limit `ecommerce.coupon.attempt`.
- Totals: subtotal, discounts by promotion, shipping (estimate or "calculated at checkout"), tax (estimated until an address exists, E6), total.
- Shipping estimate: country/region/postcode → `ZoneShippingRateProvider` rates.
- Unsellable lines (`unsellableItems()`) flagged with a reason and excluded from checkout until resolved.
- Cross-sells (E19). "Continue shopping" and "Checkout" actions.

**Mini-cart and drawer**
- Header cart button with item count badge; clicking opens the drawer (`x-artisanpack-drawer right`).
- Drawer: lines with quantity and remove, subtotal, "View cart" and "Checkout".
- Add to cart anywhere dispatches `ecommerce-cart-updated` and opens the drawer (configurable: drawer / toast / nothing); focus moves into the drawer and returns on close.

**Currency switcher** (S16) — `x-artisanpack-select` of enabled currencies (E15); switching re-prices the cart through `changeCurrency()` (E6).

### 7.4 Checkout

Built on the engine `CheckoutService` (E1), `OrderPlacementService` (E2), and the two-phase payment flow (E3).

**Entry and guards**
- Empty cart → redirect to cart. Unsellable lines → back to cart with the reasons.
- `start()` reserves stock; shortfalls are shown before the first step.
- Guest checkout follows the `checkout.guest_checkout` setting: allowed (guest or sign in), account required (sign-in / register links to host routes, returning to checkout), or disabled.
- Email is captured first and saved to the cart (abandoned-cart support, E14).

**Steps** (both layouts use the same step components)

| Step | Contents |
|---|---|
| Contact | Email; "sign in for faster checkout" link; marketing consent (unchecked) |
| Address | Shipping address (`x-artisanpack-ec-address-form`, country/region lists, postcode format); saved addresses for signed-in shoppers; billing same-as-shipping toggle; skipped for all-digital carts except billing |
| Shipping | Rates from `getRatesForCart()`; re-quoted whenever lines or the address change; `setShippingMethod()` |
| Payment | Gateway choice from `availableGateways()`; the chosen gateway's driver (§8.3) renders the payment UI |
| Review | Lines, addresses, method, totals with tax; order note; terms checkbox (when configured); optional "create an account" (D6); "Place order" |

- **Multi-step** (`x-artisanpack-steps`): one step at a time, completed steps summarised and editable, progress announced to screen readers, browser back moves between steps (query string).
- **Single page:** all sections visible; sections below an incomplete one are disabled with a reason.
- Layout comes from `ecommerceSetting( 'storefront.checkout_layout' )`, falling back to config.

**Placing the order**
1. Payment step calls `createPaymentSession()` (stored on the cart, reused on retries).
2. The driver confirms client-side (embedded) or redirects; it returns a payment reference.
3. "Place order" calls `finalize( $cart, $reference )` under the action token and the `ecommerce.checkout.finalize` limiter. A repeat returns the same order.
4. Challenged → driver handles 3DS and resumes; failed → message from the gateway, payment step reopened, reservations kept until TTL; blocked by fraud → generic message.
5. Success → redirect to the confirmation page (signed for guests). The webhook path (E5) produces the same order if the shopper leaves early, and the return route finds it.

**Account creation at checkout** — when enabled and the shopper is a guest, a password field; after placement `CreateCustomerAccount` (configurable) creates the host user from `auth.providers.users.model`, links the customer (`CustomerService::linkUser()`), and logs them in. Hosts with extra registration rules swap the action.

**Confirmation page** — order number, status, items, totals, addresses, payment method, download links for digital items, "create an account to track orders" for guests, and "continue shopping".

### 7.5 Account

- **Shell:** account nav (`x-artisanpack-menu`) built from core entries plus the engine's `AccountMenuRegistry` (E16), so subscriptions, wishlists, and other satellites add pages. Dashboard: greeting, recent orders, default addresses, downloads count.
- **Orders:** list with number, date, status (customer-facing label), total, item count; filter by status; pagination. Detail: items, totals, addresses, payment method, shipments with tracking links, refunds, customer-visible notes, download links; "buy again" adds available lines to the cart.
- **Addresses:** list, add, edit, delete, set default shipping/billing through `CustomerAddressService` with owner policy (E10).
- **Downloads and licence keys** (E11): entitlements with remaining downloads and expiry, download button (owner route), licence keys with copy and activation count.
- **Profile:** name, phone, marketing consent; notification preferences through `NotificationPreferenceService` (E10). Email and password changes link to the host's profile screens.
- **Claim an order:** order number + postcode → `CustomerClaimService::claim()`, rate limited (E10).

### 7.6 Guest order lookup

- Email + order number form → `GuestOrderLookupService::find()` (E12), rate limited; on success redirect to the signed order view, which reuses the account order-detail component in read-only guest mode.
- Linked from the confirmation email, footer, and the account sign-in prompt.

### 7.7 Search

- Search page: `SearchProvider::search()` (E13) results in the catalog grid, facets in the catalog filter sidebar, sort including relevance, "did you mean" when the provider returns suggestions, empty state.
- Header search box: `x-artisanpack-input` with debounced live results (top 5 products and matching categories) in a popover; Enter goes to the search page. Keyboard: arrow keys through results, Escape closes.

## 8. Shared components

### 8.1 Display and input helpers

Composed Blade components (no new primitives):

- `<x-artisanpack-ec-price>` — renders a `DisplayPrice` (E9): price, struck compare-at with "Sale" text for screen readers, from/to ranges, tax label.
- `<x-artisanpack-ec-money>` — `MoneyFormatter` (shared naming with the admin's component; storefront ships its own copy until a shared package exists).
- `<x-artisanpack-ec-stock-status>` — badge with text, never colour alone.
- `<x-artisanpack-ec-rating-summary>` — read-only `x-artisanpack-rating` + count link.
- `<x-artisanpack-ec-address>`, `<x-artisanpack-ec-address-form>` — country and region lists, postcode hints, autocomplete attributes.
- `<x-artisanpack-ec-sf-product-card>` — the card used by grids, related products, search, and blocks.
- `<x-artisanpack-ec-empty-state>` — local stand-in for livewire-ui-components #111.
- Local stand-ins for U1–U5 until they ship, each in one component file.

### 8.2 Product form registry

`ProductFormRegistry::register( string $typeKey, string $livewireComponent )` maps a product type to its purchase form. Core types are registered; unknown types use the engine's `ProvidesStorefrontOptions` schema (E16) rendered by a generic form, and a type with neither shows "unavailable".

### 8.3 Payment drivers

- `PaymentDriverRegistry::register( string $driver, string $livewireComponent )` keyed by the `driver` value from the engine's `clientConfig()` (E4).
- Core drivers:
  - `stripe-payment-element` — loads Stripe.js from `js.stripe.com`, mounts the Payment Element with the client secret and locale/appearance options, confirms with `redirect: 'if_required'`, handles 3DS, and returns the PaymentIntent id. Appearance follows the daisyUI theme tokens.
  - `redirect` — "Continue to {gateway}" button to `redirect_url`; the return route resumes checkout.
- A driver component implements `confirm()` and reports `payment-confirmed` / `payment-failed` events to the checkout. Card data never touches Livewire state or the server (plan §8.5).

### 8.4 Extension surface

| Surface | How a satellite plugs in |
|---|---|
| Account nav | Engine `AccountMenuRegistry` (E16) |
| Product purchase form | `ProductFormRegistry` (§8.2) or engine `ProvidesStorefrontOptions` |
| Payment UI | `PaymentDriverRegistry` (§8.3) |
| Product page sections | Filter `ap.ecommerceStorefrontLivewire.product.sections` |
| Catalog filters / sorts / product card | Filters `ap.ecommerceStorefrontLivewire.catalog.filters`, `.sorts`, `.productCard` |
| Cart and checkout | Filters `ap.ecommerceStorefrontLivewire.cart.sections`, `.checkout.steps` (extra step before Review), actions `…checkout.beforePlaceOrder` |
| Header | Filter `ap.ecommerceStorefrontLivewire.header.actions` (e.g. wishlist icon) |
| Visual-editor blocks | §11 |

Registries are singletons under `ArtisanPackUI\EcommerceStorefrontLivewire\Registries\`.

## 9. Engine prerequisites

Verified against engine `release/1.0` (HEAD c5144b7). Each row is an issue on `ArtisanPack-UI/ecommerce`, milestone `v1.0` (D2).

| # | Gap | Blocks |
|---|---|---|
| E1 ([ecommerce#164](https://github.com/ArtisanPack-UI/ecommerce/issues/164)) | No `CheckoutService`, checkout state, checkout hooks, or checkout-start reservations; no `checkout/*` endpoints | §7.4 |
| E2 ([ecommerce#165](https://github.com/ArtisanPack-UI/ecommerce/issues/165)) | Nothing converts a cart into an order; `OrderPlaced` / `CartCompleted` and `order.number` never fire; reservations never commit | §7.4 placing the order, confirmation |
| E3 ([ecommerce#166](https://github.com/ArtisanPack-UI/ecommerce/issues/166)) | `PaymentOrchestrator::finalize()` creates a new PaymentIntent, no 3DS resume, not idempotent per cart, bypasses `OrderStatusMachine` | §7.4, §8.3 |
| E4 ([ecommerce#167](https://github.com/ArtisanPack-UI/ecommerce/issues/167)) | No gateway client-render contract | §8.3 |
| E5 ([ecommerce#168](https://github.com/ArtisanPack-UI/ecommerce/issues/168)) | Payment webhooks do not update orders; no `reconcile-payments` job | §7.4 step 5 |
| E6 ([ecommerce#169](https://github.com/ArtisanPack-UI/ecommerce/issues/169)) | Cart ignores product-type validation and stock; tax never applied; no currency switch | §7.2, §7.3 |
| E7 ([ecommerce#170](https://github.com/ArtisanPack-UI/ecommerce/issues/170)) | No current-cart resolver, customer attach, or login merge wiring | §5.2, §7.3 |
| E8 ([ecommerce#171](https://github.com/ArtisanPack-UI/ecommerce/issues/171)) | No catalog query/facets, category tree read, public category/tag endpoints, or featured flag | §7.1, §11 |
| E9 ([ecommerce#172](https://github.com/ArtisanPack-UI/ecommerce/issues/172)) | No display price (compare-at, from-price, tax-inclusive), variant resolver, or read-only stock status | §7.1, §7.2, §8.1 |
| E10 ([ecommerce#173](https://github.com/ArtisanPack-UI/ecommerce/issues/173)) | No owner policies, order history, `me/*` endpoints, or claim endpoint/limiter | §7.5 |
| E11 ([ecommerce#174](https://github.com/ArtisanPack-UI/ecommerce/issues/174)) | Downloads and licence keys cannot be listed or downloaded by their owner | §7.5 downloads |
| E12 ([ecommerce#175](https://github.com/ArtisanPack-UI/ecommerce/issues/175)) | No guest lookup service/endpoint or signed order-view link | §7.4 confirmation, §7.6 |
| E13 ([ecommerce#176](https://github.com/ArtisanPack-UI/ecommerce/issues/176)) | No `SearchProvider` / facets / `SearchIndexer` | §7.7 |
| E14 ([ecommerce#177](https://github.com/ArtisanPack-UI/ecommerce/issues/177)) | No abandoned-cart flagging or `CartAbandoned` | §7.4 email capture (soft) |
| E15 ([ecommerce#178](https://github.com/ArtisanPack-UI/ecommerce/issues/178)) | No enabled currencies, `CurrencyResolver`, or FX refresh | §7.3 currency switcher |
| E16 ([ecommerce#179](https://github.com/ArtisanPack-UI/ecommerce/issues/179)) | No account-nav registry, product-type storefront options, or `product.viewed` hook | §7.2, §7.5, §8.4 |
| E17 ([ecommerce#180](https://github.com/ArtisanPack-UI/ecommerce/issues/180)) | Rate limiting / idempotency only work for HTTP routes | §5.2 |
| E18 ([ecommerce#181](https://github.com/ArtisanPack-UI/ecommerce/issues/181)) | No review eligibility, automatic verified purchase, or histogram | §7.2 reviews |
| E19 ([ecommerce#182](https://github.com/ArtisanPack-UI/ecommerce/issues/182)) | No related products, upsells, or cross-sells | §7.2, §7.3, §11 |
| E20 ([ecommerce#183](https://github.com/ArtisanPack-UI/ecommerce/issues/183)) | Engine `suggest` claims it ships visual-editor blocks | D8 |

Not blocked: package shell, display helpers (with local fallbacks), the visual-editor block foundation, and most layout work. Catalog and cart can start on the existing `StorefrontCartService` and `ProductPriceResolver` but finish on E6–E9.

## 10. `livewire-ui-components` prerequisites

Filed on `ArtisanPack-UI/livewire-ui-components` (milestone `Awaiting Review`, checked against 2.1.0):

| # | Missing | Used by |
|---|---|---|
| U1 ([livewire-ui-components#120](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/120)) | Quantity stepper | Product page, cart, drawer |
| U2 ([livewire-ui-components#121](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/121)) | Dual-handle range slider | Price filter |
| U3 ([livewire-ui-components#122](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/122)) | Swatch selector | Variation picker, colour/size filters |
| U4 ([livewire-ui-components#123](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/123)) | Image slider thumbnails, external control, zoom | Product gallery |
| U5 ([livewire-ui-components#124](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/124)) | Standalone skeleton | Lazy sections |

Already open and relevant: #111 (empty state), #117 (WCAG fixes: tabs keyboard, modal/drawer semantics, field errors), #118 (spotlight query encoding — header search does not use spotlight), #119 (tabs empty on daisyUI ≥ 5.0.x; the browser workbench pins `daisyui ~5.0.48` like the admin).

## 11. Visual-editor blocks and templates

Registered in `boot()` only when `class_exists( \ArtisanPackUI\VisualEditor\Facades\VisualEditor::class )` and `visual_editor.blocks` is true.

### 11.1 Mechanism

- Each block uses `VisualEditor::registerServerBlock( 'artisanpack-commerce/{name}', $metadata, $render, $callbacks )` (visual-editor ≥ 1.8). No editor JS build: the editor builds the inspector from `attributes` (`apControl` overrides) and previews through the server.
- `render()` returns a view that mounts the matching storefront Livewire component, so blocks and pages share one implementation.
- `validateAttrs` clamps every attribute; every output is escaped (the visual-editor docs' stored-XSS warning).
- Names are added to `enabled_blocks` through config so the allow-list stays correct.

### 11.2 Context ("current product")

visual-editor's `render()` receives only attributes, so the storefront binds a request-scoped `StorefrontContext` (current product, category, order) that page controllers set before rendering a template. Context blocks (Single Product, Gallery, Price, Add to Cart, Reviews, Related) read it; in the editor, or when it is empty, they render the product chosen in the block's `productId` attribute, else the newest active product as a labelled sample. `products` is mapped in `artisanpack.visual-editor.resources` so preview requests carry the resource id.

### 11.3 Blocks (plan §11.2)

| Block | Attributes | Group |
|---|---|---|
| Product Grid | source (category, tag, featured, on sale, hand-picked, newest), ids, sort, limit, columns, show price/rating/add-to-cart | S35 |
| Category Grid | parent, limit, columns, show counts/images | S35 |
| Related Products | type (related / upsell / cross-sell), limit, columns | S35 |
| Recently Viewed | limit — registered only when the recently-viewed satellite is installed | S35 |
| Single Product | productId (optional) | S36 |
| Product Gallery | productId, thumbnails position, zoom | S36 |
| Price | productId | S36 |
| Add-to-Cart Button | productId, show quantity, button text | S36 |
| Reviews | productId, per page, show form | S36 |
| Cart Contents | show cross-sells, show coupon | S37 |
| Checkout Steps | layout override | S37 |

### 11.4 Templates and patterns

- Default templates through the `ap.visualEditor.templates` filter: `single-product`, `product-archive`, `product-category`, `product-tag`, `cart`, `checkout`, `search-results`. Each is composed from the blocks above and core visual-editor blocks, so the default rendering matches the non-template pages.
- Template chain for a product: `single-product-{slug}` → `single-product-{type}` → `single-product`; for a category: `product-category-{slug}` → `product-category` → `product-archive`.
- Patterns (`ap.visualEditor.patterns`): "Featured products", "Shop by category", "Sale banner + grid".
- Template rendering is opt-in (`visual_editor.templates`) so installing visual-editor does not change an existing store's pages.

## 12. Localization, accessibility, SEO, performance

- `lang/{en,es,fr,de}.json`; CI runs `ecommerce:lint:translations --no-engine` against `src` and `resources`.
- Money through `MoneyFormatter`, dates through `LocalizedDate`, tax label through `TaxLabel`, so admin and storefront agree. Logical CSS properties for RTL (plan §16.5).
- **WCAG 2.2 AA.** Keyboard-complete flows (filters, swatches, gallery, drawer, checkout); visible focus; focus moves to the drawer, step headings, and error summaries; live regions announce cart updates, filter result counts, and checkout step changes; form errors linked with `aria-describedby`; touch targets ≥ 24px; prices read correctly ("was $25, now $19").
- **SEO:** per-page title and description, canonical URLs (filters and sort excluded except category/page), `noindex` on cart/checkout/account/search, JSON-LD `Product`/`Offer`/`AggregateRating`/`BreadcrumbList`; delegated to `artisanpack-ui/seo` when installed.
- **Performance:** eager loading in every listing; cached category tree and facet counts (tagged, busted on product/category writes); `lazy` Livewire for below-the-fold sections; responsive images with `srcset` from media-library conversions; no N+1 in the product card (asserted in tests).

## 13. Testing

- **Pest + Testbench.** One `Livewire::test()` file per component under `tests/Feature/Livewire/{Area}/`, covering render, happy path, validation, engine exceptions, and (for account screens) another customer's data returning 404.
- **Route test** walks every route as guest and as a signed-in customer.
- **Coverage target ~80%** (plan §15.1).
- **Browser tests** (Pest 4 browser plugin, seeded with `ecommerce:seed-demo`, Stripe in test mode with a fake driver for CI): add to cart from catalog and product page, choose a variant, update the cart and apply a coupon, guest checkout (multi-step), registered checkout (single page), confirmation shown, guest order lookup, account order detail.
- **CI matrix:** PHP 8.3/8.4 × Laravel 12/13 × Livewire 3/4; jobs: tests, `composer lint`, translation lint, PCI column lint, browser (Livewire 3/4), `verify-satellite` on tags.

## 14. Satellite lifecycle

```php
$active = $this->app->make( SatelliteRegistry::class )->register( [
    'package_name'    => 'artisanpack-ui/ecommerce-storefront-livewire',
    'version'         => self::VERSION,
    'label'           => __( 'Storefront (Livewire)' ),
    'migration_paths' => [],
    'config_keys'     => [ 'artisanpack.ecommerce-storefront-livewire' ],
    'meta_namespaces' => [],
    'tables'          => [],
    'columns'         => [],
    'product_types'   => [],
] );

if ( ! $active ) {
    return; // no routes, components, blocks, or hooks
}
```

- The storefront owns no tables, so no migrations and no uninstaller; `ecommerce:verify-satellite` runs with `--allow-empty`.
- `php artisan ecommerce-storefront:install` publishes config, prints the Tailwind `@source` line and the layout include, checks that the host has `login` / `register` routes (D6), and warns when no payment gateway is configured.

## 15. Delivery plan

Issues on this repository (issue numbers match the S-numbers: S01 is #1), labelled `ecosystem: ecommerce`, milestone `v1.0`, with the org issue type plus Priority and Effort fields set. "Needs" lists engine (E) or component-library (U) prerequisites; "soft" means a local fallback exists.

### M0 — Foundation

| # | Issue | Type | Needs |
|---|---|---|---|
| S01 | Package scaffold | task | — |
| S02 | Storefront shell: routes, page controller, layout resolver, standalone layout, global partial | feature | — |
| S03 | Install command | task | — |
| S04 | Display components: price, money, stock status, rating summary, address, product card, empty state | feature | E9, U5 (soft) |
| S05 | Cart session wiring and Livewire safety: current cart, guest cookie, login merge prompt, rate limits, action tokens | feature | E7, E17 |

### M1 — Catalog and product

| # | Issue | Type | Needs |
|---|---|---|---|
| S06 | Catalog page: grid, sort, pagination, URL state | feature | E8, E9 |
| S07 | Catalog filters with facet counts and mobile filter drawer | feature | E8, U2, U3 (soft) |
| S08 | Category and tag pages | feature | E8 |
| S09 | Product page: gallery, summary, details, simple add to cart | feature | E6, E9, U1, U4 (soft) |
| S10 | Variation picker for variable products | feature | E9, U3 (soft) |
| S11 | Grouped, bundled, and digital purchase forms + product form registry | feature | E16 |
| S12 | Product reviews: list, histogram, submit | feature | E18 |
| S13 | Related products, upsells, cross-sells | feature | E19 |

### M2 — Cart

| # | Issue | Type | Needs |
|---|---|---|---|
| S14 | Cart page: lines, coupons, totals, shipping estimate, unsellable lines | feature | E6 |
| S15 | Mini-cart, header cart button, and slide-out cart drawer | feature | E6, E7 |
| S16 | Currency switcher | feature | E6, E15 |

### M3 — Checkout

| # | Issue | Type | Needs |
|---|---|---|---|
| S17 | Checkout foundation: entry guards, reservations, guest rules, email capture | feature | E1, E14 (soft) |
| S18 | Address step | feature | E1, E10 |
| S19 | Shipping step | feature | E1 |
| S20 | Payment step, payment driver registry, redirect driver | feature | E3, E4 |
| S21 | Stripe Payment Element driver with 3DS | feature | E3, E4, E5 |
| S22 | Review and place order; account creation at checkout | feature | E2, E3, E17 |
| S23 | Multi-step and single-page checkout layouts | feature | E1 |
| S24 | Order confirmation page | feature | E2, E12 |

### M4 — Account and order lookup

| # | Issue | Type | Needs |
|---|---|---|---|
| S25 | Account shell, dashboard, and account navigation | feature | E10, E16 |
| S26 | Order history and order detail | feature | E10 |
| S27 | Address book | feature | E10 |
| S28 | Downloads and licence keys | feature | E11 |
| S29 | Profile and notification preferences | feature | E10 |
| S30 | Claim a guest order | feature | E10 |
| S31 | Guest order lookup | feature | E12 |

### M5 — Search

| # | Issue | Type | Needs |
|---|---|---|---|
| S32 | Search page with facets | feature | E13 |
| S33 | Header search with live suggestions | feature | E13 |

### M6 — Visual editor

| # | Issue | Type | Needs |
|---|---|---|---|
| S34 | Block foundation: conditional registration, `StorefrontContext`, sample product, resource mapping | feature | — |
| S35 | Catalog blocks: Product Grid, Category Grid, Related Products, Recently Viewed | feature | E8, E19 |
| S36 | Product blocks: Single Product, Product Gallery, Price, Add-to-Cart, Reviews | feature | E9 |
| S37 | Cart Contents and Checkout Steps blocks | feature | E1 |
| S38 | Default templates and patterns | feature | — |

### M7 — Hardening and release

| # | Issue | Type | Needs |
|---|---|---|---|
| S39 | SEO: meta, canonical, noindex, structured data, seo package integration | feature | — |
| S40 | Extension surface: hooks, filters, and registries documented and tested | feature | E16 |
| S41 | Performance: caching, eager loading, lazy sections, responsive images | task | — |
| S42 | `es`, `fr`, `de` catalogues + translation lint | task | — |
| S43 | Accessibility audit (WCAG 2.2 AA) and fixes | task | — |
| S44 | Browser test suite | task | — |
| S45 | Documentation | documentation | — |
| S46 | v1.0 release | task | — |

M0 and the visual-editor foundation (S34) can start now. Catalog and product work waits mainly on E8/E9; checkout waits on E1–E4, which are the critical path for the whole package.

## 16. Open questions

1. **Route prefixes:** `shop` and `account` are common words a host may already use. Both are configurable; is `account` the right default next to starter kits that use `/settings`?
2. **Shared display components:** the admin and storefront each ship `x-artisanpack-ec-money` / address components. Worth extracting into the engine (as anonymous Blade components) or a small shared package after 1.0?
3. **Order-edit payment follow-ups:** the admin's order edit can require extra payment (`payment_action_required`, plan §7.6). A customer-facing "complete payment" page is not in this spec.
4. **Tax display by location before an address exists:** this spec shows store-default tax treatment until the shopper gives an address; a geo-IP satellite could refine it.

## 17. Traceability against tracker #53

| Acceptance criterion | Status |
|---|---|
| Repo created at `ArtisanPack-UI/ecommerce-storefront-livewire` | Done; scaffolded from `package-blueprint`, description and topics set |
| Per-package spec drafted | This document |
| Comprehensive issue set opened on the new repo (with `ecosystem: ecommerce` label) | §15 — 46 issues, plus 20 engine issues (§9) and 5 component-library issues (§10) |
| Tracker closed with a link to the new repo | Closed once the issue set is open |

## 18. Changelog

- **2026-10-05** — v0.2. D4–D9 confirmed: routes + components, client-render payment contract with Stripe Payment Element and redirect drivers, host-owned auth, search with basic facets, blocks in this package.
- **2026-10-05** — Draft v0.1. Written against parent plan v2, engine spec, engine `release/1.0` (c5144b7), `livewire-ui-components` 2.1.0, `visual-editor` 1.12.1, and the admin spec v0.2.
