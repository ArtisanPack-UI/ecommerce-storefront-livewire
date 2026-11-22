---
title: Accessibility
menu_order: 55
---

# Accessibility

The storefront targets **WCAG 2.2 AA**. Every shopper flow (browsing, choosing options, the cart, checkout, the account, and order lookup) is meant to work by keyboard and with a screen reader. This page says what is in place, how it is tested, what is known not to meet the target yet, and how to report a problem.

## What is in place

- **Skip link.** The standalone layout's first tab stop is "Skip to content", which moves focus to the main content.
- **Full page loads.** Links styled as buttons (such as "View cart", "Checkout", and the account links) load the next page in full instead of swapping it in with `wire:navigate`, so screen readers announce the new page and focus starts at the top.
- **Cart drawer focus.** When the drawer opens (after "Add to cart" or from the header cart button), focus moves to its title, so screen readers announce "Your cart (n items)". Escape or "Close cart" closes it, and focus returns to the control that opened it.
- **Checkout focus.** After each step, focus moves to the next step's heading. A failed step shows an error summary ("There are 3 problems to fix:") with `role="alert"`, and focus moves to it. Each error is a link that moves focus to its field.
- **Error summaries on other forms.** The review form, the address form, the profile, order lookup, and guest-order claim show the same error summary after a failed submit, and focus moves to it.
- **Refused add to cart.** When "Add to cart" is refused (no variant chosen, too few in stock, a missing option), the reason is announced as an alert: "Not added to your cart: …". A missing variant choice is a field error on that group ("Choose a size to add this to your cart."), linked with `aria-describedby`, its inputs marked `aria-invalid`, and focus moves to it (to the group itself when every option in it is disabled); other variant errors are linked from the button, and a grouped product's quantity error from "Add selected to cart".
- **Toasts.** Confirmations are announced politely (`role="status"`) and errors assertively (`role="alert"`); every toast has a labelled "Dismiss notification" button. A toast with an action ("Undo" after a removal, "View cart" after an add) stays until it is dismissed, so there is time to use it. A new removal replaces the previous removal's toast, so Undo always restores the line removed last.
- **Focus kept when a control goes away.** Removing a cart line (on the cart page or in the drawer) moves focus to "Undo", and "Undo" moves it back to the restored line's "Remove". Opening the review form moves focus to its heading, and cancelling or submitting it returns focus to "Write a review", which says whether the form is open (`aria-expanded`). Deleting an address moves focus to "Add an address".
- **Live regions.** Polite live regions announce cart updates ("Added to your cart."), the result count when a filter changes, checkout step changes, what changed when a variant is chosen, header search results, and the gallery's current image ("Image 2 of 4: Mug from the side").
- **Header search.** The search box is a combobox: the arrow keys move through the suggestions (`aria-activedescendant`), Enter opens the highlighted one or submits the search, and Escape closes the list. On phones the box is replaced by a "Search" link to the search page.
- **Gallery.** The zoom button is named from the image it shows ("Zoom image: Mug from the front"), so the image's alt text isn't lost behind it.
- **Swatches and ratings.** Option swatches are native radio groups (checkboxes for multi-select filters) inside a `fieldset` with a `legend`, so the arrow keys move within a group and Tab moves between groups. A disabled option says why. The review form's stars are labelled radios ("4 stars"), with a focus outline around the group and the chosen rating written out ("4 out of 5").
- **Prices read correctly.** A sale price is read as one sentence, "Sale price: was $25.00, now $19.00", and a range as "From $10.00 to $20.00", instead of bare amounts.
- **Stock in text, not colour.** Stock status is always written out ("In stock", "Low stock", "Out of stock"); colour only tints the icon.
- **Ratings in text.** Star ratings are read as "Rated 4.5 out of 5" with the review count. Each row of the reviews breakdown is a button named for the whole row ("5 stars: 98 reviews (78%)"), pressed while it filters the list; its bar is decorative.
- **Target size.** Filter options and suggestion rows are at least 24px tall, and the suite checks target size on every screen.
- **Contrast on soft alerts.** Soft (tinted) alerts use the base text colour instead of the tint colour, so their text keeps 4.5:1. Field errors the storefront prints itself mix the error colour with the text colour for the same reason.
- **Visible focus.** Product card links show a focus outline; swatches and gallery thumbnails draw a focus outline in the primary colour, and the rating input draws the design system's focus ring around the focused star.
- **Product cards.** The product name is the card's only link and stretches over the whole card, so there is one link per product.
- **Field errors.** On every form (checkout, the cart's coupon, purchase options, reviews, the account, order lookup, and claim), an invalid field is marked `aria-invalid="true"`, and its error and hint are linked to it with `aria-describedby`. The storefront does this with its own `<x-artisanpack-ec-field>` wrapper around the library's controls (see [Known issues](#known-issues)). The quantity and rating inputs link their error the same way.
- **Repeated buttons.** In the address book, "Edit", "Delete", and "Use for shipping" carry the address in screen-reader text ("Edit: Home, 1 Main Street, Springfield"), so each button has its own name.
- **Phones.** Below 1024px there are no invisible tab stops, and at 320px wide the header wraps instead of scrolling sideways.
- **Empty states and loading.** Empty states are `status` regions, so a screen reader hears when filtering empties a list. Loading placeholders are `aria-busy` with a "Loading…" status.
- **New tabs.** The terms link at checkout, which opens a new tab, says so to screen readers.
- **Language and direction.** The layout's `<html lang>` follows the application locale, and `dir` is `rtl` for the languages in `storefront.rtl_locales` (Arabic, Hebrew, Persian, and Urdu by default). The gallery's arrow keys and chevrons follow the direction.

## How it is tested

The browser suite (see [Browser Tests](https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/blob/main/CONTRIBUTING.md#browser-tests) in the contributing guide) drives the storefront in Chromium against a seeded demo store:

- **axe on every screen.** `tests/Browser/AccessibilityTest.php` runs every axe rule, with `target-size` (WCAG 2.2, 24px) turned on, on the catalog, a category, a tag, simple and variable products, search (with and without results), the empty and filled cart, each checkout step, order lookup, the guest order page, the confirmation, and every account screen. It also checks screens showing errors: a bad coupon, a failed checkout step, and a failed order lookup. Any violation fails the build, except the known library issue below. A guard test injects an unlabelled field into the page content and checks that the suite still reports it, so the exclusions stay narrow.
- **Keyboard.** `tests/Browser/KeyboardTest.php` checks the skip link, adding to the cart by keyboard with focus moving into the drawer and back on Escape, opening the drawer from the header button, choosing a variant with the arrow keys and hearing the change, the result count announcement, the checkout error summary and step focus, header search with the arrow keys and Escape, the focus outline on a product card link, the refused add-to-cart announcement, the error summary after an empty review, the quick-add button's name starting with its visible text, focus after removing a cart line and after undo, focus moving into and out of the review form, focus after deleting an address, the gallery's zoom button name and image announcement, and the rating input's focus outline and text.
- **Phones.** `tests/Browser/MobileTest.php` runs axe at 390×844 on the catalog (with the filters drawer open), a product, and each checkout step with the summary open. It also opens and closes the filters by keyboard (focus returns to "Filters"), checks that Tab never lands on a drawer toggle, that the header has a search link, and that the header doesn't scroll sideways at 320px.

The axe helper is `tests/Browser/Support/Accessibility.php` (`Accessibility::assertAccessible( $page )`).

## Known issues

This comes from `artisanpack-ui/livewire-ui-components` markup the storefront cannot change.

- **Drawer role.** The library's Drawer renders its panel as an `<aside>` with `role="dialog"`, a role axe doesn't allow on `<aside>`. Screen readers still announce the cart, filters, and menu drawers as dialogs.

Since 2.0 the library's selects are named by a `<label>` and its breadcrumbs keep their separators inside list items, so the 1.x exclusions for unnamed selects ([livewire-ui-components#117](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/117)) and breadcrumb list markup ([livewire-ui-components#129](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/129)) are gone, and the automated check enforces both rules again.

Since 2.0 the library links field errors and hints to their controls and sets `aria-invalid` itself, so `<x-artisanpack-ec-field>` no longer has to work around it. Use it for your own fields anyway: it feeds the storefront's error summaries (see [Blade Components](extending/blade-components.md)).

The issue is excluded from the automated check by axe rule and selector in `Accessibility::LIBRARY_ISSUES`; a node is ignored only when it sits inside that selector, so nothing else can regress unnoticed. The drawer exclusion covers only the drawer's panel element (`[data-scope="drawer"][data-part="content"]`) and the `aria-allowed-role` rule, not everything inside a drawer. An exclusion is removed when the library fixes it.

## What your theme needs

The storefront draws every colour from the design system's tokens (`--apui-text-default`, `--apui-surface-page`, and so on), in its light and dark themes. If you change the tokens or add a theme, keep text at 4.5:1 against the surfaces it sits on, including muted secondary text and the text on primary buttons. If you use your own layout, keep a skip link to the content, set `<html lang>` and `dir`, and add `@apuiThemeScript` to `<head>` so the shopper's theme applies before the page paints. See [Layouts](installation/layouts.md).

## Reporting an issue

Open an issue on the [GitHub repository](https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/issues) with:

- the page and what you were doing;
- the assistive technology, browser, and operating system;
- what you expected and what happened.
