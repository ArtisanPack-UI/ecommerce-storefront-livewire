---
title: Usage Overview
---

# Usage

What shoppers get from the storefront, and the behaviour every page shares.

## In this section

- [Screens](Usage-Screens) - Every page, its URL, route name, Livewire component, and what it does

Checkout has its own section: see [Checkout](Checkout).

## What shoppers get

- **Browsing.** A catalog with filters, sorts, and pagination; category and tag pages; search with a header search box; product pages with a gallery, a purchase form per product type, reviews, and related products.
- **Cart.** A cart page, a slide-out cart drawer, and a header cart button with a count. A currency switcher when the store sells in more than one currency.
- **Checkout.** Contact, address, shipping, review, and payment, as a multi-step wizard or a single page. Confirming the payment places the order. See [Checkout](Checkout).
- **After the order.** A confirmation page, a guest order lookup, and signed order links for guests.
- **Account.** A dashboard, order history and order detail, an address book, downloads and licence keys, a profile with notification preferences, and a page to claim guest orders.

## Behaviour every page shares

- **Shareable URLs.** Catalog filters, sort, page size, and page, the search term, the chosen variant, the review rating filter, the order-history status filter, and the checkout step live in the query string, so a link reopens the same view and back and forward work.
- **Private pages.** Cart, checkout, confirmation, order lookup, signed order view, and every account page are sent with `Cache-Control: no-store, private`, `X-Robots-Tag: noindex, nofollow`, and `Referrer-Policy: same-origin`. Search results are `noindex, follow`.
- **Owned records.** An order or address that isn't the shopper's is a 404, so its existence isn't given away. Order lookup and claim give one message for every failure, for the same reason.
- **Rate limits.** Cart changes, coupon attempts, reviews, lookups, claims, header search suggestions, and placing the order count against the engine's limiters (`ecommerce.cart.mutate`, `ecommerce.coupon.attempt`, `ecommerce.review.submit`, `ecommerce.lookup.attempt`, `ecommerce.claim.attempt`, `ecommerce.catalog.read`, `ecommerce.checkout.finalize`). Over a limit, the shopper is told how long to wait.
- **Feedback.** Cart changes and checkout steps are announced in live regions. "Add to cart" opens the cart drawer, shows a toast, or only updates the count, per `cart.after_add` (see [Screens](Usage-Screens#cart)).
- **Money** comes from the engine in integer minor units and is formatted with the shopper's currency. Prices follow the currency the shopper chose.
