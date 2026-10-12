---
title: Embedding Components
---

# Embedding Components

Every storefront screen is a Livewire component you can place on your own pages. Use this when the storefront's routes don't fit your site: a product on a landing page, the cart inside your own account area, or the whole store under your own URLs.

## Turning the storefront routes off

```php
// config/artisanpack/ecommerce-storefront-livewire.php
'storefront' => [
    'routes_enabled' => false,
    // …
],
```

With `routes_enabled` off, the package registers none of its storefront routes (`artisanpack.ecommerce.storefront.*`) and none of its account routes (`artisanpack.ecommerce.account.*`). The Livewire and Blade components are still registered, so you can embed them anywhere. The storefront also stops pointing the engine's guest order links at its order page: set `artisanpack.ecommerce.checkout.order_view_url` (a URL with a `{token}` placeholder) yourself if you send guests a link to their order.

You can also leave the routes on and embed components on other pages as well.

## Components

All names start with `artisanpack-ecommerce-storefront-`. Props marked *locked* can't be changed from the browser.

### Catalog and search

| Component | Props | Shows |
| --- | --- | --- |
| `artisanpack-ecommerce-storefront-catalog` | `category` (id or slug), `tag` (id or slug), `featured` (bool), `ids` (array of product ids), `heading` (string or null), `filterable` (bool, default `true`); all locked | Product listing with filters, sorting, and pages. Sorting, page size, and filters are kept in the query string. |
| `artisanpack-ecommerce-storefront-category-show` | `category` (a `ProductCategory`) | A category page: breadcrumbs, heading, description, and its products |
| `artisanpack-ecommerce-storefront-tag-show` | `tag` (a `ProductTag`) | A tag page |
| `artisanpack-ecommerce-storefront-product-grid` | `source`, `category`, `tag`, `ids`, `sort`, `limit`, `columns`, `showPrice`, `showRating`, `showAddToCart`, `heading`; all locked | A fixed grid of products, as the Product Grid block. See [Blocks](Visual-Editor-Blocks) for the values. |
| `artisanpack-ecommerce-storefront-search` | none (reads `q` from the query string) | Search results |
| `artisanpack-ecommerce-storefront-search-box` | `inputId` (default `ecommerce-header-search`) | The header search box with live suggestions. Also available as `<x-artisanpack-ec-search-box>`. |

### Product

| Component | Props | Shows |
| --- | --- | --- |
| `artisanpack-ecommerce-storefront-product-show` | `product` (a `Product`, required), `variantId` (int or null), `recordView` (bool, default `true`) | The whole product page. Returns 404 for a product that isn't storefront-visible or whose type is missing. With `recordView`, fires the engine's `ap.ecommerce.product.viewed` once. |
| `artisanpack-ecommerce-storefront-product-reviews` | `product`, `reviewsPerPage` (int or null), `allowForm` (bool) | Ratings, reviews, and the review form |
| `artisanpack-ecommerce-storefront-related-products` | `product` (or null), `type` (`related`, `upsell`, `cross_sell`), `limit`, `columns`, `heading` | Suggested products. Without a product and with `type="cross_sell"`, cross-sells for the cart. Loads lazily. |
| `artisanpack-ecommerce-storefront-recently-viewed` | `product` (excluded), `limit`, `columns`, `heading` | Recently viewed products, from the recently-viewed satellite. Loads lazily. |

The purchase forms (`…-product-form-simple`, `-variable`, `-grouped`, `-bundled`, `-digital`) are rendered by the product page for the product's type; you don't usually embed them yourself.

### Cart and checkout

| Component | Props | Shows |
| --- | --- | --- |
| `artisanpack-ecommerce-storefront-cart` | `showCrossSells` (bool), `showCoupon` (bool) | The cart page |
| `artisanpack-ecommerce-storefront-cart-drawer` | none | The slide-out cart. Part of the global partial. |
| `artisanpack-ecommerce-storefront-cart-button` | none | The header cart button |
| `artisanpack-ecommerce-storefront-cart-merge-prompt` | none | The prompt after sign-in when the guest cart and the account cart both have items. Part of the global partial. |
| `artisanpack-ecommerce-storefront-currency-switcher` | none | The currency menu |
| `artisanpack-ecommerce-storefront-checkout` | `layoutOverride` (`''`, `multi_step`, or `single_page`) | Checkout. Empty uses the store's layout setting. |

### Orders and account

| Component | Props | Shows |
| --- | --- | --- |
| `artisanpack-ecommerce-storefront-order-confirmation` | `order` (order id), `token` (signed order-view token, for guests) | The confirmation page. 404 unless the signed-in shopper owns the order or the token is valid for it. |
| `artisanpack-ecommerce-storefront-order-lookup` | none | Guest order lookup |
| `artisanpack-ecommerce-storefront-account-dashboard` | none | Account overview |
| `artisanpack-ecommerce-storefront-account-orders` | none | Order history |
| `artisanpack-ecommerce-storefront-account-order` | `order` (order id), `token` (optional; shows the order read-only to a guest) | One order |
| `artisanpack-ecommerce-storefront-account-addresses` | none | Address book |
| `artisanpack-ecommerce-storefront-account-downloads` | none | Downloads and licence keys |
| `artisanpack-ecommerce-storefront-account-profile` | none | Profile and preferences |
| `artisanpack-ecommerce-storefront-account-claim` | none | Claim a guest order |

