---
title: SEO
---

# SEO

Every storefront page describes itself for search engines: a title, a description, a canonical URL, robots rules, Open Graph tags, and structured data (JSON-LD). The page controller fills a request-scoped `ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontSeo`, and each page pushes `ecommerce-storefront::partials.seo` onto the layout's `head` stack.

If you use your own layout, add `@stack( 'head' )` inside its `<head>`, or the tags are not printed. See [Layouts](Installation-Layouts).

## Titles and descriptions

| Page | Title | Description |
| --- | --- | --- |
| Product | The product name | The short description, else the description, as plain text |
| Category | The category name | The category description, else "Shop {name}." |
| Tag | The tag name | "Shop products tagged {name}." |
| Catalog | "Shop" | "Browse every product at {app name}." |
| Search | "Search results for "{term}"", or "Search" | none |

From page 2 on, catalog, category, and tag titles add "– Page N". Descriptions are cut to 160 characters. The standalone layout prints the title as `{title} · {app name}`.

## Canonical URLs

| Page | Canonical |
| --- | --- |
| Product | The product URL |
| Category | The category URL, keeping `page` when it is above 1 |
| Tag | The tag URL, keeping `page` when it is above 1 |
| Catalog | The catalog URL, keeping `category` and `page` (above 1) |

Every other query parameter (sort, page size, price, attribute, and other filters) is left out. A page whose route doesn't exist (the storefront routes are off) gets no canonical URL.

## Pages that are not indexed

| Page | Robots | Also |
| --- | --- | --- |
| Search | `noindex, follow` | `X-Robots-Tag: noindex, follow` header |
| Cart, checkout, order confirmation, order lookup, guest order page, and every account page | `noindex, nofollow` | `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store, private`, and `Referrer-Policy: same-origin` headers |

`Referrer-Policy` keeps the URL of a guest's order page, which carries a signed token, from reaching other sites.

## Open Graph

Pages that can be indexed and have a title also get `og:title`, `og:type` (`product` on product pages, otherwise `website`), `og:site_name`, `og:description`, and `og:url` (the canonical URL). Product pages with an image add `og:image` and `twitter:card` (`summary_large_image`).

## Structured data

| Page | JSON-LD |
| --- | --- |
| Product | `Product` (name, description, SKU, image, URL), with an `Offer` (price, currency, availability) or, for a price range, an `AggregateOffer` (`lowPrice`, `highPrice`), and an `AggregateRating` when the product has reviews |
| Product | `BreadcrumbList`: Shop, the product's first category and its ancestors, then the product |
| Category | `BreadcrumbList`: Shop, then the category and its ancestors |

Availability maps the engine's stock status to `InStock` (in stock or low stock), `BackOrder`, or `OutOfStock`. Prices are in the shopper's currency.

## With `artisanpack-ui/seo`

When `artisanpack-ui/seo` is installed (its `SchemaCollector` is bound), the storefront hands its JSON-LD to that package's schema collector instead of printing it, and the standalone layout prints the collected schema with `<x-seo:schema />`. Use `<x-seo:schema />` in your own layout as well. The meta tags are still printed by the storefront.

## Changing the tags

Two filters change what is printed. Both receive the page name (`product`, `category`, `tag`, `catalog`, `search`, `cart`, `checkout`, `confirmation`, `lookup`, `order-view`, or `account`) and the subject (the product, category, or tag model, or `null`).

| Filter | Value | Notes |
| --- | --- | --- |
| `ap.ecommerceStorefrontLivewire.seo.meta` | `array $meta` with `title`, `description`, `canonical`, `robots`, `image`, `type` | Keys you leave out keep their value; set one to `null` to remove it. Unsafe URLs are dropped; descriptions are cut to 160 characters. |
| `ap.ecommerceStorefrontLivewire.seo.schema` | `array $schemas` (JSON-LD entries) | Entries that aren't non-empty arrays are dropped |

```php
use Illuminate\Database\Eloquent\Model;

addFilter( 'ap.ecommerceStorefrontLivewire.seo.meta', function ( array $meta, ?string $page, ?Model $subject ): array {
    if ( 'catalog' === $page ) {
        $meta['description'] = __( 'Handmade ceramics, shipped worldwide.' );
    }

    return $meta;
} );
```

See [Hooks Reference](Extending-Hooks-Reference).

## On your own routes

If you turn the storefront routes off and embed the components (see [Embedding Components](Extending-Embedding-Components)), describe the page yourself before rendering it, and include the partial in your `<head>`:

```php
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontSeo;

app( StorefrontSeo::class )->catalog( $request );
app( StorefrontSeo::class )->noindex( 'cart' );
```

```blade
@include( 'ecommerce-storefront::partials.seo' )
```

`StorefrontSeo` also has `product( $product, $currency )`, `category( $category, $request )`, `tag( $tag, $request )`, and `search( $term )`. Canonical URLs and breadcrumbs link only to storefront routes that exist.
