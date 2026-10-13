---
title: Templates and Patterns
---

# Templates and Patterns

With templates on, the storefront's product, catalog, category, tag, cart, checkout, and search pages render through visual-editor templates, so a store owner can design them in the site editor. The storefront also offers three block patterns. Both are built from the commerce [Blocks](Visual-Editor-Blocks).

## Turning templates on

Templates are off by default, so installing visual-editor doesn't change an existing store:

```php
// config/artisanpack/ecommerce-storefront-livewire.php
'visual_editor' => [
    'blocks'    => true,
    'templates' => true,
],
```

A page renders through a template only when all of these hold:

- `visual_editor.templates` is `true`;
- the blocks are available (see [Visual Editor](Visual-Editor));
- `cms-framework`'s site editor is installed (`ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver` exists);
- the `ve-template` Blade component is registered.

Otherwise, and whenever no template in the page's chain resolves, the page renders its Livewire component as usual. If the resolver throws, the error is reported and the page falls back the same way.

## Default templates

With templates on, the storefront offers these templates to the site editor through the `ap.visualEditor.templates` filter. A template you already have with the same slug is kept; the storefront's is only a default. Each is attributed to the active theme (or `storefront` when no theme manager is available).

| Slug | Title | Content |
| --- | --- | --- |
| `single-product` | Single Product | The Single Product block |
| `product-archive` | Product Catalog | A "Shop" heading and the Product Catalog block |
| `product-category` | Product Category | The Product Catalog block |
| `product-tag` | Product Tag | The Product Catalog block |
| `cart` | Cart | A "Cart" heading and the Cart Contents block |
| `checkout` | Checkout | A "Checkout" heading and the Checkout Steps block |
| `search-results` | Product Search Results | A "Search" heading and the Product Catalog block |

Apart from `single-product`, each template wraps its blocks in a group block with the classes `flex flex-col gap-6`.

## Which template a page uses

The page tries each slug in its chain, in order, and uses the first one the site editor resolves to a template with blocks.

| Page | Chain |
| --- | --- |
| Product | `single-product-{product slug}` → `single-product-{product type}` → `single-product` |
| Category | `product-category-{category slug}` → `product-category` → `product-archive` |
| Tag | `product-tag-{tag slug}` → `product-tag` → `product-archive` |
| Catalog | `product-archive` |
| Search | `search-results` |
| Cart | `cart` |
| Checkout | `checkout` |

So, to give one product its own layout, create a `single-product-blue-mug` template; to change every variable product, create `single-product-variable`. Slugs are lowercased. A product, category, or tag slug that isn't made of letters, numbers, `-`, and `_` (starting with a letter or number) skips its entry and moves on to the next one in the chain.

Order lookup, order pages, and account pages don't use templates.

## Patterns

The storefront offers these patterns through `ap.visualEditor.patterns` whenever the blocks are available (templates on or off). They are in the `commerce` pattern category and unsynced.

| Slug | Title | Content |
| --- | --- | --- |
| `artisanpack-commerce/featured-products` | Featured products | A heading and a 4-column Product Grid of 4 featured products |
| `artisanpack-commerce/shop-by-category` | Shop by category | A heading and a 3-column Category Grid of 6 top-level categories |
| `artisanpack-commerce/sale-banner-grid` | Sale banner + grid | A banner ("On sale now") above a 4-column Product Grid of 8 products on sale |

A pattern you already have with the same slug is kept.

## Changing the defaults

Two filters change what the storefront offers. Each receives an array keyed by slug, with `title`, `description`, and `content` (block markup). Entries with an invalid slug (lowercase letters, numbers, `_`, `-`, and `/`), or without a title or content, are dropped.

| Filter | Changes |
| --- | --- |
| `ap.ecommerceStorefrontLivewire.templates` | The default templates (only used with `visual_editor.templates` on) |
| `ap.ecommerceStorefrontLivewire.patterns` | The patterns |

`StorefrontTemplates::block( $slug, $attrs )` builds a commerce block's markup for you:

```php
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontTemplates;

addFilter( 'ap.ecommerceStorefrontLivewire.patterns', function ( array $patterns ): array {
    $patterns['artisanpack-commerce/new-arrivals'] = [
        'title'       => __( 'New arrivals' ),
        'description' => __( 'The eight newest products.' ),
        'content'     => StorefrontTemplates::block( 'product-grid', [ 'source' => 'newest', 'limit' => 8 ] ),
    ];

    unset( $patterns['artisanpack-commerce/sale-banner-grid'] );

    return $patterns;
} );
```

See [Hooks Reference](Extending-Hooks-Reference).
