---
title: Checkout Overview
---

# Checkout

The checkout is one Livewire component, `<livewire:artisanpack-ecommerce-storefront-checkout />`, on the engine's `CheckoutService` (see the engine's `docs/checkout.md`). It renders at `/shop/checkout` (route `artisanpack.ecommerce.storefront.checkout`). The page is never cached or indexed.

## In this section

- [Checkout Layouts](Checkout-Layouts) - Multi-step or single page, the terms checkbox, and adding your own steps
- [Guest Checkout](Checkout-Guest-Checkout) - The guest checkout policy, sign-in links, and cart merging
- [Account Creation at Checkout](Checkout-Account-Creation) - The optional "create an account" and swapping the action that does it
- [Payment Drivers](Checkout-Payment-Drivers) - How gateways render their payment UI, writing a driver, and testing with Stripe

## Steps

| Step | Key | What the shopper does |
| --- | --- | --- |
| Contact | `contact` | Gives an email and, optionally, marketing consent (unchecked by default). The email is saved to the cart straight away, so the engine can follow up on an abandoned cart. |
| Address | `address` | Gives a shipping address and a billing address (or "same as shipping"). A cart that ships nothing asks for a billing address only, and the step is labelled "Billing address". Signed-in shoppers can pick a saved address and save a new one to their address book. |
| Shipping | `shipping` | Picks a rate from the engine's quote. Skipped for carts that ship nothing. |
| Payment | `payment` | Picks a gateway and pays through its payment driver. A single gateway is picked for them. Skipped with a "Continue" button when nothing is due. |
| Review | `review` | Checks the order, leaves an optional note (up to 2,000 characters), accepts the terms when a terms page is set, optionally creates an account, and places the order. |

Satellites can add steps; see [Checkout Layouts](Checkout-Layouts#adding-a-step).

A shopper can open a step once the steps before it are done, judged from the cart and the engine's checkout state. Completed steps can be reopened. On entry, checkout opens the furthest step the shopper can reach. The current step is in the query string (`?step=payment`), so browser back works.

Shipping rates are quoted again when the cart's lines or address change. A chosen rate the engine no longer offers is cleared, and the shopper is asked to choose again. A payment made for an old total no longer counts, so a cart change at the payment step creates a new payment session.

Engine refusals, including an `ap.ecommerce.checkout.canTransitionTo` listener blocking a step, show their message on the field they name and in an error summary.

## Entry guards

When checkout opens (and whenever the cart changes in the cart drawer):

- **Empty cart.** Back to the cart page with "Your cart is empty". Free lines a promotion added don't count.
- **Lines that can't be bought.** Back to the cart page, which marks them, with "Some items can't be bought".
- **Stock reservations.** The engine's `start()` holds the cart's stock for the engine's `checkout.reservation_ttl_minutes`. A line it had to reduce or remove is listed above the first step ("Only 2 of … are in stock, so we've changed the quantity in your cart."). If nothing is left, the shopper goes back to the cart.
- **Guests.** The engine's `checkout.guest_checkout` setting decides whether a guest can continue. See [Guest Checkout](Checkout-Guest-Checkout).

Without the storefront's cart route (routes off), the reason shows in place of the checkout instead of redirecting.

## Placing the order

1. **Payment session.** At the payment step, the chosen gateway creates (or reuses) a payment session through the engine's `createPaymentSession()`, with `return_url` set to `/shop/checkout/return`. The gateway's client config (`ClientPaymentConfig::for()`) names a driver, and the driver's Livewire component renders the payment UI. A gateway with no registered driver is hidden with "This payment method isn't available".
2. **Driver confirms.** The shopper pays in the driver's UI. The driver reports `payment-confirmed` with the session reference. The checkout checks the reference is the cart's session and asks the gateway whether it is confirmed (authorized, succeeded, or processing) for the cart's current total and currency. Only then does it move on to review. See [Payment Drivers](Checkout-Payment-Drivers).
3. **Action token.** The "Place order" button carries a one-time action token. The order is placed under it and the `ecommerce.checkout.finalize` rate limit, so a double click or a retried request places one order.
4. **Finalize.** The review form is checked (note length, terms, account password), then `ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder` fires with the cart and context, and the engine's `finalize( $cart, $reference, $context )` places the order. The context holds the note (sanitized), IP address, and user agent.
5. **Confirmation.** When the payment is captured, the account the shopper asked for is created and they are redirected to the confirmation page. Guests get a link signed with an order-view token. Without the storefront's routes, the checkout says the order is placed instead.

### When the payment doesn't go through

| Outcome | What happens |
| --- | --- |
| Captured | Confirmation, as above. |
| Challenged (the bank wants 3-D Secure) | The order is placed but not paid. The payment step shows again for the order's own payment session: "Your bank needs you to confirm this payment." Confirming it finishes the order. |
| Failed (declined) | As challenged, with the provider's message. |
| Blocked by fraud screening | A generic "We couldn't process your order. Please contact the store for help." The reason is never shown. |
| Refused before placing | The shopper goes back to the step that needs fixing, with the engine's message. A payment problem (including `totals-changed`) clears the confirmed payment, so they pay again. |

### Placed but unpaid

Placing the order turns the cart into the order, so after a challenged or failed payment there is no open cart left. `Support\CheckoutPlacement` remembers the order in the session (`ecommerce-storefront.checkout.placement`). When the shopper reloads checkout, or comes back from the provider:

- a paid order goes to its confirmation;
- a payment confirmed on return finishes the order (the engine's `finalize()` is idempotent per cart and resumes the payment);
- otherwise the payment step shows again: "Your order … is placed, but its payment isn't finished. Pay below to complete it."

A cancelled, failed, or refunded order is forgotten.

### The return route

`/shop/checkout/return` (`artisanpack.ecommerce.storefront.checkout.return`) is where a redirect gateway, 3-D Secure, or a redirecting payment method sends the shopper. It asks the gateway about the cart's session. A `reference` (or Stripe's `payment_intent`) in the query string must match the cart's session.

- Confirmed for the current total: back to checkout at the review step.
- Not confirmed, a different total, or a mismatched reference: back to the payment step with a toast saying why.
- No session on the cart: back to checkout as it is.
- An order placed but unpaid: checked the same way against the order's session.
