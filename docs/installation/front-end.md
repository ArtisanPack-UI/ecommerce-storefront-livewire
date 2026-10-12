---
title: Front-End Setup
---

# Front-End Setup

The package ships no CSS or JavaScript build. Your application's Tailwind build styles the storefront. Until you add the storefront to it, pages render unstyled.

The browser test suite builds a host like this. Use it as a working reference:

- `tests/Browser/workbench/resources/css/app.css`
- `vite.config.js`

## 1. Tailwind 4 and daisyUI 5

The storefront uses Tailwind CSS 4 and daisyUI 5 class names. Install them if your application does not already:

```bash
npm install -D tailwindcss @tailwindcss/vite daisyui@~5.0
```

Pin daisyUI to `~5.0`. Later 5.x releases hide `.tab-content`, so the component library's tab panels render empty ([livewire-ui-components#119](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/119)).

## 2. Add the storefront to your Tailwind sources

`php artisan ecommerce-storefront:install` prints the storefront's two lines. Add them, and the component library's views if your app does not list them already, to your Tailwind entry, for example `resources/css/app.css`:

```css
@import 'tailwindcss';

@source "../../vendor/artisanpack-ui/ecommerce-storefront-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-storefront-livewire/src/**/*.php";

/* The component library's views, if your app does not list them already. */
@source "../../vendor/artisanpack-ui/livewire-ui-components/resources/views/**/*.php";
@source "../../vendor/artisanpack-ui/livewire-ui-components/src/**/*.php";

@custom-variant dark (&:where(.dark, .dark *));

@plugin "daisyui" {
    themes: light --default, dark --prefersdark;
}
```

The paths are relative to the CSS file. Adjust them if your entry lives elsewhere. If you publish the storefront's views, add `resources/views/vendor/ecommerce-storefront` too (your app's `resources/views` source usually covers it).

The test workbench's entry is the same file with paths pointing at the package checkout instead of `vendor/`:

```css
@import 'tailwindcss';

@source '../../../../../resources/views/**/*.blade.php';
@source '../../../../../src/**/*.php';
@source '../../../../../vendor/artisanpack-ui/livewire-ui-components/resources/views/**/*.php';
@source '../../../../../vendor/artisanpack-ui/livewire-ui-components/src/**/*.php';

@custom-variant dark (&:where(.dark, .dark *));

@plugin "daisyui" {
    themes: light --default, dark --prefersdark;
}
```

## 3. No scripts to add

The storefront needs no npm packages. Its interactive parts use Alpine, which comes with Livewire; do not import Alpine again.

## 4. Build

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

Return an empty array to load nothing. This applies only to the package layout (through `ecommerce-storefront::partials.vite-assets`). When `storefront.layout` points at your own layout, that layout loads its own assets. See [Layouts](Installation-Layouts).

## Stripe.js

The Stripe payment driver loads Stripe.js from `https://js.stripe.com/v3` at runtime, once per page, as Stripe requires. Do not bundle it. Card details go from Stripe's iframe straight to Stripe and never reach Livewire or your server.

If your application sends a Content Security Policy, allow:

- `script-src https://js.stripe.com`
- `frame-src https://js.stripe.com https://hooks.stripe.com`
- `connect-src https://api.stripe.com`

See Stripe's CSP guide for wallets and 3-D Secure, and [Payment Drivers](Checkout-Payment-Drivers).

## Checklist

| Symptom | Missing step |
| --- | --- |
| Storefront is unstyled | Steps 2 and 4, or a Vite entry name that does not match |
| Tab panels on the account orders page are blank | daisyUI newer than 5.0.x (step 1) |
| Some storefront classes have no effect | The `src/**/*.php` source line (classes built in PHP) |
| Card form never appears at checkout | A CSP blocking `js.stripe.com`, or Stripe keys missing |
