---
title: Accessibility
---

# Accessibility

The storefront targets **WCAG 2.2 AA**. Every shopper flow (browsing, choosing options, the cart, checkout, the account, and order lookup) is meant to work by keyboard and with a screen reader. This page says what is in place, how it is tested, what is known not to meet the target yet, and how to report a problem.

## What is in place

- **Skip link.** The standalone layout's first tab stop is "Skip to content", which moves focus to the main content.
- **Cart drawer focus.** When the drawer opens (after "Add to cart" or from the header cart button), focus moves to its title, so screen readers announce "Your cart (n items)". Escape or "Close cart" closes it, and focus returns to the control that opened it.
- **Checkout focus.** After each step, focus moves to the next step's heading. A failed step shows an error summary ("There are 3 problems to fix:") with `role="alert"`, and focus moves to it. Each error is a link that moves focus to its field.
- **Live regions.** Polite live regions announce cart updates ("Added to your cart."), the result count when a filter changes, checkout step changes, what changed when a variant is chosen, header search results, and the gallery's current image.
- **Header search.** The search box is a combobox: the arrow keys move through the suggestions (`aria-activedescendant`), Enter opens the highlighted one or submits the search, and Escape closes the list.
- **Swatches and ratings.** Option swatches are native radio groups (checkboxes for multi-select filters) inside a `fieldset` with a `legend`, so the arrow keys move within a group and Tab moves between groups. A disabled option says why. The review form's stars are labelled radios ("4 stars").
- **Prices read correctly.** A sale price is read as one sentence, "Sale price: was $25.00, now $19.00", and a range as "From $10.00 to $20.00", instead of bare amounts.
- **Stock in text, not colour.** Stock status is always written out ("In stock", "Low stock", "Out of stock"); colour only tints the icon.
- **Ratings in text.** Star ratings are read as "Rated 4.5 out of 5" with the review count.
- **Target size.** Filter options and suggestion rows are at least 24px tall, and the suite checks target size on every screen.
- **Contrast on soft alerts.** Soft (tinted) alerts use the base text colour instead of the tint colour, so their text keeps 4.5:1. Field errors the storefront prints itself mix the error colour with the text colour for the same reason.
- **Visible focus.** Product card links show a focus outline; swatches and gallery thumbnails draw a focus outline in the primary colour.
- **Product cards.** The product name is the card's only link and stretches over the whole card, so there is one link per product.
- **Field errors.** The quantity and rating inputs link their error with `aria-describedby`.
- **Empty states and loading.** Empty states are `status` regions, so a screen reader hears when filtering empties a list. Loading placeholders are `aria-busy` with a "Loading…" status.
- **New tabs.** The terms link at checkout, which opens a new tab, says so to screen readers.
- **Language.** The layout's `<html lang>` follows the application locale.

## How it is tested

The browser suite (see [Browser Tests](Advanced-Browser-Tests)) drives the storefront in Chromium against a seeded demo store:

- **axe on every screen.** `tests/Browser/AccessibilityTest.php` runs every axe rule, with `target-size` (WCAG 2.2, 24px) turned on, on the catalog, a category, a tag, simple and variable products, search (with and without results), the empty and filled cart, each checkout step, order lookup, the guest order page, the confirmation, and every account screen. It also checks screens showing errors: a bad coupon, a failed checkout step, and a failed order lookup. Any violation fails the build, except the known library issues below.
- **Keyboard.** `tests/Browser/KeyboardTest.php` checks the skip link, adding to the cart by keyboard with focus moving into the drawer and back on Escape, opening the drawer from the header button, choosing a variant with the arrow keys and hearing the change, the result count announcement, the checkout error summary and step focus, header search with the arrow keys and Escape, and the focus outline on a product card link.

The axe helper is `tests/Browser/Support/Accessibility.php` (`Accessibility::assertAccessible( $page )`).

## Known issues

These come from `artisanpack-ui/livewire-ui-components` markup the storefront cannot change.

- **Select names.** `x-artisanpack-select` labels with a `<legend>`, so the select itself has no accessible name ([livewire-ui-components#117](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/117)).
- **Drawer toggle.** The library drawer's `drawer-toggle` checkbox is unlabelled and outside any landmark ([livewire-ui-components#117](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/117)). The cart drawer takes it out of the tab order and hides it from screen readers, and moves focus to its title instead.
- **Breadcrumbs.** `x-artisanpack-breadcrumbs` puts its separator `<span>`s straight into its `<ul>`, so the list markup is invalid. Filed on livewire-ui-components.
- **Accordion sections.** `x-artisanpack-collapse` inside an accordion renders an unlabelled radio as the section toggle (the product page's details sections). Filed on livewire-ui-components.
- **Field error contrast.** Form components print field errors in `text-error`, about 3:1 on the default light theme. Filed on livewire-ui-components.

Each is excluded from the automated check by axe rule and selector in `Accessibility::LIBRARY_ISSUES`; a node is ignored only when it sits inside that selector, so nothing else can regress unnoticed. An exclusion is removed when the library fixes it.

## What your theme needs

The storefront uses daisyUI theme colours (`base-content`, `primary`, `error`, and so on). If you use your own theme, keep text colours at 4.5:1 against the backgrounds they sit on, including `base-content` at 70% opacity for secondary text and the `primary` button text. If you use your own layout, keep a skip link to the content and set `<html lang>`. See [Layouts](Installation-Layouts).

## Reporting an issue

Open an issue on the [GitHub repository](https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/issues) with:

- the page and what you were doing;
- the assistive technology, browser, and operating system;
- what you expected and what happened.
