# ArtisanPack UI Ecommerce Storefront (Livewire)

The customer-facing storefront for the [ArtisanPack UI ecommerce engine](https://github.com/ArtisanPack-UI/ecommerce): catalog, product pages, cart, checkout, customer accounts, guest order lookup, and search, built with Livewire on [`livewire-ui-components`](https://github.com/ArtisanPack-UI/livewire-ui-components). With [`visual-editor`](https://github.com/ArtisanPack-UI/visual-editor) installed it also ships commerce blocks and editable product and category templates.

The storefront calls the engine's services in-process and owns no tables. Its routes work right after install, and every screen is also a Livewire component you can embed in your own pages.

## Features

- **Catalog and product pages**: Filters with facet counts, sorting, and pagination kept in the query string; category and tag pages; purchase forms for simple, variable, grouped, bundled, and digital products; reviews; related products, upsells, and cross-sells.
- **Cart**: A cart page with coupons and a shipping estimate, a header cart button, and a slide-out cart drawer.
- **Checkout**: A multi-step wizard or a single page, chosen by the store owner. Guest checkout rules, optional account creation, and idempotent order placement.
- **Payments**: A Stripe Payment Element driver, a redirect driver, and a registry for other gateways' payment UIs. Card data never enters Livewire state.
- **Accounts**: Order history and detail, address book, downloads and licence keys, profile and notification preferences, and claiming guest orders.
- **Guest order lookup** and **search** with facets and live header suggestions.
- **Visual-editor blocks and templates** when `artisanpack-ui/visual-editor` is installed.
- **Extension points**: Registries for purchase forms and payment drivers, and filters for catalog filters and sorts, product sections, cart sections, checkout steps, and header actions.
- **Accessible and translated**: Targets WCAG 2.2 AA, checked with axe and keyboard tests in a real browser. Ships English, Spanish, French, and German.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Livewire 3.6+ or 4
- `artisanpack-ui/ecommerce` 1.1+
- `artisanpack-ui/livewire-ui-components` 2.1+, with Tailwind CSS and daisyUI in the host app

## Installation

```bash
composer require artisanpack-ui/ecommerce-storefront-livewire
php artisan ecommerce-storefront:install
```

The service provider is auto-discovered and registers the package with the engine's satellite registry. The install command publishes the config, prints the Tailwind `@source` lines and the layout snippet, and warns when it finds no sign-in or registration routes or no configured payment gateway.

Add the `@source` lines to your stylesheet, then open `/shop`. For a demo store:

```bash
php artisan ecommerce:seed-demo
```

To use your own layout, point `storefront.layout` at it and include the global partial (cart drawer, toasts, and cart merge prompt):

```blade
@include( 'ecommerce-storefront::partials.global' )
```

See [Layouts](docs/installation/layouts.md) for the full layout contract and for Blade component layouts.

## Documentation

- [Getting Started](docs/getting-started.md)
- [Installation](docs/installation.md): [requirements](docs/installation/requirements.md), [configuration](docs/installation/configuration.md), [front-end setup](docs/installation/front-end.md), [layouts](docs/installation/layouts.md)
- [Usage](docs/usage.md): [screens](docs/usage/screens.md)
- [Checkout](docs/checkout.md): [layouts](docs/checkout/layouts.md), [guest checkout](docs/checkout/guest-checkout.md), [account creation](docs/checkout/account-creation.md), [payment drivers](docs/checkout/payment-drivers.md)
- [Visual Editor](docs/visual-editor.md): [blocks](docs/visual-editor/blocks.md), [templates and patterns](docs/visual-editor/templates.md)
- [Extending](docs/extending.md): [embedding components](docs/extending/embedding-components.md), [Blade components](docs/extending/blade-components.md), [hooks reference](docs/extending/hooks-reference.md)
- [Accessibility](docs/accessibility.md), [Localization](docs/localization.md), [SEO](docs/seo.md)
- [Advanced](docs/advanced.md): [Artisan commands](docs/advanced/artisan-commands.md), [performance](docs/advanced/performance.md), [browser tests](docs/advanced/browser-tests.md)

## Testing

```bash
composer test           # Unit and Feature suites
composer test:browser   # Browser suite; run npm ci and npx playwright install chromium first
composer lint
```

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing
guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.

## License

MIT. See [LICENSE](LICENSE).
