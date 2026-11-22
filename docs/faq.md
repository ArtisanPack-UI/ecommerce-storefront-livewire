---
title: FAQ
menu_order: 95
---

# FAQ

## Does the storefront have its own database tables?

No. It reads and writes the engine's tables through the engine's services and ships no migrations. See [Installation](installation.md).

## Why is the storefront unstyled?

The package ships no CSS build. Load the design system (`@artisanpack-ui/css`), add the storefront's `@source` lines to your stylesheet, and rebuild. See [Front-End Setup](installation/front-end.md).

## Does it still need daisyUI?

No. Since 2.0 the storefront renders on the ArtisanPack UI design system through `livewire-ui-components` 3.0. Remove daisyUI if nothing else uses it. See [Upgrading](upgrading.md).

## Can I put the shop inside my site's header and footer?

Yes. Set `storefront.layout` to your own layout and include `ecommerce-storefront::partials.global` in it. A Blade component layout such as `<x-app-layout>` needs a one-line wrapper view. See [Layouts](installation/layouts.md).

## Who handles sign-in and registration?

Your application. The storefront links to the route names in `auth.login_route`, `auth.register_route`, `auth.logout_route`, `auth.profile_route`, and `auth.password_route`, and hides a link whose route doesn't exist. A Laravel starter kit provides them all. See [Requirements](installation/requirements.md#authentication-routes).

## Can guests check out?

Yes, by default. The engine's `checkout.guest_checkout` setting lets guests check out (`allowed`), makes them create an account during checkout (`required_account`), or makes shoppers sign in (`disabled`). Guests can find their order later with the order lookup page. See [Guest Checkout](checkout/guest-checkout.md).

## Can I change the URLs?

Set `storefront.route_prefix` (`shop`) and `account.route_prefix` (`account`). Route names stay `artisanpack.ecommerce.storefront.*` and `artisanpack.ecommerce.account.*`. See [Configuration](installation/configuration.md).

## Can I use the storefront's screens on my own routes?

Yes. Every screen is a Livewire component. Set `storefront.routes_enabled` to `false` and mount the components on your own pages. See [Embedding Components](extending/embedding-components.md).

## Which payment gateways does it support?

The engine ships Stripe, which the storefront renders with the Stripe Payment Element. A gateway package that implements the engine's `RendersClientPayment` contract plugs in without storefront changes, and you can register your own payment driver. See [Payment Drivers](checkout/payment-drivers.md).

## Does it work with Livewire 4?

Yes. It supports Livewire 3.6+ and 4, and CI runs both.

## Do I need the visual editor?

No. With `artisanpack-ui/visual-editor` installed, the storefront adds commerce blocks, and with `visual_editor.templates` on, it renders its pages through editable templates. Without it, nothing changes. See [Visual Editor](visual-editor.md).

## How do I add a wishlist button or a "Notify me" form?

Add buttons after "Add to cart" with the `ap.ecommerceStorefrontLivewire.product.addToCartActions` filter. With engine 1.4 or later, a sold-out product already shows a "Notify me" back-in-stock signup; turn it off with `ap.ecommerceStorefrontLivewire.product.backInStockEnabled`. See [Hooks](reference/hooks.md#product-page).

## Does it support dark mode?

Yes. The design system has light and dark themes, and the package layout's footer has a theme toggle. A host layout adds `@apuiThemeScript` to its `<head>` so the stored theme applies before the page paints. See [Layouts](installation/layouts.md).

## How do I try it with data?

Run `php artisan ecommerce:seed-demo` against a development database. See [Getting Started](getting-started.md#4-seed-a-demo-store-optional).

## Which languages does it ship?

English, Spanish, French, German, Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic. The last five are machine-translated and waiting for review. Right-to-left languages get a right-to-left layout. See [Localization](localization.md).

## Is it accessible?

It targets WCAG 2.2 AA, and the browser suite runs axe on every screen. Some limitations come from the component library. See [Accessibility](accessibility.md).

## Does it help with SEO?

Yes. Pages print titles, descriptions, canonical URLs, Open Graph tags, and `Product` and `BreadcrumbList` JSON-LD, and private pages are `noindex`. With `artisanpack-ui/seo` installed, its schema collector prints the structured data. See [SEO](seo.md).

## What versions are supported?

PHP 8.3+, Laravel 12 or 13, Livewire 3.6+ or 4, and engine 1.1 or later. Rich product options and "Notify me" need engine 1.4, and the storefront detects them. See [Requirements](installation/requirements.md).
