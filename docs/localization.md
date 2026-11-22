---
title: Localization
menu_order: 60
---

# Localization

Every shopper-facing string in the storefront goes through Laravel's translation functions. The package ships JSON catalogues for nine languages.

## Shipped languages

| Language | Locale | File |
| --- | --- | --- |
| English | `en` | `lang/en.json` |
| Spanish | `es` | `lang/es.json` |
| French | `fr` | `lang/fr.json` |
| German | `de` | `lang/de.json` |
| Brazilian Portuguese | `pt_BR` | `lang/pt_BR.json` |
| Japanese | `ja` | `lang/ja.json` |
| Simplified Chinese | `zh_CN` | `lang/zh_CN.json` |
| Italian | `it` | `lang/it.json` |
| Arabic | `ar` | `lang/ar.json` |

> **Note:** The Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic catalogues are machine-translated and waiting for review by native speakers. Corrections are welcome as issues or pull requests.

The catalogues are loaded with `loadJsonTranslationsFrom()`, so strings are keyed by their English text. The storefront follows the application locale (`app()->getLocale()`), and the standalone layout's `<html lang>` follows it too.

## Overriding strings

Publish the catalogues:

```bash
php artisan vendor:publish --tag=ecommerce-storefront-lang
```

They are copied to `lang/vendor/ecommerce-storefront/`. The package loads that directory as well as its own, so you can change individual strings there.

Every package's JSON catalogue loads into one shared namespace. A key the storefront shares with the engine must read the same in both, or which one wins depends on load order; the test suite checks this for every locale the engine ships (`tests/Feature/Localization/CataloguesTest.php`). In the Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic catalogues, keys shared with `artisanpack-ui/ecommerce-admin-livewire` use the admin's wording too. When you override a shared string, override it the same way for every package that uses it.

## Plurals

Counts use `trans_choice()` with a singular and a plural segment:

```php
trans_choice( ':count product|:count products', $count, [ 'count' => $count ] );
```

Translations keep the same `:placeholders` as the key. Laravel picks the segment by the locale's plural rule, so a catalogue needs as many segments as its language has forms:

- **English, Spanish, French, German, Brazilian Portuguese, and Italian** use two segments, like the key. In French, 0 is singular ("0 produit"); in the others it is plural.
- **Arabic** has six forms (zero, one, two, 3–10, 11–99, other). Each plain `singular|plural` key has six segments in `ar.json`, and an override in `lang/vendor/ecommerce-storefront/ar.json` must have six too. With only two, Arabic shows the plural for one.
- **Japanese and Chinese** have one form, and Laravel always shows the first segment for them. Their catalogues give each plain `singular|plural` key a single segment that carries `:count`, written for any number.

## Formats that follow the engine

The storefront uses the engine's helpers, so the storefront and the admin agree:

| What | Helper |
| --- | --- |
| Money | `MoneyFormatter` (through `<x-artisanpack-ec-money>` and `<x-artisanpack-ec-price>`) |
| Order, review, and download dates | `LocalizedDate` |
| The tax note after a price ("incl. VAT", "excl. USt.") | The label the engine resolves with `TaxLabel` |

Money is formatted in the application locale, or in the `locale` you pass to the money and price components. The tax label follows the locale, and a store can override it per locale in the engine's `artisanpack.ecommerce.localization.tax_labels`.

## Right-to-left languages

Markup uses logical CSS properties (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`, `text-start`), so a right-to-left layout is a stylesheet concern. The standalone layout sets `dir="rtl"` on `<html>` for the locales in `storefront.rtl_locales` (Arabic among them); in your own layout, set `dir="{{ \ArtisanPackUI\EcommerceStorefrontLivewire\Support\TextDirection::for() }}"` the same way.

## Translation lint

CI fails when a user-facing string is not wrapped in a translation function, or when a key is missing from any catalogue. Run the same check locally:

```bash
vendor/bin/testbench ecommerce:lint:translations --no-engine --path=src --path=resources --lang=lang
```

It scans `src/` and `resources/` (Blade included) and compares against every catalogue in `lang/`. `--no-engine` leaves the engine's own sources out. The test suite also checks that every locale has the same keys, that English is its own translation, and that translations keep their placeholders and have the right number of plural segments for their language.

When you add a string, add it to all nine catalogues.

## Adding a language

Copy `lang/en.json` to `lang/vendor/ecommerce-storefront/{locale}.json` in your application and translate the values. Keep every `:placeholder`, and give each plural key as many `|` segments as the language has plural forms (see [Plurals](#plurals)): two for most languages, six for Arabic, and one for Japanese and Chinese. Consider contributing it back.
