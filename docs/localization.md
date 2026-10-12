---
title: Localization
---

# Localization

Every shopper-facing string in the storefront goes through Laravel's translation functions. The package ships JSON catalogues for four languages.

## Shipped languages

| Locale | File |
| --- | --- |
| English | `lang/en.json` |
| Spanish | `lang/es.json` |
| French | `lang/fr.json` |
| German | `lang/de.json` |

The catalogues are loaded with `loadJsonTranslationsFrom()`, so strings are keyed by their English text. The storefront follows the application locale (`app()->getLocale()`), and the standalone layout's `<html lang>` follows it too.

## Overriding strings

Publish the catalogues:

```bash
php artisan vendor:publish --tag=ecommerce-storefront-lang
```

They are copied to `lang/vendor/ecommerce-storefront/`. The package loads that directory as well as its own, so you can change individual strings there.

Every package's JSON catalogue loads into one shared namespace. A key the storefront shares with the engine must read the same in both, or which one wins depends on load order; the test suite checks this for every shipped locale (`tests/Feature/Localization/CataloguesTest.php`). When you override a shared string, override it the same way for every package that uses it.

## Plurals

Counts use `trans_choice()` with a singular and a plural segment:

```php
trans_choice( ':count product|:count products', $count, [ 'count' => $count ] );
```

Translations keep the same number of `|` segments and the same `:placeholders` as the key. Laravel picks the segment by the locale's plural rules: in French, 0 is singular ("0 produit"); in English, Spanish, and German it is plural.

## Formats that follow the engine

The storefront uses the engine's helpers, so the storefront and the admin agree:

| What | Helper |
| --- | --- |
| Money | `MoneyFormatter` (through `<x-artisanpack-ec-money>` and `<x-artisanpack-ec-price>`) |
| Order, review, and download dates | `LocalizedDate` |
| The tax note after a price ("incl. VAT", "excl. USt.") | The label the engine resolves with `TaxLabel` |

Money is formatted in the application locale, or in the `locale` you pass to the money and price components. The tax label follows the locale, and a store can override it per locale in the engine's `artisanpack.ecommerce.localization.tax_labels`.

## Right-to-left languages

Markup uses logical CSS properties (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`, `text-start`), so a right-to-left layout is a stylesheet concern. Set `dir="rtl"` on `<html>` in your layout for those locales.

## Translation lint

CI fails when a user-facing string is not wrapped in a translation function, or when a key is missing from any catalogue. Run the same check locally:

```bash
vendor/bin/testbench ecommerce:lint:translations --no-engine --path=src --path=resources --lang=lang
```

It scans `src/` and `resources/` (Blade included) and compares against every catalogue in `lang/`. `--no-engine` leaves the engine's own sources out. The test suite also checks that every locale has the same keys, that English is its own translation, and that translations keep their placeholders and plural segments.

When you add a string, add it to all four catalogues.

## Adding a language

Copy `lang/en.json` to `lang/vendor/ecommerce-storefront/{locale}.json` in your application and translate the values. Keep every `:placeholder` and `|` segment. Consider contributing it back.
