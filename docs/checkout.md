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
| Review | `review` | Checks the order, leaves an optional note (up to 2,000 characters), accepts the terms when a terms page is set, and optionally asks for an account. "Continue to payment" checks these and moves on. |
| Payment | `payment` | Picks a gateway and pays through its payment driver. A single gateway is picked for them. Confirming the payment places the order. When nothing is due, a "Place order" button places it instead. |

Satellites can add steps; see [Checkout Layouts](Checkout-Layouts#adding-a-step).

A shopper can open a step once the steps before it are done, judged from the cart and the engine's checkout state. Completed steps can be reopened until the payment is confirmed. On entry, checkout opens the furthest step the shopper can reach. The current step is in the query string (`?step=payment`), so browser back works.

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

Payment is the last step, so the shopper has already reviewed the order, left their note, accepted the terms, and asked for an account before they pay. Confirming the payment places the order.

1. **Review.** "Continue to payment" checks the note length, the terms, and the account password (see [Account Creation at Checkout](Checkout-Account-Creation)). What the shopper reviewed is kept in the session for this cart, with the password encrypted and never sent back to the browser. The order is placed with it, even after a provider's redirect.
2. **Payment session.** At the payment step, the chosen gateway creates (or reuses) a payment session through the engine's `createPaymentSession()`, with `return_url` set to `/shop/checkout/return`. The gateway's client config (`ClientPaymentConfig::for()`) names a driver, and the driver's Livewire component renders the payment UI. A gateway with no registered driver is hidden with "This payment method isn't available".
3. **Action token.** When the payment step renders, it mints a one-time action token and passes it to the driver as its `token` prop. The order is placed under that token and the `ecommerce.checkout.finalize` rate limit, so a repeated confirmation, a double click, or a retried request places one order.
4. **Driver confirms.** The shopper pays in the driver's UI. The driver reports `payment-confirmed` with the session reference and the token. The checkout checks the reference is the cart's session and asks the gateway whether it is confirmed (authorized, succeeded, or processing) for the cart's current total and currency. See [Payment Drivers](Checkout-Payment-Drivers).
5. **Finalize.** Every step a satellite added is checked again, then `ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder` fires with the cart and context, and the engine's `finalize( $cart, $reference, $context )` places the order. The context holds the reviewed note (sanitized), IP address, and user agent.
6. **Confirmation.** When the payment is captured, the account the shopper asked for is created and they are redirected to the confirmation page. Guests get a link signed with an order-view token. Without the storefront's routes, the checkout says the order is placed instead.

**Free orders.** When nothing is due, the payment step shows "Your order is free, so there's nothing to pay." and a **Place order** button, which places the order the same way, without a payment session.

**After the payment is confirmed**, the shopper can't go back to the contact, address, or shipping step, change the gateway, or set up another payment, so they can't be charged twice. A change to the cart elsewhere (the cart drawer) doesn't set up a new payment either. In each case the checkout says "Your payment is confirmed. Contact us to change this order." If placing the order fails after the payment was confirmed, the payment step shows "Your payment is confirmed. Place your order to finish." with a **Place order** button.

### When the provider's webhook places the order first

A provider can tell the engine about a successful payment before the checkout hears from the driver, and the engine's payment reconciler then places the order from the cart. The checkout still finds that order and carries on from it, rather than saying the cart is empty:

- a paid order goes to its confirmation, and the account the shopper asked for is created;
- the note the shopper reviewed is copied onto the order when the webhook placed it without one;
- an unpaid order shows its payment again, as below.

### When the payment doesn't go through

| Outcome | What happens |
| --- | --- |
| Captured | Confirmation, as above. |
| Held for review by fraud screening | The payment is confirmed, but the store has to approve the order. The shopper goes to the confirmation page with "We're reviewing your order and will email you shortly." |
| Challenged (the bank wants 3-D Secure) | The order is placed but not paid. The payment step shows again for the order's own payment session: "Your bank needs you to confirm this payment." Confirming it finishes the order. |
| Failed (declined) | As challenged, with the provider's message. |
| Blocked by fraud screening | A generic "We couldn't process your order. Please contact the store for help." The reason is never shown. |
| Refused before placing | The shopper goes back to the step that needs fixing, with the engine's message. A payment problem (including `totals-changed`) clears the confirmed payment, so they pay again. |

### Placed but unpaid

Placing the order turns the cart into the order, so after a challenged or failed payment there is no open cart left. `Support\CheckoutPlacement` remembers the order in the session (`ecommerce-storefront.checkout.placement`). When the shopper reloads checkout, or comes back from the provider:

- a paid order, or one held for the store's fraud review, goes to its confirmation;
- a payment confirmed on return finishes the order (the engine's `finalize()` is idempotent per cart and resumes the payment);
- otherwise the payment step shows again: "Your order … is placed, but its payment isn't finished. Pay below to complete it."

A cancelled, failed, or refunded order is forgotten.

### The return route

`/shop/checkout/return` (`artisanpack.ecommerce.storefront.checkout.return`) is where a redirect gateway, 3-D Secure, or a redirecting payment method sends the shopper. It asks the gateway about the cart's session. A `reference` (or Stripe's `payment_intent`) in the query string must match the cart's session.

- Confirmed for the current total: back to checkout at the payment step (`?step=payment`), which places the order with what the shopper reviewed.
- Not confirmed, a different total, or a mismatched reference: back to the payment step with a toast saying why.
- No session on the cart: back to checkout as it is.
- An order placed but unpaid: checked the same way against the order's session. A paid order, or one held for review, goes to its confirmation. This order wins over an open cart when the returned reference is its payment, so a shopper who has started a new cart can still finish it.

Each return counts against the shopper's `ecommerce.cart.mutate` limit, because it asks the provider about the session. Over the limit, the shopper goes back to the payment step with "Too many attempts. Try again in a moment."
