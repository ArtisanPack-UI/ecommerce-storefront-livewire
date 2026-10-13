---
title: Account Creation at Checkout
---

# Account Creation at Checkout

A guest can ask for an account at the review step, before they pay: a **Create an account with this email** checkbox, and a password field once it's ticked. The account is created after the order is paid, with the order's email.

## When it's offered

The checkbox shows when all of these hold:

- the shopper is a guest and not signed in;
- the engine's `checkout.account_creation` setting is on ("Offer account creation" on the admin's Checkout settings; config default `ECOMMERCE_CHECKOUT_ACCOUNT_CREATION`, on);
- `auth.create_account_action` names a class that implements `Contracts\CreatesCustomerAccounts`.

## What happens

1. **At "Continue to payment"** on the review step, with the box ticked, the checkout validates the password against the action's `passwordRules()` and checks `emailIsAvailable()` for the cart's email. A problem shows on the password field and the shopper stays on the review step, so they never hear about it only after paying. An email that already has an account gets "There's already an account for … Sign in to use it, or place the order without an account."
2. **The request is kept** in the session for this cart, with the password encrypted. The password is cleared from the component and never sent back to the browser. Only what the review step validated counts: changing the component's state afterwards has no effect.
3. **Once the payment is captured**, the checkout calls the action's `create( $order, $password )` with the kept password, then redirects to the confirmation page with a "Your account is ready" toast. This also happens when the provider's webhook placed the order first, or when the order was held for the store's fraud review.
4. **If `create()` throws**, the order still stands. The exception is reported, and the shopper sees "We couldn't create your account. Your order is placed. You can create an account later with …".

The password is also cleared when the shopper unticks the box.

## The default action

`Actions\CreateCustomerAccount`:

- creates an instance of `auth.providers.users.model` with `name`, `email`, and the hashed password. The name comes from the order's billing or shipping address, else the part of the email before the `@`;
- gets the user's engine customer with `CustomerService::customerForUser( $user, true )` (see [Existing customer records](#existing-customer-records));
- when there is one, attaches the order just placed to it, fires `ap.ecommerce.customer.orderClaimed`, and recalculates the customer's stats;
- fires Laravel's `Registered` event, so an app that verifies email sends its verification message;
- signs the user in and regenerates the session.

Its password rules are `required`, `string`, `max:255`, and `Password::default()`. `emailIsAvailable()` compares emails case-insensitively.

Only the order just placed is attached. Earlier guest orders under the same email still go through the account's claim page, which verifies the shopper.

### Existing customer records

The checkout email isn't verified, so an account created at checkout never takes over a customer record someone else built up under that email: saved addresses, orders, or preferences. `customerForUser( $user, true )`:

- creates a new customer for the user when no customer has the email yet;
- links the existing customer when the user's email is verified (the user model implements `MustVerifyEmail` and `hasVerifiedEmail()` is true);
- otherwise returns null.

When there is no customer, the user is still created and signed in, but the order stays a guest order. The engine links the existing customer once the user verifies their email.

There is one exception. When the shopper ticked marketing consent and had no customer record, recording that consent creates one for the order's email (see [Guest Checkout](Checkout-Guest-Checkout#email-capture)). That record holds nothing else, so the default action links it to the new user.

## The contract

```php
namespace ArtisanPackUI\EcommerceStorefrontLivewire\Contracts;

interface CreatesCustomerAccounts
{
    public function passwordRules(): array;

    public function emailIsAvailable( string $email ): bool;

    public function create( Order $order, string $password ): Authenticatable;
}
```

| Method | Called | Must |
| --- | --- | --- |
| `passwordRules()` | Before placing the order | Return Laravel validation rules for the password. |
| `emailIsAvailable( $email )` | Before placing the order | Return false when a user already has the email. |
| `create( $order, $password )` | After the payment is captured | Create the user, link an engine customer and the order to them where that is safe, sign them in, and return the user. Throw on failure. |

The action is resolved from the container, so constructor dependencies are injected.

## Writing your own action

Swap the action when registration needs more: a username, extra consent, another user model. Extending the default keeps its linking and sign-in:

```php
<?php

declare( strict_types=1 );

namespace App\Actions;

use App\Models\User;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceStorefrontLivewire\Actions\CreateCustomerAccount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateShopAccount extends CreateCustomerAccount
{
    public function passwordRules(): array
    {
        return [ 'required', 'string', 'max:255', Password::min( 12 )->mixedCase()->numbers() ];
    }

    public function create( Order $order, string $password ): Authenticatable
    {
        $user = parent::create( $order, $password );

        if ( $user instanceof User ) {
            $user->forceFill( [ 'username' => Str::before( (string) $order->email, '@' ) . '-' . $user->getKey() ] )->save();
        }

        return $user;
    }
}
```

Or implement the contract yourself, for example to create the user in an external directory:

```php
<?php

declare( strict_types=1 );

namespace App\Actions;

use App\Models\User;
use App\Services\Directory;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Contracts\CreatesCustomerAccounts;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class CreateDirectoryAccount implements CreatesCustomerAccounts
{
    public function __construct(
        protected Directory $directory,
        protected CustomerService $customers,
    ) {
    }

    public function passwordRules(): array
    {
        return [ 'required', 'string', Password::default() ];
    }

    public function emailIsAvailable( string $email ): bool
    {
        return ! $this->directory->hasUser( $email );
    }

    public function create( Order $order, string $password ): Authenticatable
    {
        $user = $this->directory->register( (string) $order->email, $password ); // Throws on failure.

        // Never hands an existing customer record to an unverified email.
        $customer = $this->customers->customerForUser( $user, true );

        if ( null !== $customer ) {
            Order::query()->whereKey( $order->getKey() )->whereNull( 'customer_id' )->update( [ 'customer_id' => $customer->id, 'is_claimed' => true ] );
        }

        Auth::login( $user );
        session()->regenerate();

        return $user;
    }
}
```

`Directory` here stands for your own service. Register the class in `config/artisanpack/ecommerce-storefront-livewire.php`:

```php
'auth' => [
    // …
    'create_account_action' => App\Actions\CreateShopAccount::class,
],
```

A class that doesn't implement the contract (or an empty value) turns the checkbox off.

## Handling failures

Throw from `create()` when the account can't be made. The checkout catches any `Throwable`, reports it, and tells the shopper their order is placed but the account wasn't created. Don't throw for problems you can check up front: put them in `passwordRules()` or `emailIsAvailable()` so the shopper can fix them before paying.

The package's tests use an action whose `create()` always fails, to check that the order still goes through:

```php
class FailingCreateCustomerAccount extends CreateCustomerAccount
{
    public function create( Order $order, string $password ): Authenticatable
    {
        throw new RuntimeException( 'The user directory is down.' );
    }
}
```
