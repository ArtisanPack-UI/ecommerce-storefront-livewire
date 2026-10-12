---
title: Performance
---

# Performance

The storefront is built to stay fast with thousands of products and deep category trees (spec §12).

## Queries

Listings (catalog, category, tag, search, product grids, related products, recently viewed, the cart and cart drawer) load what their cards need in a fixed number of queries, however many products a page shows: the gallery and the engine's display data (`Product::displayRelations()`: prices, stock rows, variants with theirs). The engine prices and stocks a product from those loaded relations, and reads tax rates once per request. A variable product page runs the same queries for 2 variants or 20.

When you build your own listing, eager-load the same relations:

```php
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\ProductCard;

$products = Product::query()->storefrontVisible()->with( ProductCard::relations() )->paginate( 24 );
```

## Caching

The walked category tree and catalog filter counts are cached for `performance.cache_ttl` seconds (default 600), tagged `ecommerce-storefront` on stores that support tags. They are cleared when a product is saved, deleted, published, or unpublished, when stock is adjusted or runs out, and when a category or tag is saved or deleted.

Set `performance.cache_ttl` to `0` to turn the cache off — do this if an `ap.ecommerce.product.listQuery` filter shows different shoppers different products (per customer group, for example), since the counts are shared.

## Lazy sections

Related products, upsells, cross-sells, and recently viewed load after the page with skeleton cards in their place. Reviews load with the page so links to `#reviews` land on them.

## Images

With `artisanpack-ui/media-library` installed, images carry a `srcset` built from its conversions and a `sizes` hint for where they are shown (cards, gallery, category tiles, cart and search thumbnails). Every image has `width` and `height`, from the media item when it is known and from the box it fills otherwise, so the page doesn't shift as images load.

## Livewire payload size

Livewire sends each component's public properties to the browser and back on every update, and reloads every relation loaded on a public model.

- Keep public properties small: ids, scalars, and the one model the component is about (`Product $product`, `Order $order`). Don't put collections of models, or models with large relations loaded, in public properties.
- Load display relations on a copy, not on the public model. The product page prices from `Support\DisplayData::for( $this->product )`, so Livewire doesn't reload prices and stock rows on every update.
- Compute lists in `render()` and pass them to the view, as the catalog and cart do; they are never serialized.
- Mark props that shoppers mustn't change with `#[Locked]`.
