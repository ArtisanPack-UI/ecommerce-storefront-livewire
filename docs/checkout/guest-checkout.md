---
title: Guest Checkout
---

# Guest Checkout

Whether a shopper without an account can check out is the engine's `checkout.guest_checkout` setting. Change it on the admin's Checkout settings ("Guest checkout"), or set its config default with `ECOMMERCE_GUEST_CHECKOUT`. The storefront reads it through `CheckoutService::guestCheckout()`, and the engine enforces it again when the order is placed.

A guest is a shopper whose cart has no customer. Sign-in and registration belong to your app: the storefront only links to them.

## Policies

| Value | Admin label | What a guest sees |
| --- | --- | --- |
| `allowed` (default) | Allowed | The full checkout. The contact step offers "Have an account? Sign in for faster checkout". |
| `required_account` | Create an account during checkout | The contact step says "You'll need an account to pay", with **Sign in** and **Create an account** buttons. The guest can fill in contact, address, and shipping. The payment step then asks them to sign in or create an account, and no payment session is created until they do. |
| `disabled` | Sign in required | No checkout: "Sign in to check out", with **Sign in** and **Create an account** buttons. |

An unknown value is treated as `allowed`.

If the engine refuses a guest at any step with `account-required` (for example, the policy changed while they were checking out), the checkout shows the engine's message, and under `disabled` switches to the "Sign in to check out" screen.

## Sign-in and register links

The buttons link to your routes, named in this package's config:

```php
'auth' => [
    'login_route'    => 'login',
    'register_route' => 'register',
    // …
],
```

A route name that doesn't exist hides its button. When a guest opens checkout, the storefront stores the checkout URL as Laravel's `url.intended`, so a login or registration that redirects with `redirect()->intended()` brings them back to checkout.

## Cart merge on sign-in

When the guest signs in, the engine merges their guest cart into their account cart (`cart.merge_on_login`, on by default). Quantities of the same line are added together, capped by stock. See "Merge on login" in the engine's `docs/checkout.md` for the rules.

When the two carts are in different currencies, the engine doesn't merge them on its own. The storefront's merge prompt (mounted once by `ecommerce-storefront::partials.global`) opens a modal with three choices:

| Choice | Result |
| --- | --- |
| Keep {guest currency} | The carts are combined in the guest cart's currency. |
| Switch to {account currency} | The carts are combined in the account cart's currency. |
| Don't merge | The account cart is left as it was. |

The choice is applied through the engine's `CurrentCart`. Lines are re-priced in the kept currency; a line with no price in it is dropped.

## Email capture

The contact step asks every shopper for an email address, prefilled from the cart or, for a signed-in shopper, their account. Saving the step validates it (required, a valid address, up to 254 characters) and saves it to the cart through `CheckoutService::setEmail()`, before any payment. That lets the engine follow up on carts left in checkout (`cart.abandoned_after_minutes`).

The same step has a marketing consent checkbox, unchecked by default. The choice is kept in the cart's `meta` (`storefront_marketing_consent`) until the order is placed. When the order is placed with the box ticked, the consent is recorded in two places:

- **On the order**, as the audit trail: `orders.meta.marketing_consent` holds `given` (true), `at` (an ISO 8601 time), `source` (`storefront-checkout`), `customer_id` (the customer whose preference was set), and `customer_created`.
- **On the customer**, as `accepts_marketing`, through the engine's `CustomerService::updateProfile()`. That is the order's customer or, for a guest, the customer for the order's email, created when there is none (`customer_created` is then true).

A guest checkout never changes the preferences of a customer linked to a user account: that shopper manages them while signed in. The consent is still recorded on the order.

This runs on the engine's `ap.ecommerce.checkout.finalized` action, so it also covers orders the provider's webhook placed. Unticked, nothing is written. A failure is reported and never holds up the order.

For a guest, the order's email is what order lookup and account claiming match against. See [Screens](Usage-Screens#orders-after-checkout).
