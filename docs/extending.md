---
title: Extending Overview
---

# Extending

Satellites and host applications change the storefront through registries, filters, and actions, and can reuse its Livewire and Blade components on their own pages. Nothing here requires publishing views.

## In this section

- [Embedding Components](Extending-Embedding-Components) - Use each storefront screen as a Livewire component on your own routes
- [Blade Components](Extending-Blade-Components) - The `x-artisanpack-ec-*` components to reuse in your views
- [Hooks Reference](Extending-Hooks-Reference) - Every registry, filter, and action the storefront uses

The commerce blocks have their own section: [Visual Editor](Visual-Editor).

## Registries, filters, and actions

**Registries** are singletons. Resolve one from the container and register in your service provider's `boot()`:

```php
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry;

public function boot(): void
{
    app( ProductFormRegistry::class )->register( 'subscription', 'subscriptions-purchase-form' );
}
```

| Registry | Adds |
| --- | --- |
| `ProductFormRegistry` | The purchase form (a Livewire component) for a product type |
| `PaymentDriverRegistry` | The payment UI for a gateway's client-render `driver`. See [Payment Drivers](Checkout-Payment-Drivers). |
| `AccountMenuRegistry` (engine) | An entry in the account navigation |

**Filters** change a value the storefront is about to use: the catalog filters and sorts, the product card, the product page sections, header actions, SEO tags, the commerce blocks, and more. **Actions** let you run code at a point in a flow; the storefront fires `ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder` and the engine's `ap.ecommerce.customer.orderClaimed`. Both use `artisanpack-ui/hooks`:

```php
use ArtisanPackUI\Ecommerce\Models\Product;

addFilter( 'ap.ecommerceStorefrontLivewire.catalog.productCard', function ( array $card, Product $product ): array {
    $card['badges'][] = __( 'New' );

    return $card;
}, 10, 2 );
```

A filter receives the value first and must return it. Pass the number of arguments you need (`10, 2` above) when the filter has more than one. A filter that returns the wrong type is ignored and the storefront's own value is used. [Hooks Reference](Extending-Hooks-Reference) lists every one.

## Rules every extension follows

- **Register your own Livewire components.** Purchase forms and payment drivers are Livewire components you register with `Livewire::component()` in your package.
- **Translate labels per request.** Build labels with `__()` inside the filter callback, so they follow the request's locale.
- **A later registration with the same key replaces an earlier one.** The storefront registers its core product forms and payment drivers only for keys nobody registered first, so a satellite's registration wins.
