---
title: Hooks Reference
---

# Hooks Reference

Every seam a satellite or host can use to change the storefront without publishing views (spec §8.4). Filters and actions use the `artisanpack-ui/hooks` helpers; registries are singletons resolved from the container. Register everything from a service provider's `boot()`.

Filters receive the value as the first argument and must return it; extra arguments need the `$acceptedArgs` count when you add the callback (`addFilter( $hook, $callback, 10, 3 )`). A filter that returns the wrong type is ignored and the storefront's own value is used.

## Registries

| Registry | Method | Use |
| --- | --- | --- |
| `ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry` | `register( string $typeKey, string $component )` | The purchase form for a product type. The component gets `product` and should extend `Livewire\Product\Forms\PurchaseForm`. |
| `ArtisanPackUI\EcommerceStorefrontLivewire\Registries\PaymentDriverRegistry` | `register( string $driver, string $component )` | The payment UI for a gateway's client-render `driver`. Report `payment-confirmed` / `payment-failed` to the checkout. |
| `ArtisanPackUI\Ecommerce\Registries\AccountMenuRegistry` (engine) | `register( string $key, array $entry )` | An account navigation entry: `label`, `icon`, `route`, `position`, `visible`. |
| `ArtisanPackUI\Ecommerce\Contracts\ProvidesStorefrontOptions` (engine) | `storefrontOptions( Product ): array` on a product type | Fields for the generic purchase form when a type has no registered form. |

```php
use ArtisanPackUI\Ecommerce\Registries\AccountMenuRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry;

app( ProductFormRegistry::class )->register( 'subscription', 'subscriptions-purchase-form' );

app( AccountMenuRegistry::class )->register( 'wishlist', [
    'label'    => __( 'Wishlist' ),
    'icon'     => 'o-heart',
    'route'    => 'wishlist.index',
    'position' => 40,
] );
```

## Catalog

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.catalog.filters` | filter | Building the catalog, category, tag, and search filter panels | `array $filters` (key => definition), `array $context` (`category`, `tag`, `featured`) | Filter definitions. Unset a core key (`category`, `tag`, `price`, `attributes`, `in_stock`, `on_sale`, `rating`) to remove it; add a key with `type`, `label`, `options`, and an `apply( CatalogQuery $query, array\|bool $value )` callback. |
| `ap.ecommerceStorefrontLivewire.catalog.sorts` | filter | Building the sort menu | `array $sorts` (engine sort key => label) | Sort options. Keys must be engine sorts. |
| `ap.ecommerceStorefrontLivewire.catalog.productCard` | filter | Rendering every product card (grids, related products, search, blocks) | `array $card`, `Product $product` | Card data: `name`, `url`, `image`, `price`, `stock`, `rating`, `reviews`, `quick_add`, `badges` (texts), `actions` (key => view name rendered with `$product`, or `Htmlable`, shown over the image), `heading_level`. |

```php
// A wishlist heart on every product card.
addFilter( 'ap.ecommerceStorefrontLivewire.catalog.productCard', function ( array $card, Product $product ): array {
    $card['actions']['wishlist'] = 'wishlist::heart';

    return $card;
}, 10, 2 );
```

## Product page

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.product.sections` | filter | Rendering the sections below the product (reviews, upsells, related) | `array $sections` (key => `component`, `params`, `position`), `Product $product` | Sections. Each component also receives `product`. |
| `ap.ecommerceStorefrontLivewire.product.shippingReturns` | filter | Rendering the "Shipping & returns" tab | `string $html`, `Product $product` | HTML (cleaned with `kses` rules); empty hides the tab. |
| `ap.ecommerceStorefrontLivewire.recentlyViewed.productIds` | filter | Rendering the Recently Viewed section | `array $ids`, `int $limit`, `?Customer $customer`, `?Product $exclude` | Product ids, most recent first. Answered by the recently-viewed satellite. |
| `ap.ecommerce.product.viewed` (engine) | action | Once per product page view (not on Livewire updates or editor previews) | `Product $product`, `?Customer $customer` | — |

## Cart and checkout

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.cart.sections` | filter | Rendering the sections below the cart page (cross-sells by default) | `array $sections` (key => `component`, `params`, `position`), `?Cart $cart` | Sections. Each component except `cross-sells` also receives `cart`. |
| `ap.ecommerceStorefrontLivewire.checkout.steps` | filter | Building the checkout steps | `array $steps`, `Cart $cart` | Steps. Add one with `label`, `component`, and `before` (`address`, `shipping`, `payment`, or `review`, the default). Core steps can't be removed. |
| `ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder` | action | Just before the engine places the order (once per "Place order") | `Cart $cart`, `array $context` (note, IP, user agent) | — Throw a `CartOperationException` to stop the order with its message; any other exception stops it with a generic error. |

## Account and orders

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.order.statusLabel` | filter | Showing an order's status to the shopper | `string $label`, `Order $order` | The label. |

## Addresses

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.address.regions` | filter | Building a country's region select | `array $regions` (country => code => name) | Regions; a country listed here gets a select. |
| `ap.ecommerceStorefrontLivewire.address.postcodeExamples` | filter | Showing a postcode hint | `array $examples` (country => example) | Examples. |
| `ap.ecommerceStorefrontLivewire.address.postcodePatterns` | filter | Validating a postcode | `array $patterns` (country => PCRE) | Patterns; an invalid pattern is ignored. |

## Layout

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.header.actions` | filter | Rendering the package layout's header | `array $actions` (key => view name or `Htmlable`) | Header actions (`currency`, `account`, `cart` by default). |
| `ap.ecommerceStorefrontLivewire.layout.viteEntries` | filter | Loading the package layout's assets | `array $entries` | Vite entry paths. |

## Search engines

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.seo.meta` | filter | Rendering a page's head tags | `array $meta` (`title`, `description`, `canonical`, `robots`, `image`, `type`), `?string $page`, `?Model $subject` (product, category, or tag) | The tags. Unsafe URLs are dropped; descriptions are cut to 160 characters. |
| `ap.ecommerceStorefrontLivewire.seo.schema` | filter | Rendering a page's JSON-LD (or handing it to `artisanpack-ui/seo`) | `array $schemas`, `?string $page`, `?Model $subject` | JSON-LD entries; anything that isn't an array is dropped. |

`$page` is `product`, `category`, `tag`, `catalog`, `search`, `cart`, `checkout`, `confirmation`, `lookup`, `order-view`, or `account`.

## Visual editor

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.blocks` | filter | Registering the commerce blocks (visual-editor installed) | `array $classes` (`StorefrontBlock` subclasses) | Block classes to register. |
| `ap.ecommerceStorefrontLivewire.templates` | filter | Offering the default templates to the site editor (`visual_editor.templates` on) | `array $templates` (slug => `title`, `description`, `content`) | Templates; entries without a title or content are dropped. |
| `ap.ecommerceStorefrontLivewire.patterns` | filter | Offering the block patterns to the editor | `array $patterns` (slug => `title`, `description`, `content`) | Patterns. |

Template slugs a page looks for, most specific first: `single-product-{slug}` → `single-product-{type}` → `single-product`; `product-category-{slug}` → `product-category` → `product-archive`; `product-tag-{slug}` → `product-tag` → `product-archive`. A page renders through the first one saved in the site editor, and as usual when none is.
