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
} );
```

A filter receives the value first and must return it. Every extra argument is passed to the callback, so declare only the ones you need. A filter that returns the wrong type is ignored and the storefront's own value is used. [Hooks Reference](Extending-Hooks-Reference) lists every one.

## Custom purchase forms

A purchase form registered with `ProductFormRegistry` should extend `Livewire\Product\Forms\PurchaseForm`. A form that overrides `addToCart()` calls `$this->startAdding()` first: it clears the last announcement and keys a new error summary, so a refused add is announced even when the same error repeats.

```php
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\PurchaseForm;

class SubscriptionForm extends PurchaseForm
{
    public function addToCart(): void
    {
        $this->startAdding();
        $this->validateQuantity();

        // ... add the lines
    }
}
```

## Error summaries in your own forms

The storefront's forms list every problem in an error summary that is announced and focused after each failed submit. To do the same in your own Livewire form, use the `Livewire\Concerns\SummarizesErrors` concern, call `$this->newErrorSummary()` at the start of each submit, and include the partial inside the form:

```blade
@include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => 'my-form' ] )
```

`summaryId` must be unique on the page. `summaryExcept` (error keys shown elsewhere) and `summaryClass` (extra classes) are optional. Each entry links to its field, found by an `<x-artisanpack-ec-field>` (`data-field-key`) or a `data-field` wrapper.

## Rules every extension follows

- **Register your own Livewire components.** Purchase forms and payment drivers are Livewire components you register with `Livewire::component()` in your package.
- **Translate labels per request.** Build labels with `__()` inside the filter callback, so they follow the request's locale.
- **A later registration with the same key replaces an earlier one.** The storefront registers its core product forms and payment drivers only for keys nobody registered first, so a satellite's registration wins.
