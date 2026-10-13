---
title: Performance
---

# Performance

The storefront is built to stay fast with thousands of products and deep category trees.

## Queries

Listings (catalog, category, tag, search, product grids, related products, recently viewed, the cart and cart drawer) load what their cards need in a fixed number of queries, however many products a page shows: the gallery and the engine's display data (`Product::displayRelations()`: prices, stock rows, variants with theirs). The engine prices and stocks a product from those loaded relations, and reads tax rates once per request. A variable product page runs the same queries for 2 variants or 20.

Card images from `artisanpack-ui/media-library` are looked up in one query per page too: listings preload them before rendering the cards. Without the media library, preloading does nothing.

When you build your own listing, eager-load the same relations and preload the images, with `ProductImages::preloadCards()` for products or `ProductImages::preload()` for media ids:

```php
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\ProductCard;

$products = Product::query()->storefrontVisible()->with( ProductCard::relations() )->paginate( 24 );

ProductImages::preloadCards( $products );

// Category tiles, or anything else with a media id:
ProductImages::preload( $categories->pluck( 'image_media_id' ) );
```

The cart drawer lists its lines only once it has opened for the first time; until then it shows a loading state (`data-cart-loading`), so pages don't pay for the cart's lines and images on every load. The header count stays up to date regardless.

## Caching

Catalog filter counts, category grid counts, attribute labels and swatches, and search facet counts are cached for `performance.cache_ttl` seconds (default 600), tagged `ecommerce-storefront` on stores that support tags. (The category tree itself is cached by the engine's `CategoryTree`; the storefront keeps no copy of its own.) On stores with locks, one request recomputes a missing entry while the others wait for it, so a flush doesn't send every shopper to the database at once.

The cache is cleared when:

- a product is saved, deleted, published, or unpublished;
- a category, tag, price, attribute, attribute value, or variant is saved or deleted;
- a product runs out of stock or comes back into stock.

An ordinary sale doesn't clear it: only stock going to or from none does.

The engine fires no hook when a product's categories or tags are re-synced (`ProductService::setCategories()` and `setTags()`), so counts can lag behind those changes for up to `performance.cache_ttl` seconds until the engine adds one.

Category grid counts are distinct products: a product filed in a category and in one of its sub-categories counts once.

Set `performance.cache_ttl` to `0` to turn the cache off — do this if an `ap.ecommerce.product.listQuery` filter shows different shoppers different products (per customer group, for example), since the counts are shared.

## Search

Each search on the search page counts against the engine's `ecommerce.catalog.read` rate limiter, as header search suggestions do. Over the limit, the page shows a "Slow down" message instead of results until the shopper can search again.

## Malformed URLs

A catalog, category, tag, search, or product URL with a malformed query string (`?per_page=abc`, `?sort[]=x`, `?variant=abc`) renders the page with the bad values dropped, on Livewire 3 and 4, instead of failing with a server error.

## Lazy sections

Related products, upsells, cross-sells, and recently viewed load after the page with skeleton cards in their place. Reviews load with the page so links to `#reviews` land on them.

## Images

With `artisanpack-ui/media-library` installed, images carry a `srcset` built from its generic `medium` and `large` image sizes and a `sizes` hint for where they are shown (cards, gallery, category tiles, cart and search thumbnails). The lightbox and zoom use the `full` size. The storefront registers no image sizes of its own; change the widths with the media library's `image_sizes` config. Every image has `width` and `height`, from the media item when it is known and from the box it fills otherwise, so the page doesn't shift as images load.

## Livewire payload size

Livewire sends each component's public properties to the browser and back on every update, and reloads every relation loaded on a public model.

- Keep public properties small: ids, scalars, and the one model the component is about (`Product $product`, `Order $order`). Don't put collections of models, or models with large relations loaded, in public properties.
- Load display relations on a copy, not on the public model. The product page prices from `Support\DisplayData::for( $this->product )`, so Livewire doesn't reload prices and stock rows on every update.
- Compute lists in `render()` and pass them to the view, as the catalog and cart do; they are never serialized.
- Mark props that shoppers mustn't change with `#[Locked]`.
