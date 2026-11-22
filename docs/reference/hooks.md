---
title: Hooks
menu_order: 10
redirect_from: extending/hooks-reference
---

# Hooks

Every seam a satellite or host can use to change the storefront without publishing views. Filters and actions use the `artisanpack-ui/hooks` helpers; registries are singletons resolved from the container. Register everything from a service provider's `boot()`.

Filters receive the value as the first argument and must return it; every extra argument is passed to the callback, so declare only the ones you need. A filter that returns the wrong type is ignored and the storefront's own value is used.

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
} );
```

## Product page

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.product.sections` | filter | Rendering the sections below the product (reviews, upsells, related) | `array $sections` (key => `component`, `params`, `position`), `Product $product` | Sections. Each component also receives `product`. |
| `ap.ecommerceStorefrontLivewire.product.addToCartActions` | filter | Rendering the add-to-cart row of every purchase form | `array $actions`, `Product $product` | Buttons after "Add to cart", such as a wishlist heart: key => view name (rendered with `$product` and `$formId`) or `Htmlable`. Use an outline neutral icon-only Button with an accessible name. Since 2.0.0. |
| `ap.ecommerceStorefrontLivewire.product.highlights` | filter | Rendering the product's delivery and returns list | `array $highlights` (each `icon`, `title`, optional `description`), `Product $product` | The list under the purchase form ("Free shipping over $75" when `cart.free_shipping_threshold` is set). Entries without a title are dropped. |
| `ap.ecommerceStorefrontLivewire.product.soldOut` | filter | Rendering a sold-out product or variant | `string\|Htmlable $notice` (a view name; the default is the "This item is sold out" alert), `Product $product`, `?ProductVariant $variant` | What shows under the purchase form, such as a "Notify me" form. A view that doesn't exist, or anything else, shows nothing. The default alert is left out when the purchase form offers its own "Notify me" signup (see `backInStockEnabled`). |
| `ap.ecommerceStorefrontLivewire.product.backInStockEnabled` | filter | Rendering the add-to-cart row of a sold-out product or variant | `bool $enabled` (default `true`), `Product $product` | `false` turns off the row's "Notify me" back-in-stock signup, leaving the disabled button and its reason. Only applies with an engine that has `BackInStockService` (1.4+). Since 2.0.0. |
| `ap.ecommerceStorefrontLivewire.product.shippingReturns` | filter | Rendering the "Shipping & returns" tab | `string $html`, `Product $product` | HTML (cleaned with `kses` rules); empty hides the tab. |
| `ap.ecommerceStorefrontLivewire.recentlyViewed.productIds` | filter | Rendering the Recently Viewed section | `array $ids`, `int $limit`, `?Customer $customer`, `?Product $exclude` | Product ids, most recent first. Answered by the recently-viewed satellite. |
| `ap.ecommerce.product.viewed` (engine) | action | Once per product page view (not on Livewire updates or editor previews) | `Product $product`, `?Customer $customer` | — |

## Cart and checkout

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.cart.sections` | filter | Rendering the sections below the cart page (cross-sells by default) | `array $sections` (key => `component`, `params`, `position`), `?Cart $cart` | Sections. Each component except `cross-sells` also receives `cart`. |
| `ap.ecommerceStorefrontLivewire.checkout.steps` | filter | Building the checkout steps | `array $steps`, `Cart $cart` | Steps. Add one with `label`, `component`, `before` (`address`, `shipping`, `review`, the default, or `payment`), and optionally `complete`: a `callable( Cart $cart ): bool` the checkout asks on the server whether the step is done. Core steps can't be removed. An entry with a missing label or component, an invalid key, or a `complete` that isn't callable is ignored. |
| `ap.ecommerceStorefrontLivewire.checkout.paymentMethod` | filter | Rendering each gateway's card on the payment step | `array $details` (`description` and `icon`, both `null` by default), `string $key` (the gateway's key), `string $label` (its name) | The card's details: a line under the name (`description`) and an icon name at the end of its header (`icon`). An empty or non-string value isn't shown. |
| `ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder` | action | Just before the engine places the order: when the shopper confirms the payment, or presses "Place order" when nothing is due. Runs once per placement, after every added step is checked again. | `Cart $cart`, `array $context` (note, IP, user agent) | — Throw a `CartOperationException` to stop the order with its message; any other exception stops it with a generic error. |
| `ap.ecommerceStorefrontLivewire.checkout.paymentMismatch` | action | The gateway confirmed a payment that can't place the order: it was made for an earlier cart total, or for a payment session the cart has since replaced (another tab, a reload). The shopper is told to contact the store, never to pay again, and the checkout won't set up another payment for that cart. Fires once per payment, from the checkout or the payment return route; the payment is also reported. | `Cart $cart`, `string $gatewayKey`, `string $reference`, `PaymentSession $session` | — Alert staff, refund, or place the order by hand. Since 2.0.0. |
| `ap.ecommerceStorefrontLivewire.checkout.marketingConsentPending` | action | A guest ticked the marketing checkbox with the email of a customer record that already existed and hadn't opted in. The customer's `accepts_marketing` is left alone (the email isn't verified); the order's `marketing_consent` meta has `applied: false`. | `Order $order`, `Customer $customer` | — Send a confirmation (double opt-in) email. Since 2.0.0. |

A step without a `complete` callable counts as done once its component dispatches `ecommerce-checkout-step-completed` with `step: <key>`, and only while the shopper can reach it. That event comes from the browser, so a step that must hold the shopper back (an age check, say) should give `complete`.

```php
addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', function ( array $steps, Cart $cart ): array {
    $steps['age-check'] = [
        'label'     => __( 'Date of birth' ),
        'component' => 'age-check-checkout-step',
        'before'    => 'payment',
        'complete'  => fn ( Cart $cart ): bool => AgeChecks::passed( $cart ),
    ];

    return $steps;
} );
```

## Account and orders

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.order.statusLabel` | filter | Showing an order's status to the shopper | `string $label`, `Order $order` | The label. |
| `ap.ecommerce.customer.orderClaimed` (engine) | action | An order is attached to a customer: a guest order claimed from the account, or the order just placed when the shopper creates an account at checkout | `Customer $customer`, `Order $order` | — |

