---
title: Advanced Overview
---

# Advanced

Commands, internals, and contributor tooling.

## In this section

- [Artisan Commands](Advanced-Artisan-Commands) - `ecommerce-storefront:install` and the engine commands a store uses with the storefront
- [Performance](Advanced-Performance) - Query counts, caching, lazy sections, and images
- [Browser Tests](Advanced-Browser-Tests) - Running and writing the Playwright browser suite

## How a request flows

```
Route → StorefrontPageController (sets StorefrontContext and StorefrontSeo, returns a page view)
          → page view  @extends( $ecommerceStorefrontLayout )
              → a visual-editor template, when one applies
              → otherwise <livewire:artisanpack-ecommerce-storefront-… />
                   → engine services   (cart, checkout, orders: writes)
                   → engine queries    (catalog, prices, stock: reads)
```

- **Pages are controller actions** returning a view that embeds one Livewire component. They are not full-page Livewire routes, so `route:cache` works and the layout can be swapped (`storefront.layout`).
- **Writes go through the engine.** The cart, checkout, reviews, and account actions call engine services; the storefront owns no tables.
- **Private pages stay private.** Cart, checkout, order, lookup, and account responses are sent with `Cache-Control: no-store, private`, `X-Robots-Tag: noindex, nofollow`, and `Referrer-Policy: same-origin`. See [SEO](Seo).

## Rate limits

Shopper actions run through the engine's rate limit policies. They count against the shopper's cart, except claiming a guest order, which counts against the signed-in customer. Over the limit, the action doesn't run and the shopper is told to wait.

| Policy | Covers |
| --- | --- |
| `ecommerce.cart.mutate` | Adding, updating, and removing cart lines, choosing a shipping rate, and starting a payment |
| `ecommerce.coupon.attempt` | Applying a coupon |
| `ecommerce.checkout.finalize` | Placing an order |
| `ecommerce.review.submit` | Submitting a review |
| `ecommerce.lookup.attempt` | Guest order lookup |
| `ecommerce.claim.attempt` | Claiming a guest order |
| `ecommerce.catalog.read` | Header search suggestions |

The limits are engine settings (`artisanpack.ecommerce.rate_limits`).

## Account middleware

The account routes use `account.middleware` (`[ 'web', 'auth' ]` by default; add `verified` if you need it). The storefront also registers those middleware classes as Livewire persistent middleware, so Livewire re-runs them on every update request from an account page.

## Registering routes yourself

Set `storefront.routes_enabled` to `false` and register the routes in your own route file, or embed the components on your own pages. See [Embedding Components](Extending-Embedding-Components). `routes/storefront.php` and `routes/account.php` in the package are the reference.
