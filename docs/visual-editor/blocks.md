---
title: Blocks
---

# Blocks

The storefront registers twelve blocks with visual-editor, named `artisanpack-commerce/{slug}`. Each block renders the same Livewire component or Blade component the storefront pages use, so a block and a page look and behave the same. See [Visual Editor](Visual-Editor) for when they are registered.

## How every block behaves

- **Attributes are clamped.** Before rendering, each block checks its attributes against its schema: booleans, integers kept within the control's `min` and `max`, enums (an unknown value falls back to the default), and strings trimmed, stripped of control characters, and cut to 200 characters. Unknown attributes are dropped.
- **Output is escaped.** Blocks render Blade views, which escape every attribute.
- **Previews need a signed-in user.** The block's `authorize` callback allows any signed-in user to preview it; the products shown are always storefront-visible ones.
- **A block never breaks the page.** If the engine throws while rendering, the error is reported and the block renders nothing on the site, or a notice ("This block couldn't be shown. Check the logs for the error.") in an editor preview.
- **Nothing to show.** When a block has nothing to render (no product, no categories), it renders nothing on the site and a notice explaining why in an editor preview.

Every rendered block sits in a `<div class="ec-commerce-block" data-commerce-block="{slug}">` wrapper.

## How product blocks find their product

Single Product, Product Gallery, Price, Add-to-Cart Button, Reviews, and Related Products show one product. They pick it in this order:

