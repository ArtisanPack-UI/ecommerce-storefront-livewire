---
title: Blade Components
---

# Blade Components

The storefront composes its screens from a few store-specific Blade components built on `livewire-ui-components`. They are registered with the `artisanpack-ec-` prefix, so your own pages, panels, and satellite views can match the storefront's look and behaviour.

`money`, `address`, `address-form`, and `empty-state` share their names and base props with the admin's components, so either copy works when both packages are installed.

## Display

| Component | Attributes | Renders |
| --- | --- | --- |
| `<x-artisanpack-ec-money>` | `amount` (int, minor units), `currency` (defaults to the engine's base currency), `locale` | An amount formatted with the engine's `MoneyFormatter` |
| `<x-artisanpack-ec-price>` | `price` (the engine's `DisplayPrice`), `show-tax` (default `true`), `sale-badge` (default `true`), `locale` | A price, a sale (struck compare-at price and a "Sale" badge), or a variable product's range, with the tax note ("incl. VAT"). Screen readers hear one sentence, such as "Sale price: was $25.00, now $19.00". A null price renders "Price unavailable". |
| `<x-artisanpack-ec-stock-status>` | `status` (the engine's `StockStatus`) or `product` (a product or variant), `show-count` (defaults to `catalog.show_stock_count`) | An icon and text: "In stock", "Low stock" (or "Only 2 left"), "Available on backorder", or "Out of stock". The text carries the meaning; colour only tints the icon. |
| `<x-artisanpack-ec-rating-summary>` | `rating` (0 to 5), `count`, `href`, `hide-empty` | Stars and the review count, linked to `href` when given. Screen readers hear "Rated 4.5 out of 5". With no reviews it says so, or renders nothing with `hide-empty`. |
| `<x-artisanpack-ec-address>` | `address`, `show-phone` (default `true`) | A postal address in an `<address>` element. Takes an order address array, the engine's `Address` value object, or a `CustomerAddress`. |
| `<x-artisanpack-ec-empty-state>` | `title`, `description`, `icon` (default `o-inbox`); the slot holds actions | An empty state. It is a `status` region, so a screen reader announces it when filtering empties a list. |
| `<x-artisanpack-ec-skeleton>` | `variant` (`text`, `card`, or `image`), `count` (1 to 24, default 3), `label` (default "Loading…") | A loading placeholder, `aria-busy` with a status for screen readers |

```blade
<x-artisanpack-ec-price :price="app( \ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver::class )->for( $product, 'USD' )" />

<x-artisanpack-ec-money :amount="$order->total_amount" :currency="$order->currency" />

<x-artisanpack-ec-stock-status :product="$product" />

<x-artisanpack-ec-rating-summary :rating="$product->avg_rating" :count="$product->reviews_count" href="#reviews" />

<x-artisanpack-ec-address :address="$order->shipping_address" :show-phone="false" />

<x-artisanpack-ec-empty-state icon="o-heart" :title="__( 'Your wishlist is empty' )" :description="__( 'Save products to find them later.' )">
    <x-artisanpack-button :label="__( 'Browse products' )" :link="route( 'artisanpack.ecommerce.storefront.catalog' )" />
</x-artisanpack-ec-empty-state>

<x-artisanpack-ec-skeleton variant="card" :count="4" />
```

## Products and categories

| Component | Attributes | Renders |
| --- | --- | --- |
| `<x-artisanpack-ec-sf-product-card>` | `product`, `currency`, `price`, `stock` (pass the last three to skip their lookups), `quick-add` (default `true`), `heading-level` (default `3`), `show-price`, `show-rating` | The card every listing uses: image, name linked to the product page, price, rating, and stock. For a simple product that can be bought, an "Add to cart" button. |
| `<x-artisanpack-ec-gallery>` | `images` (from `ProductImages::gallery( $product )`), `name`, `active` (index), `scope`, `thumbnails` (`bottom`, `start`, `end`, `none`), `zoom` (default `true`) | The product gallery: main image, thumbnails, zoom, and a lightbox (a native `<dialog>`) with previous and next |
| `<x-artisanpack-ec-category-grid>` | `parent` (id or slug; null for the top level), `limit` (1 to 24, default 6), `columns` (1 to 6, default 3), `show-counts`, `show-images`, `heading` | Category tiles with images and product counts (sub-categories included) |

```blade
@php( $products = \ArtisanPackUI\Ecommerce\Models\Product::query()->storefrontVisible()->with( \ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\ProductCard::relations() )->limit( 4 )->get() )

<ul role="list" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    @foreach ( $products as $product )
        <li wire:key="card-{{ $product->id }}">
            <x-artisanpack-ec-sf-product-card :product="$product" :quick-add="false" :heading-level="2" />
        </li>
    @endforeach
</ul>

<x-artisanpack-ec-gallery :images="\ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::gallery( $product )" :name="$product->name" thumbnails="start" />

<x-artisanpack-ec-category-grid parent="clothing" :limit="6" :columns="3" :heading="__( 'Shop clothing' )" />
```

The card's "Add to cart" button calls the parent Livewire component's `quickAdd()` (the `ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\AddsToCart` trait). Outside such a component, set `:quick-add="false"`. Eager-load `ProductCard::relations()` when rendering many cards (see [Performance](Advanced-Performance)). The card's data runs through the `ap.ecommerceStorefrontLivewire.catalog.productCard` filter; see [Hooks Reference](Extending-Hooks-Reference).

The gallery switches images when it receives the browser event `ecommerce-gallery-show` with an image `url` and, when the gallery has one, its `scope`.

## Input

| Component | Attributes | Use |
| --- | --- | --- |
| `<x-artisanpack-ec-quantity>` | `id` (required), `label` (visually hidden "Quantity" when omitted), `min` (default 1), `max`, `error`, `item-name`, plus `wire:model` and `disabled` | A number input between "Decrease" and "Increase" buttons. The buttons fire `input`, so `wire:model` (deferred or live) picks the change up. `item-name` names the buttons ("Increase quantity of Blue mug"). An error is linked with `aria-describedby`. |
| `<x-artisanpack-ec-swatches>` | `name`, `legend`, `options`, `multiple`, `id-prefix`, plus `wire:model` | A fieldset with one native radio (or checkbox with `multiple`) per option, drawn as swatches or pills. Each option is `value`, `label`, and optionally `swatch` (a hex colour or an http(s) image URL), `count`, `selected`, `disabled`, and `reason` (why it is disabled). |
| `<x-artisanpack-ec-rating-input>` | `id`, `label`, `error`, `required`, plus `wire:model` | A 1 to 5 star picker made of labelled radios ("4 stars") |
| `<x-artisanpack-ec-price-range>` | `min`, `max`, `currency`, `from-model`, `to-model`, `step` (default 100), `label`, `id-prefix` | Two labelled range inputs bound to the parent component's properties in minor units. Values are sent when a handle is released; a handle at its end sends `null`. |
| `<x-artisanpack-ec-address-form>` | `model`, `legend`, `live`, `with-name` (default `true`), `country`, `section`, `require-name` | Address fields bound to `{model}.{field}`, with `autocomplete` tokens (prefixed with `section` when two forms share a page). Countries with a region list get a region select bound to `region_code`; the postcode is labelled and hinted for the country. Errors show under each field. |
| `<x-artisanpack-ec-search-box>` | `input-id` (default `ecommerce-header-search`) | The header search box with live suggestions. Give each box on a page its own `input-id`. |

```blade
<x-artisanpack-ec-quantity id="qty" wire:model="quantity" :max="10" :error="$errors->first( 'quantity' )" />

<x-artisanpack-ec-swatches
    name="colour"
    :legend="__( 'Colour' )"
    :options="[
        [ 'value' => 'red', 'label' => __( 'Red' ), 'swatch' => '#dc2626' ],
        [ 'value' => 'blue', 'label' => __( 'Blue' ), 'swatch' => '#2563eb', 'disabled' => true, 'reason' => __( 'Out of stock' ) ],
    ]"
    wire:model.live="colour"
/>

<x-artisanpack-ec-rating-input id="rating" :label="__( 'Your rating' )" wire:model="rating" :error="$errors->first( 'rating' )" required />

<x-artisanpack-ec-price-range :min="0" :max="12000" currency="EUR" from-model="priceMin" to-model="priceMax" />

<x-artisanpack-ec-address-form model="shipping" section="shipping" :country="$shipping['country_code'] ?? null" :legend="__( 'Shipping address' )" />

<x-artisanpack-ec-search-box input-id="footer-search" class="w-full" />
```

## Account pages

`<x-artisanpack-ec-account-shell :title="…">` is the frame every account page sits in: the page heading, and the account navigation beside the slot. The navigation lists the storefront's account pages that have a route, then the entries satellites register with the engine's `AccountMenuRegistry`, sorted by position. The current entry is marked `aria-current="page"`.

```blade
<x-artisanpack-ec-account-shell :title="__( 'Wishlist' )">
    <livewire:wishlist-index />
</x-artisanpack-ec-account-shell>
```

## Stand-ins for the component library

`quantity`, `swatches`, `rating-input`, `price-range`, `empty-state`, and `skeleton` stand in for components `livewire-ui-components` does not ship yet (or, for `rating-input`, ships broken). Each is one file, so it can be swapped when the library's version is ready.
