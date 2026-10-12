---
title: Layouts
---

# Layouts

Every storefront and account page is a controller action that returns a page view embedding one Livewire component. Each page view starts with `@extends( $ecommerceStorefrontLayout )`. A view composer on `ecommerce-storefront::pages.*` sets `$ecommerceStorefrontLayout` to the `storefront.layout` config value, or to `ecommerce-storefront::layouts.app` when that value is empty.

So there are three ways to run the shop:

| Setup | `storefront.layout` | Use it when |
| --- | --- | --- |
| Standalone | `ecommerce-storefront::layouts.app` (default) | You want a working shop with no layout work |
| Your Blade layout | Your layout view, for example `layouts.site` | Your site has a classic `@yield` layout |
| Your component layout | A one-line wrapper view you create | Your site uses `<x-app-layout>` or `<x-layouts.app>` |

## The layout contract

Any layout the pages extend must provide:

- `@yield( 'title' )`: each page sets a plain-text `title` section.
- `@yield( 'content' )`: the page body.
- `@stack( 'head' )` inside `<head>`: every page pushes its description, canonical URL, robots directive, Open Graph tags, and JSON-LD here (`ecommerce-storefront::partials.seo`). Without it, pages lose their search-engine tags.
- `@stack( 'styles' )` and `@stack( 'scripts' )`.
- `@include( 'ecommerce-storefront::partials.global' )`: the cart drawer, toasts, and the cart merge prompt. See [The global partial](#the-global-partial).
- Livewire's assets and your Tailwind build, as on any Livewire page.

When `artisanpack-ui/seo` is installed, the storefront hands its JSON-LD to the SEO package instead of printing it, so your layout also needs `<x-seo:schema />` in `<head>` (the package layout includes it). See [SEO](Seo).

## 1. Standalone layout

`ecommerce-storefront::layouts.app` is used unless you configure another. It is built from `x-artisanpack-nav` and `x-artisanpack-main` and includes:

- a "Skip to content" link;
- a sticky header with the store name (`app.name`) linking to the catalog, a "Shop" link, the search box, the header actions, and the theme toggle;
- a footer with the copyright line and a "Find an order" link to the order lookup;
- your Vite entries (see [Front-End Setup](Installation-Front-End#which-entries-the-storefront-loads)), `@livewireStyles`, `@livewireScripts`, the `head`, `styles`, and `scripts` stacks, and the global partial.

Links to routes that aren't registered (catalog, search, lookup) are left out. The page title is the page's `title` section followed by `· {app.name}`.

### Header actions

The header's action buttons run through the `ap.ecommerceStorefrontLivewire.header.actions` filter. Each entry is `key => view name` or an `Htmlable`. The defaults:

| Key | View | Shows |
| --- | --- | --- |
| `currency` | `ecommerce-storefront::partials.header.currency` | The currency switcher, only when the store sells in more than one currency |
| `account` | `ecommerce-storefront::partials.header.account` | "Account" for signed-in shoppers, else "Sign in" to `auth.login_route` |
| `cart` | `ecommerce-storefront::partials.header.cart` | The cart button and count; opens the cart drawer |

Add, replace, or remove entries from a service provider's `boot()`:

```php
use Illuminate\Support\HtmlString;

addFilter( 'ap.ecommerceStorefrontLivewire.header.actions', function ( array $actions ): array {
    $actions['wishlist'] = 'wishlist::header-link';
    unset( $actions['currency'] );

    return $actions;
} );
```

A view name that doesn't exist is skipped. The filter only affects the package layout. See [Hooks Reference](Extending-Hooks-Reference).

To change the markup itself, publish the views and edit `resources/views/vendor/ecommerce-storefront/layouts/app.blade.php`, keeping the contract above.

## 2. Your own Blade layout

Point `storefront.layout` at your layout:

```php
'storefront' => [
    'layout' => 'layouts.site',
],
```

and make it meet the contract. A minimal example:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield( 'title' ) &middot; {{ config( 'app.name' ) }}</title>
    @vite( [ 'resources/css/app.css', 'resources/js/app.js' ] )
    @livewireStyles
    @stack( 'head' )
    @stack( 'styles' )
</head>
<body>
    @include( 'partials.site-header' )

    <main id="content">
        @yield( 'content' )
    </main>

    @include( 'partials.site-footer' )

    @include( 'ecommerce-storefront::partials.global' )

    @livewireScripts
    @stack( 'scripts' )
</body>
</html>
```

The install command prints the same instructions:

```text
Storefront pages use the package layout. To use your own, set storefront.layout in the config to a layout that yields "title" and "content" and includes:
    @include( 'ecommerce-storefront::partials.global' )
Add @stack( 'head' ) inside its <head> for page descriptions, canonical URLs, and structured data.
```

Your layout replaces the package header, so it has no search box or cart button unless you add them. The header pieces are plain views and components you can include in your own header:

```blade
<x-artisanpack-ec-search-box />
@include( 'ecommerce-storefront::partials.header.currency' )
@include( 'ecommerce-storefront::partials.header.account' )
@include( 'ecommerce-storefront::partials.header.cart' )
```

If a page shows more than one search box, give each its own `input-id` (the default is `ecommerce-header-search`). See [Blade Components](Extending-Blade-Components).

## 3. Component layouts

Page views use `@extends`, which can't extend a Blade component such as `<x-app-layout>` or `<x-layouts.app>`. Create a one-line wrapper view that does, and point `storefront.layout` at it. The install command prints the wrapper for a `<x-layouts.app>` component:

```blade
<x-layouts.app :title="$__env->yieldContent( 'title' )">@yield( 'content' ) @include( 'ecommerce-storefront::partials.global' )</x-layouts.app>
```

For example, with a starter kit's `resources/views/components/layouts/app.blade.php`, save this as `resources/views/layouts/shop.blade.php`:

```blade
<x-layouts.app :title="$__env->yieldContent( 'title' )">
    @yield( 'content' )

    @include( 'ecommerce-storefront::partials.global' )
</x-layouts.app>
```

and set:

```php
'storefront' => [
    'layout' => 'layouts.shop',
],
```

For a Breeze-style `<x-app-layout>`, use that tag instead and pass the title however your component accepts it.

The wrapper passes the page title to the component as its `title` prop and puts the page body and the global partial in its slot. Your component still has to meet the rest of the contract: `@stack( 'head' )` in its `<head>`, the `styles` and `scripts` stacks, and Livewire's assets. Pages push onto the stacks before your component renders, so the stacks work inside a component layout.

## The global partial

`ecommerce-storefront::partials.global` renders the storefront's page-wide pieces:

- the toast container, plus any toast flashed for this page;
- the slide-out cart drawer (`cart.after_add` set to `drawer` opens it after "Add to cart");
- the cart merge prompt, shown after sign-in when the cart built as a guest and the account's cart are in different currencies (same-currency carts merge on their own).

Include it once, after the content. It is wrapped in `@once`, so it renders once per response even if both your layout and a page include it. Without it, "Add to cart" can't open the drawer, the header cart button has nothing to open, and toasts don't show.

## Visual-editor templates

With `artisanpack-ui/visual-editor` and `artisanpack-ui/cms-framework` installed and `visual_editor.templates` on, the catalog, category, tag, product, search, cart, and checkout pages render through a saved visual-editor template when one exists. The template replaces only the page's `content` section: the page still extends your configured layout, so the header, footer, global partial, and `head` tags stay the same. With no saved template, pages render as usual. See [Templates](Visual-Editor-Templates).
