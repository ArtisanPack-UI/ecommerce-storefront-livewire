---
title: Troubleshooting
menu_order: 99
---

# Troubleshooting

## The storefront is unstyled

**Cause:** Your application's Vite build doesn't load the design system, or doesn't scan the storefront's views. The package ships no CSS build of its own.

**Fix:**

1. Add the stylesheet lines from [Front-End Setup](installation/front-end.md#3-add-the-stylesheet-lines) to your main stylesheet (for example `resources/css/app.css`): the `@layer` line, `@import '@artisanpack-ui/css';`, and the `@source` lines. Check the relative paths from that file to `vendor/`.
2. Make sure Tailwind 4 and `@artisanpack-ui/css` are installed.
3. Rebuild: `npm run build`, or run `npm run dev`.
4. The package layout loads `resources/css/app.css` and `resources/js/app.js` only when `public/build/manifest.json` or a Vite dev server exists. If your entries have other names, set them with `ap.ecommerceStorefrontLivewire.layout.viteEntries`.

## Components look half-styled after upgrading to 2.0

**Cause:** daisyUI is still loaded, or the design system's layer order is missing.

**Fix:** If nothing else uses daisyUI, remove its `@plugin "daisyui"` block and any `@plugin "daisyui/theme"` blocks from your stylesheet, then uninstall the `daisyui` package (the build fails if a block outlives the package). Make `@layer theme, base, apui, components, utilities;` the first line of your stylesheet, before `@import 'tailwindcss';`, then rebuild.

## The cart drawer, the filters drawer, or the menu doesn't open

**Cause:** The design system's behavior engine isn't registered. Dialogs, the product details accordion, toasts, and the licence key copy button need it too.

**Fix:** Register it in `resources/js/app.js`, then rebuild:

```js
import apuiBehavior from '@artisanpack-ui/behavior/alpine';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
```

## Published views look broken after upgrading to 2.0

**Cause:** Views published from 1.x use the `livewire-ui-components` 2.x API and daisyUI class names, which 2.0 no longer loads.

**Fix:** Move your changes to an [extension point](extending.md) if one covers them. Otherwise delete the old copies and publish again with `php artisan vendor:publish --tag=ecommerce-storefront-views --force`, then reapply your changes. See [Upgrading](upgrading.md#published-views).

## The cart drawer, toasts, or the cart merge prompt never appear

**Cause:** Your own layout doesn't include the global partial.

**Fix:** Add `@include( 'ecommerce-storefront::partials.global' )` before `</body>` in the layout `storefront.layout` points at. See [Layouts](installation/layouts.md#the-global-partial).

## The page flashes the light theme before turning dark

**Cause:** Your own layout doesn't apply the stored theme before the page paints.

**Fix:** Add `@apuiThemeScript` inside your layout's `<head>`, before your Vite entries. The package layout already has it.

## Pages have no description, canonical URL, or structured data

**Cause:** Your layout has no `@stack( 'head' )` in its `<head>`. With `artisanpack-ui/seo` installed, the structured data also needs `<x-seo:schema />`.

**Fix:** Add both to your layout's `<head>`. See [SEO](seo.md).

## Storefront routes return 404

**Cause:** The routes are turned off, the prefix is different, or the engine has the storefront satellite marked uninstalled, in which case the package registers nothing.

**Fix:** Check `storefront.routes_enabled` and `storefront.route_prefix` in the config, run `php artisan route:list --name=artisanpack.ecommerce.storefront` to see what is registered, and clear a cached route list with `php artisan route:clear`.

## The "Sign in", "Create an account", or "Sign out" link is missing

**Cause:** The route named in `auth.login_route`, `auth.register_route`, or `auth.logout_route` doesn't exist. The storefront hides links to routes that aren't registered.

**Fix:** Install an auth starter kit, or set the config keys to your own route names. `php artisan ecommerce-storefront:install` warns when the sign-in or registration route is missing. See [Requirements](installation/requirements.md#authentication-routes).

## Shoppers can't pay at checkout

**Cause:** No payment gateway is registered with the engine, or the one registered has no API keys.

**Fix:** Enable Stripe with `ECOMMERCE_STRIPE_ENABLED`, `ECOMMERCE_STRIPE_SECRET_KEY`, and `ECOMMERCE_STRIPE_PUBLISHABLE_KEY`, or install a gateway package. Run `php artisan ecommerce-storefront:install` to check. See [Payment Drivers](checkout/payment-drivers.md).

## The card form never appears at checkout

**Cause:** A Content Security Policy blocks Stripe.js, or the Stripe publishable key is missing.

**Fix:** Allow `https://js.stripe.com` in `script-src` and `frame-src`, `https://hooks.stripe.com` in `frame-src`, and `https://api.stripe.com` in `connect-src`, and check the Stripe keys. See [Front-End Setup](installation/front-end.md#stripejs).

## Buttons and forms stop responding under a Content Security Policy

**Cause:** Livewire and Alpine evaluate `wire:` and `x-` expressions with `new Function()`, which a policy blocks unless `script-src` allows `'unsafe-eval'`. A nonce-only `style-src` also blocks the inline style attributes the component library renders.

**Fix:** Add `'unsafe-eval'` to `script-src` and `style-src-attr 'unsafe-inline'` to the policy. See [Front-End Setup](installation/front-end.md#content-security-policy).

## Storefront strings stay in English

**Cause:** The request's locale has no catalogue, or a published catalogue in `lang/vendor/ecommerce-storefront` is missing the strings added since you published it.

**Fix:** Check `app()->getLocale()` on the request. Publish the catalogues again with `php artisan vendor:publish --tag=ecommerce-storefront-lang --force` and reapply your changes, or delete the published copy to use the package's. See [Localization](localization.md).

## Storefront pages don't use my visual-editor templates

**Cause:** `visual_editor.templates` is off (the default), or `artisanpack-ui/cms-framework` isn't installed.

**Fix:** Set `visual_editor.templates` to `true` and install `cms-framework`. See [Templates and Patterns](visual-editor/templates.md).

## "Notify me" doesn't appear on a sold-out product

**Cause:** The engine is older than 1.4 and has no `BackInStockService`, the product has no price, a variable product's choices aren't all made, or a filter turned it off.

**Fix:** Update the engine to 1.4 or later, and check for a subscriber to `ap.ecommerceStorefrontLivewire.product.backInStockEnabled` that returns `false`. See [Hooks](reference/hooks.md#product-page).

## Category counts or filters are out of date

**Cause:** The category tree and filter counts are cached for `performance.cache_ttl` seconds. They're cleared when the catalog changes through the engine, but not when rows are changed directly in the database.

**Fix:** Run `php artisan cache:clear`, or lower `performance.cache_ttl`. See [Performance](advanced/performance.md).
