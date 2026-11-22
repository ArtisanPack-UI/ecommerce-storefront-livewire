---
title: Upgrading
menu_order: 90
---

# Upgrading

## From 1.x to 2.0

2.0 rebuilds every screen, block, and Blade component on the ArtisanPack UI design system through `livewire-ui-components` 3.0. The storefront's routes, route names, Livewire component names, config keys, and hooks are unchanged unless listed here. Most hosts only need the front-end changes and, if they published views, a fresh copy of them.

### Update the packages

```bash
composer require artisanpack-ui/ecommerce-storefront-livewire:^2.0
```

This pulls in `artisanpack-ui/livewire-ui-components` `^3.0`. The engine requirement is still `^1.1`, but update the engine to 1.4 or later (`composer update artisanpack-ui/ecommerce`) to get rich product options, the "Notify me" back-in-stock signup, line option summaries, and the engine's hold for stranded payments, which the storefront turns on when it finds them.

### Replace the front-end build

daisyUI is gone. The design system's stylesheet and behavior engine replace it.

1. Remove daisyUI if nothing else in your app uses it:

   ```bash
   npm uninstall daisyui
   ```

2. Install the design system:

   ```bash
   npm install @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1
   ```

3. In your main stylesheet, remove the `@plugin "daisyui"` block (and any `@plugin "daisyui/theme"` blocks) and add the design system. The `@layer` line comes first:

   ```css
   @layer theme, base, apui, components, utilities;
   @import 'tailwindcss';
   @import '@artisanpack-ui/css';
   ```

   Keep the storefront's and the component library's `@source` lines.

4. In `resources/js/app.js`, register the behavior engine:

   ```js
   import apuiBehavior from '@artisanpack-ui/behavior/alpine';

   document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
   ```

5. Rebuild with `npm run build`.

See [Front-End Setup](installation/front-end.md) for the whole setup, including a Content Security Policy.

### Your own layout

If `storefront.layout` points at your own layout, add `@apuiThemeScript` inside its `<head>`, before your Vite entries, so the shopper's light or dark theme applies before the page paints. The global partial now also inlines the storefront's own stylesheet, so keep including it. See [Layouts](installation/layouts.md#the-layout-contract).

The package layout is new too: a store navigation ("Shop all" and the top-level categories), a footer with link columns, a menu drawer on phones, and a slim secure header on the checkout page. Change its links with the new `ap.ecommerceStorefrontLivewire.layout.navigation` and `ap.ecommerceStorefrontLivewire.layout.footerLinks` filters. See [Store navigation](installation/layouts.md#store-navigation).

### Published views

Every view was rebuilt on the `livewire-ui-components` 3.0 API and the design system's classes. Views published from 1.x use the 2.x components and daisyUI classes, so they render half-styled or break.

1. Check whether an [extension point](extending.md) now covers your change. 2.0 adds filters for the add-to-cart row, the product highlights, the sold-out notice, the payment method cards, and the store navigation. See [Hooks](reference/hooks.md).
2. If you still need a published view, delete your old copy, publish again, and reapply your change:

   ```bash
   php artisan vendor:publish --tag=ecommerce-storefront-views --force
   ```

The six strings the 1.x checkout step views use are still in every catalogue, so an old published step view keeps its translations until you replace it.

### Published translations

2.0 adds strings for the new screens and removes some it no longer uses. A catalogue published to `lang/vendor/ecommerce-storefront` from 1.x is missing the new strings, which then show in English. Publish again with `php artisan vendor:publish --tag=ecommerce-storefront-lang --force` and reapply your changes, or delete the published copy.

### Configuration

2.0 adds these keys. A config file published from 1.x works without them, since each has the default shown, but add them if you want to change them. See [Configuration](installation/configuration.md).

| Key | Default | What it controls |
| --- | --- | --- |
| `storefront.tagline` | `null` | The line under the store name in the package layout's footer |
| `storefront.nav_categories` | `3` | How many top-level categories the header and footer list |
| `auth.logout_route` | `'logout'` | The route the account navigation's "Sign out" button posts to |
| `catalog.description` | `null` | The line under the catalog page's title |
| `cart.free_shipping_threshold` | `null` | The amount the cart drawer's free-shipping meter counts toward |
| `product.variation_select_after` | `8` | How many values a variation attribute can have before it's drawn as a select instead of swatches |
| `search.keyboard_shortcut` | `true` | Whether "/" jumps to the header search. Set it to `false` to turn the shortcut off |

### Changes in behavior

- **Variation pickers.** A variation attribute with more than 8 values is now a select instead of swatches. Set `product.variation_select_after` to `0` to always use swatches.
- **Sold-out products.** With engine 1.4 or later, a sold-out product or variant shows a "Notify me" signup in place of the "This item is sold out" notice. Turn it off with the `ap.ecommerceStorefrontLivewire.product.backInStockEnabled` filter.
- **Claiming guest orders.** A successful claim now stays on the page with a success message and a "View orders" link, instead of redirecting with a toast.
- **Account navigation.** The account sidebar has a "Sign out" button that posts to `auth.logout_route`. It's hidden when the route doesn't exist.
- **Checkout.** Steps still to come are shown, muted, in the multi-step layout too, and the checkout page uses the slim secure header with no store navigation or footer.
- **Page titles.** The address book's heading is now "Saved addresses" and the claim page's is "Claim orders".
- **Descriptions.** Product and category descriptions drop `class` and `style` attributes, custom elements, form controls, and Alpine or Livewire attributes. Style descriptions through your theme's typography instead of classes in the content.
- **Customer records.** For a user model that implements `MustVerifyEmail`, the profile and address pages only create the engine customer record once the email is verified. Claiming guest orders also needs the record's email to match the user's current, verified email.
- **Marketing consent.** A guest checkout no longer opts in an existing customer record that hadn't opted in. The order's `meta.marketing_consent` gains `applied`, and the `ap.ecommerceStorefrontLivewire.checkout.marketingConsentPending` action fires so you can send a double opt-in email.
- **Stranded payments.** When a payment is confirmed for an earlier cart total, the checkout no longer asks the shopper to pay again. It tells them to contact you and fires `ap.ecommerceStorefrontLivewire.checkout.paymentMismatch`. Hook it to alert staff.
- **Embedded components.** The cart, checkout, search, order, and account components take an optional `show-title` property that renders the page's h1. It's off by default, as before. See [Embedding Components](extending/embedding-components.md).

### Toasts

Storefront toasts now go through the component library's 3.0 toaster, rendered by the new `<x-artisanpack-ec-toaster>` in the global partial. A satellite using the `SendsToasts` concern keeps working: each helper takes an optional third `$options` argument (the variant, an icon, and one action). If you call `ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload::make()` directly, its fourth argument is now that options array, not a CSS class string.

### Your theme

The storefront no longer uses daisyUI theme colours (`base-content`, `primary`, and so on). It draws every colour from the design system's tokens (`--apui-*`). Custom CSS that targeted daisyUI classes in the storefront's markup no longer matches anything. Restyle through the design system's tokens instead. See [Accessibility](accessibility.md#what-your-theme-needs).