## Livewire events

| Event | Sent when | Parameters |
| --- | --- | --- |
| `ecommerce-cart-updated` | Any storefront component changes the cart (a browser and Livewire event) | `count` (items in the cart now), `source` (the class of the component that made the change) |
| `ecommerce-cart-open` | The cart drawer should open: after an add to cart with `cart.after_add` set to `drawer`, or when the header cart button is pressed. Dispatch it yourself to open the drawer. | — |

A listener that already followed its own change can ignore an `ecommerce-cart-updated` whose `source` is its own class; the cart page, cart drawer, and checkout do.

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
| `ap.ecommerceStorefrontLivewire.layout.navigation` | filter | Rendering the package layout's header | `array $links` (each `label`, `url`, `current`) | The store navigation ("Shop all" and the top-level categories). See [Layouts](../installation/layouts.md#store-navigation). |
| `ap.ecommerceStorefrontLivewire.layout.footerLinks` | filter | Rendering the package layout's footer | `array $columns` (key => `title`, `links`) | The footer's link columns (`shop`, `help`, `account`). See [Layouts](../installation/layouts.md#store-navigation). |
| `ap.ecommerceStorefrontLivewire.layout.viteEntries` | filter | Loading the package layout's assets | `array $entries` | Vite entry paths. |

## Search engines

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.seo.meta` | filter | Rendering a page's head tags | `array $meta` (`title`, `description`, `canonical`, `robots`, `image`, `type`), `?string $page`, `?Model $subject` (product, category, or tag) | The tags. Keys you leave out keep their value; set one to `null` to remove it. Unsafe URLs are dropped; descriptions are cut to 160 characters. |
| `ap.ecommerceStorefrontLivewire.seo.schema` | filter | Rendering a page's JSON-LD (or handing it to `artisanpack-ui/seo`) | `array $schemas`, `?string $page`, `?Model $subject` | JSON-LD entries; anything that isn't an array is dropped. |

`$page` is `product`, `category`, `tag`, `catalog`, `search`, `cart`, `checkout`, `confirmation`, `lookup`, `order-view`, or `account`.

## Visual editor

| Hook | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.blocks` | filter | Registering the commerce blocks (visual-editor installed) | `array $classes` (`StorefrontBlock` subclasses) | Block classes to register. |
| `ap.ecommerceStorefrontLivewire.templates` | filter | Offering the default templates to the site editor (`visual_editor.templates` on) | `array $templates` (slug => `title`, `description`, `content`) | Templates; entries without a title or content are dropped. |
| `ap.ecommerceStorefrontLivewire.patterns` | filter | Offering the block patterns to the editor | `array $patterns` (slug => `title`, `description`, `content`) | Patterns. |

Template slugs a page looks for, most specific first: `single-product-{slug}` → `single-product-{type}` → `single-product`; `product-category-{slug}` → `product-category` → `product-archive`; `product-tag-{slug}` → `product-tag` → `product-archive`. A page renders through the first one saved in the site editor, and as usual when none is.
