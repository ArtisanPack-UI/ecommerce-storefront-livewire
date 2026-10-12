---
title: Browser Tests
---

# Browser Tests

For contributors. The storefront has a browser suite (Pest 4's browser plugin on Playwright) that drives the real storefront in Chromium against a seeded demo store. It covers what unit and Livewire tests cannot: whole shopper journeys through checkout, keyboard use and focus, live region announcements, real assets, and an axe accessibility check on every screen.

## Running it

```bash
npm ci
npx playwright install chromium
npm run build:browser
composer test:browser
```

`composer test:browser` runs the build again before the tests, so after the first run it is the only command you need.

`composer test` runs only the Unit and Feature suites (`--testsuite=Unit,Feature`); loading the Browser suite would start Playwright. CI runs the browser suite in its own `browser` job, on Livewire 3 and 4.

## How the harness works

| Piece | Where | Role |
| --- | --- | --- |
| Base test case | `tests/BrowserTestCase.php` | Serves the Testbench app from the workbench `public` directory, loads the workbench stylesheet through the `ap.ecommerceStorefrontLivewire.layout.viteEntries` filter, and seeds a demo store (`ecommerce:seed-demo` with 12 products, 5 orders, seed 44) named "Demo Store" in USD |
| Test card | `tests/Browser/Support/TestCardDriver.php` | A `test-card` gateway (a fake client gateway) and its payment driver, so checkout can be paid without a payment provider. The driver renders "Pay with test card" (`[data-test-card-pay]`) and "Use a declined card" (`[data-test-card-decline]`). |
| Workbench | `tests/Browser/workbench/` | Stands in for a host application: `resources/css/app.css` imports Tailwind 4, adds the storefront's and the component library's views as `@source`s (as the install command tells a host to), and loads daisyUI with the light and dark themes |
| Build | `package.json`, `vite.config.js` | `npm run build:browser` builds the workbench stylesheet into `tests/Browser/workbench/public/build`. The package itself ships no front-end build; `package.json` exists only for this harness. |
| Accessibility helper | `tests/Browser/Support/Accessibility.php` | `Accessibility::assertAccessible( $page )` runs every axe rule (axe-core is injected into every page by the Pest browser plugin), with `target-size` turned on, and fails on any violation |
| Locators | `tests/Browser/Support/Locators.php` | Finds controls the way a person or screen reader does |
| Tests | `tests/Browser/*Test.php` | Journeys, accessibility, keyboard |

The base test case throws if `public/build/manifest.json` is missing. Run `npm run build:browser` first.

Pest's group config in `tests/Pest.php` applies `BrowserTestCase` and the `browser` group to everything in `tests/Browser`, with a 10-second browser timeout.

## What the suites cover

| Suite | Covers |
| --- | --- |
| `JourneysTest` | Adding to the cart from the catalog and the product page, choosing a variant, updating the cart and applying a coupon, guest checkout in the multi-step layout through to the confirmation, signed-in checkout on the single-page layout, guest order lookup (found, and not found without saying which part was wrong), and opening an order from the account |
| `AccessibilityTest` | axe on every storefront and account screen, the filled cart and each checkout step, and screens showing errors |
| `KeyboardTest` | The skip link, the cart drawer's focus and Escape, choosing a variant with the arrow keys, filter result announcements, the checkout error summary and step focus, header search with the arrow keys and Escape, and the focus outline on product card links |

## Locators

```php
use Tests\Browser\Support\Locators;

Locators::label( 'Email' );               // the field labelled "Email" (a trailing * is allowed)
Locators::role( 'Add to cart' );          // a button named "Add to cart"
Locators::role( 'Shop', 'link' );         // a link named "Shop"
Locators::field( 'shipping.postal_code' ); // an input, select, or textarea whose id contains the key
```

Find fields by label, or with `Locators::field()` by their binding: the component library prefixes generated ids, so an exact id won't match.

## Writing a test

```php
use Tests\Browser\Support\Accessibility;

it( 'adds a mug to the cart', function (): void {
    $product = makeProduct( 1900, [ 'name' => 'Blue mug', 'slug' => 'blue-mug' ] );

    $page = visit( route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) )
        ->click( '[data-add-to-cart]' )
        ->assertSeeIn( '#ecommerce-cart-drawer-panel', 'Blue mug' )
        ->assertNoJavaScriptErrors();

    Accessibility::assertAccessible( $page );
} );
```

- Focus settles just after Livewire's update. Check focus and announcements with `assertScript()`, which retries until it holds.
- Assert `assertNoJavaScriptErrors()` on new screens.
- `tests/Pest.php` has helpers for test data, such as `makeProduct()`, `makeVariableProduct()`, `checkoutCart()`, `placedOrder()`, and `shopper()`.
- Prefer the `data-*` hooks the views carry (`[data-add-to-cart]`, `[data-checkout-contact-submit]`, `[data-product-card="…"]`) over classes.

## Selectors that never match

The plugin's `keys()` and `click()` wait for their selector with no timeout of their own, so a selector that matches nothing can hang the run instead of failing. Use explicit CSS selectors. A bare word is treated as text to find, not a tag: to send keys to the page, use `html > body`, not `body`. If a run hangs, look for a selector or label that changed. CI caps the browser job at 20 minutes so a hang cannot block the pipeline.

## Known library exclusions

`Accessibility::LIBRARY_ISSUES` lists axe failures that come from `livewire-ui-components` markup this package cannot change. Each entry is an axe rule and a CSS selector; a node is ignored only when it, or an ancestor, matches that selector. Remove an entry when the library fixes it, and the suite enforces the rule again. See [Accessibility](Accessibility).

## daisyUI pin

`package.json` pins `daisyui` to `~5.0.48`. Later 5.x releases hide `.tab-content`, which leaves the component library's tab panels empty ([livewire-ui-components#119](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/119)). Keep the pin until that is fixed.
