---
title: Artisan Commands
---

# Artisan Commands

## `ecommerce-storefront:install`

```bash
php artisan ecommerce-storefront:install [--force]
```

Sets up the storefront in a host application. It:

1. publishes `config/artisanpack/ecommerce-storefront-livewire.php` (tag `ecommerce-storefront-config`). If you published it before, it asks before overwriting; `--force` overwrites without asking;
2. prints the Tailwind `@source` lines to add to your main stylesheet, so your build includes the storefront's classes;
3. prints the layout steps: to use your own layout, set `storefront.layout` to a view that yields `title` and `content`, includes `ecommerce-storefront::partials.global`, and has `@stack( 'head' )` in its `<head>`. For a Blade component layout it prints a one-line wrapper view to point `storefront.layout` at. See [Layouts](Installation-Layouts);
4. checks that the `auth.login_route` and `auth.register_route` routes exist (`login` and `register` by default), and warns for each one that doesn't;
5. checks the payment gateways: warns when none is registered, or when none is configured (Stripe counts as configured once its secret and publishable keys are set), and otherwise lists the gateways that are ready;
6. when `artisanpack-ui/visual-editor` is installed, says whether storefront pages render through its templates, and warns when `visual_editor.templates` is off. See [Templates and Patterns](Visual-Editor-Templates).

It is safe to run again.

## Engine commands a store uses with the storefront

| Command | Use |
| --- | --- |
| `php artisan ecommerce:seed-demo` | Seed a demo store (products, customers, orders) to try the storefront. Refuses to run on a store with data unless you pass `--fresh`. Options: `--products=50`, `--orders=200`, `--seed=` (the same seed builds the same store), `--force` (no prompt; allows production). See [Quick Start Guide](Getting-Started). |
| `php artisan ecommerce:verify-satellite` | Run the engine's contract suites against a satellite (such as this package) and write a verification report. `--sign` signs a passing report; `--check-signature` verifies one. |
| `vendor/bin/testbench ecommerce:lint:translations` | Check translations in this package. See [Localization](Localization). |

The engine schedules the commands that keep carts and stock tidy, so the storefront needs nothing extra beyond Laravel's scheduler running (`php artisan schedule:run` every minute). Among them:

| Command | Default schedule | Does |
| --- | --- | --- |
| `ecommerce:release-expired-reservations` | Every minute | Frees stock held by checkouts that expired |
| `ecommerce:flag-abandoned-carts` | Every 5 minutes | Flags carts left in checkout (with an email) as abandoned |
| `ecommerce:prune-carts` | Daily, 03:30 | Deletes expired carts and old converted carts, releasing their stock holds |
| `ecommerce:refresh-fx-rates` | Daily, 05:30 | Refreshes exchange rates for the currencies shoppers can switch to |

Turn the engine's schedule off with `artisanpack.ecommerce.schedule.enabled`, or change a task's cron expression in `artisanpack.ecommerce.schedule.tasks`.
