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

Set a terms page and the review step shows a required "I accept the terms and conditions" checkbox. It is checked when the shopper presses "Continue to payment", before they pay. Without it, the review step stays open with "Accept the terms and conditions to place your order."

The page comes from the engine setting `storefront.terms_url` ("Terms and conditions page" in the admin's Checkout settings), falling back to config `checkout.terms_url`. It must be an `http(s)` URL or a path on this site (`/terms`). Anything else, including `//host`, is ignored, and no checkbox is shown. `null` or blank shows no checkbox.

## Adding a step

Satellites and hosts add steps with the `ap.ecommerceStorefrontLivewire.checkout.steps` filter. It receives the steps (key => definition) and the cart:

```php
use App\Support\AgeChecks;
use ArtisanPackUI\Ecommerce\Models\Cart;

addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', function ( array $steps, Cart $cart ): array {
    $steps['age-check'] = [
        'label'     => __( 'Date of birth' ),
        'component' => 'age-check-checkout-step',
        'before'    => 'review',
        'complete'  => fn ( Cart $cart ): bool => AgeChecks::passed( $cart ),
    ];

    return $steps;
} );
```

| Key | Meaning |
| --- | --- |
| Array key | The step key. Lower-case letters, numbers, `-` and `_`, up to 64 characters, starting with a letter or number. |
| `label` | The step's heading. Required. |
| `component` | A registered Livewire component. Required. |
| `before` | `address`, `shipping`, `review` (default), or `payment`. When the cart doesn't ship, `shipping` means the next core step (review). |
| `complete` | Optional. A callable that takes the cart and returns whether the step is done, checked on the server. See [Writing the step component](#writing-the-step-component). |

Rules:

- The core steps are `contact`, `address`, `shipping` (only for carts that ship), `review`, and `payment`, in that order. They can't be removed, relabelled, or reordered. Changes to them are ignored.
- Added steps before the same core step keep the order they were added in.
- An entry without a label or component, with an invalid key, or with a `complete` that isn't callable, is ignored.

### Writing the step component

The checkout mounts your component with a `step` prop (its key). When the shopper has finished it, dispatch `ecommerce-checkout-step-completed` with `step`, and the checkout moves on. Until then, later steps stay locked. The checkout ignores the event for a step the shopper can't reach yet.

That event comes from the browser, so it can't be trusted on its own. A step that must hold the shopper back gives a `complete` callable. The checkout then asks the callable, on the server, whether the step is done, and the event alone no longer counts. Just before the order is placed, every added step is checked again: a step that isn't done stops the order with "Finish the steps above before placing your order." A step without a `complete` callable counts as done once it reports done.

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

Register the component and the step, with its server-side check, in a service provider's `boot()`:

```php
use App\Livewire\AgeCheckStep;
use ArtisanPackUI\Ecommerce\Models\Cart;
use Livewire\Livewire;

Livewire::component( 'age-check-checkout-step', AgeCheckStep::class );

addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', function ( array $steps, Cart $cart ): array {
    $steps['age-check'] = [
        'label'     => __( 'Date of birth' ),
        'component' => 'age-check-checkout-step',
        'before'    => 'review',
        'complete'  => fn ( Cart $cart ): bool => ! empty( ( (array) ( $cart->meta ?? [] ) )['age_verified_at'] ),
    ];

    return $steps;
} );
```

To hold back orders placed outside this checkout too, also refuse the engine's checkout states through `ap.ecommerce.checkout.canTransitionTo`. The checkout shows that refusal as an error.

The engine's checkout states are listed in its `docs/checkout.md` ("Checkout states"). See [Hooks Reference](Extending-Hooks-Reference) for every checkout hook.