Put the account components behind your `auth` middleware. The storefront registers the classes in `account.middleware` as Livewire persistent middleware, so Livewire re-runs the ones your page route has on every update from that page.

## What every page still needs

The cart drawer, the toast container, and the cart merge prompt come from one partial. Include it once in the layout of every page that embeds storefront components, after the content:

```blade
@include( 'ecommerce-storefront::partials.global' )
```

It renders once per response even if it is included twice. Without it, "Add to cart" has no drawer to open and no toasts appear.

Your layout also needs Livewire's styles and scripts, and your Tailwind build must scan the storefront's views. See [Front-End Setup](Installation-Front-End) and [Layouts](Installation-Layouts).

The SEO tags and the visual-editor context are set by the storefront's own page controller. On your own routes, set what you need yourself: describe the page through `ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontSeo` and include `ecommerce-storefront::partials.seo` in your `<head>` (see [SEO](Seo)), and set `StorefrontContext` if the page renders commerce blocks (see [Blocks](Visual-Editor-Blocks)).

## Links when the routes are off

Components build links only to routes that exist (`Route::has()`). With the storefront routes off:

| Missing route | What changes |
| --- | --- |
| `…storefront.product` | Product cards, cart lines, and search suggestions show the product name without a link |
| `…storefront.category` | Category tiles and breadcrumbs lose their links |
| `…storefront.catalog` | No "Continue shopping" buttons; no "Shop" breadcrumb |
| `…storefront.cart` | The header cart button renders nothing; the drawer has no "View cart" button; checkout can't send the shopper back to the cart when the cart changes, and shows the problem in place |
| `…storefront.checkout` | The cart and drawer have no "Checkout" button |
| `…storefront.checkout.return` | The checkout passes no `return_url` to the payment gateway |
| `…storefront.confirmation` | After placing an order, checkout stays on the page and shows "Your order {number} is placed." instead of redirecting |
| `…storefront.search` | The header search has no "See all results" option and its form has no search page to submit to |
| `…storefront.lookup` | No "Find an order" links |
| `…account.*` | Account links and the matching account navigation entries are hidden; the confirmation has no "View in your account" button |
| `…account.downloads.file` | Downloads are listed without a download link |

The order lookup always sends a matched shopper to the `artisanpack.ecommerce.storefront.order-view` route, so it needs that route.

To get the links back, register your own routes under the storefront's names. The components look links up by name and pass these parameters:

| Route name | Parameters |
| --- | --- |
| `artisanpack.ecommerce.storefront.catalog` | none |
| `artisanpack.ecommerce.storefront.category` | `path` (the category's slug chain, e.g. `clothing/shirts`) |
| `artisanpack.ecommerce.storefront.tag` | `tag` (slug) |
| `artisanpack.ecommerce.storefront.product` | `product` (slug) |
| `artisanpack.ecommerce.storefront.search` | none (`q` in the query string) |
| `artisanpack.ecommerce.storefront.cart` | none |
| `artisanpack.ecommerce.storefront.checkout` | none |
| `artisanpack.ecommerce.storefront.confirmation` | `order` (id), `token` (query, for guests) |
| `artisanpack.ecommerce.storefront.lookup` | none |
| `artisanpack.ecommerce.storefront.order-view` | `token` |

`routes/storefront.php` and `routes/account.php` in the package are the reference. The checkout return and digital download routes use `StorefrontPageController` actions you can point your own routes at.

## Example: a product page on your own route

```php
// routes/web.php
use ArtisanPackUI\Ecommerce\Models\Product;
use Illuminate\Support\Facades\Route;

// Fixed paths first: the {product} wildcard below would also match /store/cart.
Route::view( 'store/cart', 'store.cart' )->name( 'artisanpack.ecommerce.storefront.cart' );

Route::get( 'store/{product}', function ( string $product ) {
    $model = Product::query()->storefrontVisible()->where( 'slug', $product )->firstOrFail();

    return view( 'store.product', [ 'product' => $model ] );
} )->name( 'artisanpack.ecommerce.storefront.product' );
```

```blade
{{-- resources/views/store/product.blade.php --}}
<x-layouts.app :title="$product->name">
    <livewire:artisanpack-ecommerce-storefront-product-show :product="$product" />

    <livewire:artisanpack-ecommerce-storefront-related-products :product="$product" type="upsell" />

    @include( 'ecommerce-storefront::partials.global' )
</x-layouts.app>
```

```blade
{{-- resources/views/store/cart.blade.php --}}
<x-layouts.app :title="__( 'Cart' )">
    <livewire:artisanpack-ecommerce-storefront-cart :show-cross-sells="false" />

    @include( 'ecommerce-storefront::partials.global' )
</x-layouts.app>
```

Because the routes use the storefront's names, product cards link to `/store/{slug}` and the header cart button links to `/store/cart`. Pages that hold a shopper's cart or orders should not be cached or indexed; the storefront's own routes send `Cache-Control: no-store, private` and `X-Robots-Tag: noindex, nofollow` on them.
