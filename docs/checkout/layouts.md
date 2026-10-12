---
title: Checkout Layouts
---

# Checkout Layouts

The checkout comes in two layouts. Both use the same step partials, and the order summary collapses on small screens.

| Layout | Value | What the shopper sees |
| --- | --- | --- |
| Multi-step | `multi_step` (default) | A progress bar, summaries of the completed steps, and the open step. |
| Single page | `single_page` | Every section at once. Sections after an unfinished step are disabled and say why. |

## Choosing the layout

`Support\CheckoutLayout::current()` reads the engine setting `storefront.checkout_layout`. When no value is stored, it falls back to this package's config `checkout.layout`:

```php
// config/artisanpack/ecommerce-storefront-livewire.php
'checkout' => [
    'layout'    => 'single_page',
    'terms_url' => null,
],
```

The storefront adds `storefront.checkout_layout` to the engine's "Checkout" settings group, so store owners can change it on the admin's settings screen ("Checkout layout"). Any value other than `single_page` means multi-step.

### The Checkout Steps block

With `artisanpack-ui/visual-editor` installed, the `artisanpack-commerce/checkout-steps` block renders the checkout. Its **Layout** setting is "Store setting" (follow `storefront.checkout_layout`), "Multi-step", or "Single page". A layout chosen on the block wins over the store setting for that block. It reaches the component as the `layoutOverride` prop:

```blade
<livewire:artisanpack-ecommerce-storefront-checkout layout-override="single_page" />
```

See [Blocks](Visual-Editor-Blocks).

## Terms and conditions

Set a terms page and the review step shows a required "I accept the terms and conditions" checkbox. Placing the order without it fails with "Accept the terms and conditions to place your order."

The page comes from the engine setting `storefront.terms_url` ("Terms and conditions page" in the admin's Checkout settings), falling back to config `checkout.terms_url`. It must be an `http(s)` URL or a path on this site (`/terms`). Anything else, including `//host`, is ignored, and no checkbox is shown. `null` or blank shows no checkbox.

## Adding a step

Satellites and hosts add steps with the `ap.ecommerceStorefrontLivewire.checkout.steps` filter. It receives the steps (key => definition) and the cart:

```php
use ArtisanPackUI\Ecommerce\Models\Cart;

addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', function ( array $steps, Cart $cart ): array {
    $steps['age-check'] = [
        'label'     => __( 'Date of birth' ),
        'component' => 'age-check-checkout-step',
        'before'    => 'payment',
    ];

    return $steps;
}, 10, 2 );
```

| Key | Meaning |
| --- | --- |
| Array key | The step key. Lower-case letters, numbers, `-` and `_`, up to 64 characters, starting with a letter or number. |
| `label` | The step's heading. Required. |
| `component` | A registered Livewire component. Required. |
| `before` | `address`, `shipping`, `payment`, or `review` (default). When the cart doesn't ship, `shipping` means the next core step. |

Rules:

- Core steps (`contact`, `address`, `shipping`, `payment`, `review`) can't be removed, relabelled, or reordered. Changes to them are ignored.
- Added steps before the same core step keep the order they were added in.
- An entry without a label or component, or with an invalid key, is ignored.

### Writing the step component

The checkout mounts your component with a `step` prop (its key). When the shopper has finished it, dispatch `ecommerce-checkout-step-completed` with `step`, and the checkout moves on. Until then, later steps stay locked.

That event comes from the browser, so it can't be trusted on its own. A step that must hold the shopper back also refuses the engine's next checkout state through `ap.ecommerce.checkout.canTransitionTo`. The checkout shows the refusal as an error.

```php
<?php

declare( strict_types=1 );

namespace App\Livewire;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

class AgeCheckStep extends Component
{
    public string $step = '';

    public string $dateOfBirth = '';

    public function confirmAge( StorefrontCart $carts ): void
    {
        $this->validate( [ 'dateOfBirth' => [ 'required', 'date', 'before_or_equal:' . now()->subYears( 18 )->toDateString() ] ], [
            'dateOfBirth.before_or_equal' => __( 'You must be 18 or older to buy these products.' ),
        ] );

        $cart = $carts->current();

        if ( null === $cart ) {
            return;
        }

        $cart->meta = [ ...(array) ( $cart->meta ?? [] ), 'age_verified_at' => Carbon::now()->toIso8601String() ];
        $cart->save();

        $this->dispatch( 'ecommerce-checkout-step-completed', step: $this->step );
    }

    public function render(): View
    {
        return view( 'livewire.age-check-step' );
    }
}
```

```blade
<form wire:submit="confirmAge" class="flex flex-col gap-4">
    <x-artisanpack-input type="date" :label="__( 'Date of birth' )" wire:model="dateOfBirth" />

    <div>
        <x-artisanpack-button type="submit" :label="__( 'Continue' )" color="primary" spinner="confirmAge" />
    </div>
</form>
```

Register the component, the step, and the server-side check in a service provider's `boot()`:

```php
use App\Livewire\AgeCheckStep;
use ArtisanPackUI\Ecommerce\Checkout\CheckoutState;
use ArtisanPackUI\Ecommerce\Models\Cart;
use Livewire\Livewire;

Livewire::component( 'age-check-checkout-step', AgeCheckStep::class );

addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', function ( array $steps, Cart $cart ): array {
    $steps['age-check'] = [
        'label'     => __( 'Date of birth' ),
        'component' => 'age-check-checkout-step',
        'before'    => 'payment',
    ];

    return $steps;
}, 10, 2 );

// Starting a payment session moves the cart to `payment_pending`; refuse it until the age is checked.
addFilter( 'ap.ecommerce.checkout.canTransitionTo', function ( mixed $allowed, Cart $cart, string $to ): mixed {
    if ( CheckoutState::PAYMENT_PENDING === $to && empty( ( (array) ( $cart->meta ?? [] ) )['age_verified_at'] ) ) {
        return false;
    }

    return $allowed;
}, 10, 3 );
```

The engine's checkout states are listed in its `docs/checkout.md` ("Checkout states"). See [Hooks Reference](Extending-Hooks-Reference) for every checkout hook.
