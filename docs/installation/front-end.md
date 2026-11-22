---
title: Front-End Setup
menu_order: 30
---

# Front-End Setup

The storefront renders on the ArtisanPack UI design system through `livewire-ui-components` 3.0. The package ships no CSS or JavaScript build of its own: your application's Vite build loads the design system's stylesheet and behavior engine. Until you set this up, the storefront renders unstyled and its cart drawer, filters drawer, dialogs, and toasts don't work.

The storefront's own styles (the product card, prices, the account navigation, and the other parts the design system has no component for) are inlined into the page with your CSP nonce, so they need no build step.

The browser test suite builds a host like this. Use it as a working reference:

- `tests/Browser/workbench/resources/css/app.css`
- `tests/Browser/workbench/resources/js/app.js`
- `vite.config.js`

## 1. Remove daisyUI

The storefront no longer uses daisyUI. If the storefront was the only thing using it, remove the `daisyui` package and the `@plugin "daisyui"` block from your stylesheet. Its base styles fight the design system's components.

## 2. Install the npm packages

```bash
npm install -D tailwindcss @tailwindcss/vite
npm install @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1
```

| Package | Used for |
| --- | --- |
| `@artisanpack-ui/css` | The design system's tokens and component styles |
| `@artisanpack-ui/behavior` | The behavior engine behind drawers, dialogs, accordions, toasts, and the copy-to-clipboard field |

## 3. Add the stylesheet lines

Add these to your main stylesheet, for example `resources/css/app.css`. `php artisan ecommerce-storefront:install` prints the storefront's two `@source` lines:

```css
@layer theme, base, apui, components, utilities;
@import 'tailwindcss';
@import '@artisanpack-ui/css';

@source "../../vendor/artisanpack-ui/ecommerce-storefront-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-storefront-livewire/src/**/*.php";

/* The component library's views, if your app doesn't list them already. */
@source "../../vendor/artisanpack-ui/livewire-ui-components/resources/views/**/*.php";
@source "../../vendor/artisanpack-ui/livewire-ui-components/src/**/*.php";
```

The `@layer` line puts the design system in its own layer between Tailwind's base and its utilities, so Preflight never restyles a component and utilities still win. Keep it first in the file.

The paths are relative to the CSS file. Adjust them if your entry lives elsewhere. If you publish the storefront's views, add `resources/views/vendor/ecommerce-storefront` too (your app's `resources/views` source usually covers it).

## 4. Load the behavior engine

In `resources/js/app.js`:

```js
import apuiBehavior from '@artisanpack-ui/behavior/alpine';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
```

Alpine comes with Livewire. Don't import it again: the behavior engine registers itself as Alpine starts.

## 5. Build

```bash
npm run build   # or npm run dev
```

## Which entries the storefront loads

The package layout loads your Vite entries `resources/css/app.css` and `resources/js/app.js`, but only when a Vite build (`public/build/manifest.json`) or a running dev server is present. If your entries have other names, change them with the `ap.ecommerceStorefrontLivewire.layout.viteEntries` filter:

```php
addFilter( 'ap.ecommerceStorefrontLivewire.layout.viteEntries', function ( array $entries ): array {
    return [ 'resources/css/shop.css', 'resources/js/shop.js' ];
} );
```

Return an empty array to load nothing. This applies only to the package layout (through `ecommerce-storefront::partials.vite-assets`). When `storefront.layout` points at your own layout, that layout loads its own assets. See [Layouts](layouts.md).

## Stripe.js

The Stripe payment driver loads Stripe.js from `https://js.stripe.com/v3` at runtime, once per page, as Stripe requires. Don't bundle it. Card details go from Stripe's iframe straight to Stripe and never reach Livewire or your server.

If your application sends a Content Security Policy, allow:

- `script-src https://js.stripe.com`
- `frame-src https://js.stripe.com https://hooks.stripe.com`
- `connect-src https://api.stripe.com`

See Stripe's CSP guide for wallets and 3-D Secure, and [Payment Drivers](../checkout/payment-drivers.md).

## Content Security Policy

If your application sends a Content Security Policy with a nonce, the storefront's inline `<style>` and its flash toast script carry the same nonce: they read it from `Vite::cspNonce()`. Generate the nonce in a middleware that runs on storefront and account pages (add it to `storefront.middleware` and `account.middleware`):

```php
use Illuminate\Support\Facades\Vite;

Vite::useCspNonce();

$nonce = Vite::cspNonce();

$response->headers->set( 'Content-Security-Policy', "script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval' https://js.stripe.com; style-src 'self' 'nonce-{$nonce}'; style-src-attr 'unsafe-inline'; frame-src https://js.stripe.com https://hooks.stripe.com; connect-src 'self' https://api.stripe.com" );
```

A nonce covers `<style>` tags, but not inline `style="…"` attributes: no nonce can. `livewire-ui-components` 3.0 renders some of those itself. Its dialog, drawer, popover, dropdown menu, tooltip, combobox, and toaster wrap their content in an element with `style="display: contents"`, and the range sets position and size through `style`. A nonce-only `style-src` blocks all of them, so wrappers start taking up layout space and the price slider renders wrong. The `style-src-attr 'unsafe-inline'` directive above allows inline style attributes only, while `<style>` tags still need the nonce. Browsers that don't support `style-src-attr` fall back to `style-src` and block the attributes, so if you need to support them, add `'unsafe-inline'` to `style-src` instead and drop the nonce from it (a nonce makes browsers ignore `'unsafe-inline'`).

A nonce covers script and style tags, but not the expressions in `wire:` and `x-` attributes. Livewire and Alpine evaluate those with `new Function()`, which a policy blocks unless `script-src` allows `'unsafe-eval'`. Without it, buttons, drawers, and forms stop responding. Livewire 4 has a `csp_safe` option that avoids `'unsafe-eval'`, but it only supports simple expressions, and the storefront isn't tested with it, so keep `'unsafe-eval'` for storefront pages.

## Checklist

| Symptom | Missing step |
| --- | --- |
| Storefront is unstyled | Steps 2, 3, and 5, or a Vite entry name that doesn't match |
| Buttons, inputs, and cards look half-styled | daisyUI still loaded (step 1), or the `@layer` line missing or not first (step 3) |
| The cart drawer, filters, menu, or dialogs don't open | The behavior engine isn't registered (step 4) |
| Some storefront classes have no effect | The `src/**/*.php` source line (classes built in PHP) |
| Card form never appears at checkout | A CSP blocking `js.stripe.com`, or Stripe keys missing |

See [Troubleshooting](../troubleshooting.md) for more.