1. the `productId` attribute, when it names a storefront-visible product whose type is still installed;
2. the page's product, from the request's `StorefrontContext` (the storefront's product page sets it before rendering a template);
3. in an editor preview only, the product being edited, when the preview request names the `products` resource and its id;
4. in an editor preview only, the newest storefront-visible product, shown with a "Sample product" label that explains a product page shows its own product.

On the site, a product block with no product renders nothing. Leave `productId` at `0` to follow the page.

`StorefrontContext` is a request-scoped object (`ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontContext`). The storefront's page controller sets the page name and, where there is one, the product, category, or tag before rendering. The guest order page sets the order. If you render blocks on your own routes, set it yourself:

```php
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontContext;

app( StorefrontContext::class )->setPage( 'product' )->setProduct( $product );
```

## Catalog blocks

### Product Grid (`artisanpack-commerce/product-grid`)

A grid of product cards. Renders `artisanpack-ecommerce-storefront-product-grid`.

| Attribute | Type | Default | Notes |
| --- | --- | --- | --- |
| `source` | string | `newest` | `newest`, `featured`, `on_sale`, `category`, `tag`, or `hand_picked` |
| `category` | string | `''` | Category slug, for `category`. Sub-categories are included. |
| `tag` | string | `''` | Tag slug, for `tag` |
| `ids` | string | `''` | For `hand_picked`: product IDs separated by commas, in the order to show them. Anything that isn't a number is ignored. |
| `sort` | string | `newest` | `newest`, `price`, `-price`, `popularity`, `rating`, or `name` |
| `limit` | number | `8` | 1 to 24 |
| `columns` | number | `4` | 1 to 6 |
| `showPrice` | boolean | `true` | |
| `showRating` | boolean | `true` | |
| `showAddToCart` | boolean | `true` | |
| `heading` | string | `''` | Empty shows no heading |

### Category Grid (`artisanpack-commerce/category-grid`)

Category tiles with images and product counts. Renders `<x-artisanpack-ec-category-grid>`.

| Attribute | Type | Default | Notes |
| --- | --- | --- | --- |
| `parent` | string | `''` | Parent category slug; shows its sub-categories. Empty shows the top-level categories. |
| `limit` | number | `6` | 1 to 24 |
| `columns` | number | `3` | 1 to 6 |
| `showCounts` | boolean | `true` | |
| `showImages` | boolean | `true` | |
| `heading` | string | `''` | |

With no categories to show, the editor preview says to check the parent category slug.

### Related Products (`artisanpack-commerce/related-products`)

Related products, upsells, or cross-sells. Renders `artisanpack-ecommerce-storefront-related-products`.

| Attribute | Type | Default | Notes |
| --- | --- | --- | --- |
| `type` | string | `related` | `related`, `upsell`, or `cross_sell` |
| `limit` | number | `4` | 1 to 12 |
| `columns` | number | `4` | 1 to 6 |
| `productId` | number | `0` | See above |
| `heading` | string | `''` | |

With no product, `related` and `upsell` render nothing. `cross_sell` without a product shows cross-sells for the shopper's cart instead. On the site the section loads after the page (Livewire lazy loading); in an editor preview it renders straight away.

### Recently Viewed (`artisanpack-commerce/recently-viewed`)

The products the shopper looked at last. Renders `artisanpack-ecommerce-storefront-recently-viewed`, excluding the page's product.

| Attribute | Type | Default | Notes |
| --- | --- | --- | --- |
| `limit` | number | `4` | 1 to 12 |
| `columns` | number | `4` | 1 to 6 |
| `heading` | string | `''` | |

This block is registered only when the `artisanpack-ui/ecommerce-recently-viewed` satellite is registered and active with the engine. The storefront keeps no history itself; the satellite supplies the product ids through the `ap.ecommerceStorefrontLivewire.recentlyViewed.productIds` filter. See [Hooks Reference](Extending-Hooks-Reference).

### Product Catalog (`artisanpack-commerce/product-catalog`)

The products of the page the block is on, with filters, sorting, and pages. It has no attributes. It reads `StorefrontContext`:

| Page | Renders |
| --- | --- |
| A category | `artisanpack-ecommerce-storefront-category-show` |
| A tag | `artisanpack-ecommerce-storefront-tag-show` |
| Search (`page` is `search`) | `artisanpack-ecommerce-storefront-search` |
| Anything else | `artisanpack-ecommerce-storefront-catalog` |

The default archive, category, tag, and search templates are built on it.

## Product blocks

All of these take `productId` (number, default `0`, minimum `0`) and find their product as described above. With no product to show, the editor preview says "There are no products to show yet. Add a product, or set a product ID in the block settings."

### Single Product (`artisanpack-commerce/single-product`)

The whole product: images, price, add to cart, details, and reviews. Renders `artisanpack-ecommerce-storefront-product-show`. No other attributes. A product view is recorded on the site, never in an editor preview.

### Product Gallery (`artisanpack-commerce/product-gallery`)

The product's images with thumbnails and zoom, including variant images. Renders `<x-artisanpack-ec-gallery>`.

| Attribute | Type | Default | Notes |
| --- | --- | --- | --- |
| `thumbnails` | string | `bottom` | `bottom`, `start`, `end`, or `none` |
| `zoom` | boolean | `true` | Zoom on click |

### Price (`artisanpack-commerce/product-price`)

The product's price, with any sale price, in the shopper's cart currency. Renders `<x-artisanpack-ec-price>`. No other attributes.

### Add-to-Cart Button (`artisanpack-commerce/add-to-cart`)

The product's purchase form (the one registered for its type in `ProductFormRegistry`), with its options.

| Attribute | Type | Default | Notes |
| --- | --- | --- | --- |
| `showQuantity` | boolean | `true` | |
| `buttonText` | string | `''` | Empty shows "Add to cart". Cut to 60 characters. |

When the product's type has no purchase form, the block shows "This product can't be purchased online".

### Reviews (`artisanpack-commerce/product-reviews`)

The product's ratings and reviews, and the form to write one. Renders `artisanpack-ecommerce-storefront-product-reviews`.

| Attribute | Type | Default | Notes |
| --- | --- | --- | --- |
| `perPage` | number | `5` | 1 to 50 |
| `showForm` | boolean | `true` | Show the review form |

## Cart and checkout blocks

### Cart Contents (`artisanpack-commerce/cart-contents`)

The shopper's cart: items, coupon, shipping estimate, totals, and cross-sells. Renders `artisanpack-ecommerce-storefront-cart`.

| Attribute | Type | Default |
| --- | --- | --- |
| `showCrossSells` | boolean | `true` |
| `showCoupon` | boolean | `true` |

In an editor preview it shows a static cart with the two newest products that have a price, instead of the editor's own cart. With no such product, the preview says to add one.

### Checkout Steps (`artisanpack-commerce/checkout-steps`)

The checkout. Renders `artisanpack-ecommerce-storefront-checkout`.

| Attribute | Type | Default | Notes |
| --- | --- | --- | --- |
| `layout` | string | `store` | `store` (the store's checkout layout setting), `multi_step`, or `single_page` |

In an editor preview it shows the checkout steps in the chosen layout, without a cart. See [Checkout Layouts](Checkout-Layouts).

## Removing or adding blocks

The block list runs through the `ap.ecommerceStorefrontLivewire.blocks` filter: a list of `StorefrontBlock` class names. Entries that aren't subclasses of `ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlock` are ignored.

```php
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\CheckoutStepsBlock;

addFilter( 'ap.ecommerceStorefrontLivewire.blocks', function ( array $blocks ): array {
    $blocks   = array_values( array_diff( $blocks, [ CheckoutStepsBlock::class ] ) );
    $blocks[] = \App\Blocks\GiftCardBalanceBlock::class;

    return $blocks;
} );
```

Register the filter in a service provider's `boot()`. Blocks are resolved after every provider has booted, so the order of providers doesn't matter.

A block of your own extends `StorefrontBlock` (or `ProductBlock` for one that shows a product) and implements:

| Method | Returns |
| --- | --- |
| `slug()` | The name after `artisanpack-commerce/` |
| `title()`, `description()` | Inserter text (translate them) |
| `attributes()` | The block.json `attributes`, with `apControl` inspector hints (`control`, `label`, `help`, `min`, `max`, `options`) |
| `html( array $attrs )` | The block's HTML for clamped attributes, or `null` when there is nothing to show |

Override `available()` to register the block only when a dependency is present, `icon()` for a dashicon name, `keywords()` for extra inserter keywords, and `emptyMessage()` for the preview notice. Product blocks implement `productAttributes()` and `productHtml( Product $product, array $attrs )` instead of `attributes()` and `html()`.
