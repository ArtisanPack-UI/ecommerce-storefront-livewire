# ArtisanPack UI Ecommerce Storefront (Livewire)

The customer-facing storefront for the [ArtisanPack UI ecommerce engine](https://github.com/ArtisanPack-UI/ecommerce): catalog, product pages, cart, checkout, customer accounts, guest order lookup, and search, built with Livewire on [`livewire-ui-components`](https://github.com/ArtisanPack-UI/livewire-ui-components). With [`visual-editor`](https://github.com/ArtisanPack-UI/visual-editor) installed it also ships commerce blocks and editable product and category templates.

> **Status:** in development toward v1.0.0. The build plan is in [`docs/plans/14-ecommerce-storefront-livewire-spec.md`](docs/plans/14-ecommerce-storefront-livewire-spec.md) and the work is tracked in the [v1.0 milestone](https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/milestone/1).

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Livewire 3.6+ or 4
- `artisanpack-ui/ecommerce` 1.0+

## Installation

```bash
composer require artisanpack-ui/ecommerce-storefront-livewire
```

The service provider is auto-discovered and registers the package with the engine's satellite registry.

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing
guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
