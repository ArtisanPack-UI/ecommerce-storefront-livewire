---
title: Screens
---

# Screens

Storefront paths are relative to `storefront.route_prefix` (`/shop` by default) and route names to `artisanpack.ecommerce.storefront.`, so `product` is `artisanpack.ecommerce.storefront.product`. Account paths are relative to `account.route_prefix` (`/account`) and route names to `artisanpack.ecommerce.account.`. Account routes run `account.middleware` (`web`, `auth` by default).

Every page is a controller action in `Http\Controllers\StorefrontPageController` that renders a view in `resources/views/pages/` extending `storefront.layout`, with one Livewire component in it. Component names below drop the `artisanpack-ecommerce-storefront-` prefix, so `catalog` is `<livewire:artisanpack-ecommerce-storefront-catalog />`. To route the pages yourself, set `storefront.routes_enabled` to `false` and embed the components (see [Embedding Components](Extending-Embedding-Components)).

## Storefront

| Path | Route name | Component | What it does |
| --- | --- | --- | --- |
| `/` | `catalog` | `catalog` | Every storefront-visible product, with filters, sort, and pagination. |
| `/category/{path}` | `category` | `category-show` | A category: breadcrumbs, header, sub-category chips, and the catalog scoped to it and its sub-categories. |
| `/tag/{tag}` | `tag` | `tag-show` | A tag's name and the catalog scoped to it. |
| `/products/{product}` | `product` | `product-show` | The product page. |
| `/search` | `search` | `search` | Search results for `?q=`. |
| `/cart` | `cart` | `cart` | The cart page. |
| `/checkout` | `checkout` | `checkout` | The checkout. See [Checkout](Checkout). |
| `/checkout/return` | `checkout.return` | None (redirect) | Where a payment provider sends the shopper back. See [Checkout](Checkout#placing-the-order). |
| `/orders/{order}/confirmation` | `confirmation` | `order-confirmation` | The order confirmation. |
| `/order-lookup` | `lookup` | `order-lookup` | Guest order lookup by email and order number. |
| `/order/{token}` | `order-view` | `account-order` | A guest's order, opened with a signed order-view token. Read-only. |

`{path}` is the category's slug chain (`clothing/shirts`). A chain that isn't the category's canonical one redirects there permanently, keeping the query string. `{product}` and `{tag}` are slugs; an unknown or hidden one is a 404, and so is a product whose type's satellite isn't installed. `{order}` is the order id.

## Account

| Path | Route name | Component | What it does |
| --- | --- | --- | --- |
| `/` | `dashboard` | `account-dashboard` | Greeting, latest orders, default addresses, download count, and links. |
| `/orders` | `orders.index` | `account-orders` | Order history. |
| `/orders/{order}` | `orders.show` | `account-order` | One order. |
| `/addresses` | `addresses` | `account-addresses` | The address book. |
| `/downloads` | `downloads` | `account-downloads` | Downloads and licence keys. |
| `/downloads/{download}` | `downloads.file` | None (file) | Sends the file as an attachment and spends a download. |
| `/downloads/{download}/stream` | `downloads.stream` | None (file) | Streams a streaming-only file in the browser's player. |
| `/profile` | `profile` | `account-profile` | Name, phone, marketing consent, and notification preferences. |
| `/claim` | `claim` | `account-claim` | Attach guest orders to the account. |

`{download}` is an entitlement id. Another customer's entitlement is a 404. One that is expired, used up, or streaming-only sends the shopper back to the downloads page with a toast saying why.

Both file routes only accept temporary signed links, so a cross-site request or a link prefetcher can't spend a download. The downloads page renders them fresh on each visit: a download link works for 10 minutes and a stream link for 4 hours. An unsigned or expired link is a 403. To link to a file from your own page, sign the URL yourself:

```php
use Illuminate\Support\Facades\URL;

URL::temporarySignedRoute( 'artisanpack.ecommerce.account.downloads.file', now()->addMinutes( 10 ), [ 'download' => $download->id ] );
```

### Account navigation

Every account page sits in `<x-artisanpack-ec-account-shell>`, whose menu lists the storefront's own pages (dashboard, orders, addresses, downloads, profile, and "Claim an order", which only shoppers who may claim see), then the entries satellites register with the engine's `AccountMenuRegistry` that the shopper may see, all sorted by `position`. The storefront's own entries use positions 10–60. The engine's core `ecommerce.account.*` entries (profile, orders, addresses, downloads, licence keys, notifications) are left out, since the storefront's own pages already cover them. See [Hooks Reference](Extending-Hooks-Reference#registries).

## Catalog

The catalog lists products from the engine's `CatalogQuery`.

- **Sort**: `newest`, `price`, `-price`, `popularity`, `rating`, `name`. The default is `catalog.default_sort`. Add or remove sorts with the `ap.ecommerceStorefrontLivewire.catalog.sorts` filter.
- **Page size**: `catalog.per_page` (24), with the choices in `catalog.per_page_values`.
- **Filters** (a sidebar on desktop, a drawer on mobile): category tree, tag, price range, attribute values, in stock, on sale, and minimum rating. Each option shows a facet count. A group's own counts ignore its own selection, and options with no results are disabled, not hidden. `catalog.filter_attributes` limits which attributes can be filtered on. Add or remove groups with the `ap.ecommerceStorefrontLivewire.catalog.filters` filter.

Everything lives in the query string:

| Parameter | Holds |
| --- | --- |
| `sort` | Sort key |
| `per_page` | Page size |
| `page` | Page |
| `category`, `tag` | Category and tag filters |
| `price_min`, `price_max` | Price range |
| `attr` | Attribute values |
| `in_stock`, `on_sale` | Toggles |
| `rating` | Minimum rating |
| `f[{key}]` | A filter group added through the filter hook |

Product cards show the image, name, price, rating, and stock. Prices are the signed-in customer's (passed to the engine's `PriceDisplayResolver`), so a customer-group price shows in listings as it does in the cart. Simple products get a quick "Add to cart" button. Other types need a choice made on the product page (a variant, a licence type, options), so they get none, and quick add refuses them if called directly. With `catalog.show_stock_count` on, low-stock products say "Only 3 left" (the engine's `inventory.show_quantity` must be on).

Products whose type's satellite isn't installed are left out of every listing, search, and header suggestion. A malformed query string (`?per_page=abc`, `?sort[]=x`) is ignored rather than causing an error.

The category page adds breadcrumbs, the category's name, description, and image, and sub-category chips. The tag page has the catalog's filters, less the tag filter.

## Product

- **Gallery**: the featured image, gallery images, and variant images, with thumbnails, zoom, and a lightbox. It switches to the chosen variant's image.
- **Summary**: name, rating summary linking to `#reviews`, price, short description, stock status, and SKU, all following the chosen variant.
- **Purchase form**: the product type's form from `ProductFormRegistry`, or "can't be purchased online" when the type has none.
- **Details**: description, an attributes table, and shipping and returns content from the `ap.ecommerceStorefrontLivewire.product.shippingReturns` filter, in an accordion.
- **Sections** below the details, from the `ap.ecommerceStorefrontLivewire.product.sections` filter: `reviews` (position 10), `upsells` ("You may also like", 20), and `related` (30).

Opening the page records a view through the engine (`ap.ecommerce.product.viewed`).

### Purchase forms

| Type | Form |
| --- | --- |
| `simple` | Quantity and "Add to cart". Out of stock or unpriced disables the button and says why. |
| `variable` | The variation picker: one swatch or pill group per attribute. An option no available variant offers with the earlier choices is disabled, with the reason ("Out of stock" or "Not available with your other choices"). A choice that rules out a later one clears it and says so. The matched variant goes in the query string as `?variant=`. "Add to cart" stays disabled, naming the missing choice, until a variant is matched. |
| `grouped` | Each child product with its own price, stock, and quantity. One "Add to cart" adds every product with a quantity as its own line. A refused product shows the reason next to its quantity; the others are still added. |
| `bundled` | What the bundle includes and how many of each, then quantity and "Add to cart". The bundle is one line at its own price. |
| `digital` | What the shopper gets: files, download limit and expiry, and a licence key when the product issues them. When the product offers licence types, the shopper picks one. |
| Any other | A type with no registered form whose engine type implements `ProvidesStorefrontOptions` gets a generic form built from its fields. |

### Reviews

Rendered under `#reviews`:

- A histogram of approved reviews by rating. Each row filters the list to that rating (`?review-rating=`).
- Approved reviews, newest first, `reviews.per_page` (5) a page (`?reviews-page=`), with a "Verified purchase" badge.
- **Write a review** when the engine's `ReviewService::eligibility()` allows it, otherwise the reason. Guests also give a name and email.
- A signed-in shopper the engine can't link to a customer record (an unverified account whose email belongs to a guest customer, or a user with a non-numeric id) can't review. The reason is `Reviews::CUSTOMER_UNAVAILABLE`, shown as "Verify your email address to review this product." or "You can't review this product.", instead of the review being posted anonymously.

The title and text are stored as plain text, with control characters removed, and printed escaped, so "Love it <3" is kept whole.

Who may review and moderation are engine settings.

### Related products

Related products, upsells, and cart cross-sells come from the engine's `RelatedProducts`, up to `related.limit` (4). They load after the page with skeleton cards and render nothing when there is nothing to suggest. On small screens the cards scroll sideways with "Previous" and "Next" buttons.

Recently viewed (`recently-viewed`) shows the products the recently-viewed satellite reports through the `ap.ecommerceStorefrontLivewire.recentlyViewed.productIds` filter. Of the first 50 ids, it drops hidden products and products of uninstalled types, then shows up to its `limit`.

## Cart

**Cart page:**

- Lines with image, name, options, unit price, a quantity stepper, line total, and "Remove" with "Undo".
- A coupon field, with the engine's message under it.
- Totals: subtotal, each promotion's discount, shipping (the chosen estimate, "Free", or "Calculated at checkout"), tax (marked estimated until the engine knows a destination), and total.
- A shipping estimate by country, region, and postcode. The rate chosen here carries into checkout.
- Lines that can't be bought are flagged with the reason and a "Remove" button. A line whose quantity is more than the stock left says so ("Only 2 are left in stock.") and keeps its quantity stepper. "Checkout" is disabled, saying why, until the lines are removed or lowered.
- Sections below the cart from the `ap.ecommerceStorefrontLivewire.cart.sections` filter: cross-sells by default.

**Cart drawer** (`cart-drawer`): the lines with a quantity stepper and "Remove", the subtotal, and "View cart" and "Checkout" links. It opens on the `ecommerce-cart-open` browser event, closes on `ecommerce-cart-close`, Escape, or the overlay, and returns focus to whatever opened it. It is mounted once by `ecommerce-storefront::partials.global`, so it works in your own layout too. It lists the lines only once it has first opened, showing a loading state (`data-cart-loading`) until then, so pages don't load the cart's lines on every visit.

**Header cart button** (`cart-button`): a link to the cart page with a count badge. When the drawer is on the page, it opens the drawer instead.

**After "Add to cart"** (product page and quick add), `cart.after_add` decides what happens:

| Value | Result |
| --- | --- |
| `drawer` (default) | Opens the cart drawer |
| `toast` | Shows an "Added to your cart" toast |
| `none` | Only the cart count updates |

**Merge prompt** (`cart-merge-prompt`): when a shopper signs in with a guest cart in a different currency from their account cart, a modal asks them to keep the guest currency, switch to the account currency, or not merge. Same-currency carts are merged by the engine without asking. If the guest cart expired before the shopper chose, the prompt closes with "Your guest cart has expired." and the account cart is left unchanged. Switching to the account currency also makes it the shopper's display currency. See [Guest Checkout](Checkout-Guest-Checkout).

## Currency switcher

`currency-switcher` renders only when the store sells in more than one currency. Each option names the currency, its code, and symbol ("Euro (EUR, €)"). A choice is stored through the engine's `CurrencyResolver` and its currency cookie, an existing cart is re-priced through `StorefrontCartService::changeCurrency()`, and the page reloads with a toast. If the cart can't be re-priced, the choice is undone and the shopper told why.

Prices are shown in the open cart's currency when there is one, so a shopper whose account cart is in euros (opened on another device, or after a merge) sees euro prices even if they last chose dollars. Without a cart, it is the currency the engine's `CurrencyResolver` returns. The switcher is the only way to change a cart's currency, since that re-prices it. From PHP, `StorefrontCart::currency()` gives the display currency, `rememberCurrency( $code )` stores a choice (without re-pricing the cart), and `customer()` gives the signed-in shopper's engine customer.

## Search

**Search page:** the term goes to the engine's active `SearchProvider`. Results fill the catalog grid, with a filter panel built from the provider's facets. Relevance is the default sort. Provider suggestions show as "Did you mean …?" links. An empty term invites a search; a term over 200 characters is an error; a failing provider shows "search is unavailable". Each search counts against the engine's `ecommerce.catalog.read` limiter; over it, the page shows "Slow down" until the shopper can search again. The term (`q`), filters, sort, and page live in the query string. Filter groups added with an `apply` callback are left out here.

**Header search** (`search-box`): as the shopper types, up to five products (image, name, price) and five categories open in a popover. It is an ARIA combobox: arrow keys move through results, Enter opens the highlighted one or the search page, Escape closes. Without JavaScript it is a plain form to the search page. Embed it with `<x-artisanpack-ec-search-box />`.

## Account

| Screen | Details |
| --- | --- |
| Dashboard | Latest orders, default shipping and billing addresses, download count, and links to the account pages and to the host's profile and password screens (`auth.profile_route`, `auth.password_route`). Prompts the shopper to claim unclaimed guest orders placed with their verified email. |
| Orders | Newest first, with number, date, status, total, and item count. Filter by open, completed, or cancelled (`?status=`). |
| Order | Number, date, status, lines, totals, addresses, payment method, shipments with tracking links, refunds, notes the store shared, and downloads. **Buy again** adds the lines that can still be bought, at today's prices, and says which couldn't be. |
| Addresses | Add and edit in a modal, delete after confirming, and set the default shipping or billing address. The first address becomes both defaults. Writes count against the `ecommerce.cart.mutate` rate limit (see [Rate limits](Advanced#rate-limits)). |
| Downloads | Each entitlement's product, file, version, downloads left, and expiry, with a download button (or a play button for streaming-only files). Licence keys in full with a copy button, activation count, and expired or revoked state. |
| Profile | First and last name, phone, marketing consent, and notification preferences per channel. Order and account emails are always on. Marketing switches are locked off without consent, and withdrawing consent switches marketing off everywhere. Email and password link to the host's screens. |
| Claim | The shopper gives one order's number and shipping postcode. When they match an unclaimed guest order under the account's email, every guest order under that email is attached to the account. Only a user whose model implements `MustVerifyEmail` and who has verified may claim, unless `account.claim_requires_verified_email` is off (see [Configuration](Installation-Configuration#customer-account-routes)). |

A signed-in user with no engine customer record yet sees empty pages, and gets a record on their first save.

## Orders after checkout

**Confirmation** (`order-confirmation`): the order number, status, lines, totals, addresses, payment method, and downloads the order unlocked. The signed-in owner can always open it; anyone else needs the signed `token` checkout put in the link, otherwise it's a 404. It only reads the order, so refreshing changes nothing. Owners get a link to the order in their account; guests get "create an account" and "look up this order later" hints.

**Order lookup** (`order-lookup`): a guest enters the order's email and number. A match opens the signed order page, which stops working after `order_lookup.link_ttl_minutes` (60). Every failure gets the same message, and the engine locks out an order number or address after repeated failures.

**Signed order view** (`order-view`): the order read-only, opened from a lookup or from a guest confirmation email. A malformed, tampered, or expired token is a 404. The storefront points the engine's `checkout.order_view_url` at this route (unless you set your own), so guest emails link here. Email links last the engine's `checkout.order_view_ttl_days`.
