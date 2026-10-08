<?php

/**
 * Checkout component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout;

use ArtisanPackUI\Ecommerce\Checkout\CheckoutState;
use ArtisanPackUI\Ecommerce\Contracts\PaymentGateway;
use ArtisanPackUI\Ecommerce\Exceptions\CartOperationException;
use ArtisanPackUI\Ecommerce\Exceptions\CheckoutException;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\CartItem;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\Services\CheckoutService;
use ArtisanPackUI\Ecommerce\Services\CustomerAddressService;
use ArtisanPackUI\Ecommerce\Support\ClientPaymentConfig;
use ArtisanPackUI\Ecommerce\ValueObjects\Address;
use ArtisanPackUI\Ecommerce\ValueObjects\CheckoutResult;
use ArtisanPackUI\Ecommerce\ValueObjects\PaymentFinalization;
use ArtisanPackUI\Ecommerce\ValueObjects\ShippingRate;
use ArtisanPackUI\EcommerceStorefrontLivewire\Contracts\CreatesCustomerAccounts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\DescribesCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\PaymentDriverRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\AddressFormats;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutLayout;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutPlacement;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutSteps;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\Countries;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\PaymentSessions;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\AddressForm;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-checkout />`
 *
 * The checkout (spec §7.4, S17–S21), on the engine's `CheckoutService`.
 *
 * **Entry.** An empty cart goes back to the cart; so does one with lines
 * that can't be bought (the cart page says why). `start()` then holds the
 * stock, and any line it had to reduce or remove is listed above the
 * first step. Guests follow `checkout.guest_checkout`: `allowed` (check
 * out as a guest or sign in), `required_account` (fill in checkout, then
 * sign in or register before paying), or `disabled` (sign in first). The
 * sign-in and register links go to the host's routes
 * (`auth.login_route`, `auth.register_route`) and come back to checkout.
 *
 * **Steps** ({@see CheckoutSteps}): contact (email, saved to the cart for
 * abandoned-cart follow-up, and marketing consent, unchecked), address
 * (shipping, or billing only for carts that don't ship; saved addresses
 * for signed-in shoppers), shipping (rates re-quoted when the lines or
 * address change), any step a satellite adds, payment (gateway choice and
 * the gateway's payment driver, {@see PaymentDriverRegistry}), and
 * review. The shopper reaches a step once the engine's checkout state
 * says the steps before it are done; completed steps can be reopened.
 * The current step is in the query string, so browser back works.
 *
 * **Layout** ({@see CheckoutLayout}, the store owner's choice): multi-step
 * shows a progress bar (`x-artisanpack-steps`), the completed steps'
 * summaries, and the open step; single page shows every section, with the
 * ones after an unfinished step disabled and saying why. Both use the same
 * step partials, and the order summary collapses on small screens.
 *
 * **Placing the order** (S22). The review step shows what the shopper is
 * buying, takes an optional note (sanitized), the terms checkbox when a
 * terms page is set, and — for guests, when the store offers it — a
 * password to create an account. "Place order" carries a one-time action
 * token ({@see WithActionToken}) and runs under the
 * `ecommerce.checkout.finalize` limit; it fires
 * `ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder` and calls the
 * engine's `finalize()`, so a double click places one order. Then:
 *
 * - captured — the account is created when asked
 *   (`auth.create_account_action`, {@see CreatesCustomerAccounts}) and the
 *   shopper goes to the confirmation page (signed for guests);
 * - challenged or failed — the order exists but isn't paid: the payment
 *   driver is shown again for the order's payment ({@see CheckoutPlacement}
 *   keeps it across reloads and the provider's redirect), and confirming it
 *   finishes the order;
 * - blocked by fraud screening — a generic message.
 *
 * Every engine refusal — including an `ap.ecommerce.checkout.canTransitionTo`
 * listener blocking a step — is shown with its message.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use DescribesCart;
    use InteractsWithStorefrontCart;
    use RateLimitsStorefront;
    use SendsToasts;
    use WithActionToken;

    /**
     * The longest order note a shopper can leave, in characters.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const NOTE_MAX_LENGTH = 2_000;

    /**
     * Where the return route leaves a payment it found confirmed, for the
     * checkout to pick up: `reference` and `total`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CONFIRMED_PAYMENT_SESSION_KEY = 'ecommerce-storefront.checkout.confirmed-payment';

    /**
     * The `carts.meta` key holding the shopper's marketing consent until
     * the order is placed.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MARKETING_CONSENT_META_KEY = 'storefront_marketing_consent';

    /**
     * The step on screen. Empty until mount picks the furthest step the
     * shopper can reach (or the one in the query string).
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( as: 'step', history: true, except: '' )]
    public string $step = '';

    /**
     * The shopper's email.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $email = '';

    /**
     * Whether the shopper wants marketing email.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $marketingConsent = false;

    /**
     * The shipping address fields ({@see AddressForm::FIELDS}).
     *
     * @since 1.0.0
     *
     * @var array<string, string|null>
     */
    public array $shipping = [];

    /**
     * The billing address fields.
     *
     * @since 1.0.0
     *
     * @var array<string, string|null>
     */
    public array $billing = [];

    /**
     * Whether billing is the shipping address.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $billingSameAsShipping = true;

    /**
     * The saved address chosen for shipping (an id, or `new`).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $savedShipping = 'new';

    /**
     * The saved address chosen for billing (an id, or `new`).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $savedBilling = 'new';

    /**
     * Whether a new address goes into the shopper's address book.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $saveToAddressBook = false;

    /**
     * The chosen shipping rate id.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $shippingRate = '';

    /**
     * The quoted rates: `id`, `label`, `amount`, `currency`, `estimate`.
     * Null until quoted.
     *
     * @since 1.0.0
     *
     * @var array<int, array{id: string, label: string, amount: int, currency: string, estimate: string|null}>|null
     */
    #[Locked]
    public ?array $rates = null;

    /**
     * Why the shopper has to choose a shipping rate again.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $shippingNotice = null;

    /**
     * The chosen gateway key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $gateway = '';

    /**
     * The payment session being paid: `gateway`, `label`, `reference`,
     * `component` (the driver's), and `config` (the gateway's client
     * config).
     *
     * @since 1.0.0
     *
     * @var array{gateway: string, label: string, reference: string, component: string, config: array<string, mixed>}|null
     */
    #[Locked]
    public ?array $payment = null;

    /**
     * Gateways hidden because their payment UI can't be shown.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $hiddenGateways = [];

    /**
     * The payment the driver confirmed: `reference` and `total` (the cart
     * total it was for).
     *
     * @since 1.0.0
     *
     * @var array{reference: string, total: int}|null
     */
    #[Locked]
    public ?array $confirmedPayment = null;

    /**
     * Satellite steps the shopper completed.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $completedSteps = [];

    /**
     * What `start()` had to change: one message per reduced or removed
     * line.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $adjustments = [];

    /**
     * Set when a guest must sign in before checking out.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $signInRequired = false;

    /**
     * Why checkout can't be shown, when the cart can't check out and there
     * is no cart page to go back to.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $unavailable = null;

    /**
     * Whether the current step's heading takes focus (after a step change).
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $focusStep = false;

    /**
     * Bumped after each failed submit, so the error summary is re-created
     * and takes focus.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $errorSummary = 0;

    /**
     * The polite live-region message (step changes, new totals).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $announcement = '';

    /**
     * The shopper's note for the store.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $orderNote = '';

    /**
     * Whether the shopper accepted the terms and conditions.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $acceptTerms = false;

    /**
     * Whether a guest wants an account made with the order.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $createAccount = false;

    /**
     * The password for the account a guest asked for.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $password = '';

    /**
     * The order placed whose payment isn't finished (challenged or
     * failed): `cart`, `order`, and `number`. Null otherwise.
     *
     * @since 1.0.0
     *
     * @var array{cart: int, order: int, number: string}|null
     */
    #[Locked]
    public ?array $placement = null;

    /**
     * What happened to the placed order's payment, shown above the payment
     * UI.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $paymentProblem = null;

    /**
     * The order number, once the order is placed and there is no
     * confirmation page to go to.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $placedOrder = null;

    /**
     * The layout: `multi_step` or `single_page` ({@see CheckoutLayout}).
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $layout = CheckoutLayout::MULTI_STEP;

    /**
     * The open step's number, for the multi-step progress bar.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public int $progress = 1;

    /**
     * Checks the cart can check out, starts checkout, and opens the
     * furthest step the shopper can reach (or the one in the query
     * string, when they can reach it). When the shopper has nothing in an
     * open cart, an order placed earlier whose payment isn't finished is
     * picked up instead.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->layout = CheckoutLayout::current();

        $cart = $this->cart();

        if ( ( null === $cart || [] === $this->paidLines( $cart ) ) && $this->resumePlacement() ) {
            return;
        }

        if ( ! $this->guard( $cart ) ) {
            return;
        }

        $this->rememberCheckoutAsIntended( $cart );

        if ( $this->isGuest( $cart ) && CheckoutService::GUESTS_DISABLED === $this->checkout()->guestCheckout() ) {
            $this->signInRequired = true;

            return;
        }

        if ( ! $this->begin( $cart, true ) ) {
            return;
        }

        $cart = $this->cart();

        $this->fillFromCart( $cart );
        $this->acceptReturnedPayment( $cart );

        $this->step = $this->canVisit( $this->step, $cart ) ? $this->step : $this->furthestStep( $cart );

        $this->enterStep( $cart );
    }

    /**
     * Follows changes made to the cart elsewhere (the cart drawer): checks
     * the cart can still check out, holds the stock again, re-quotes
     * shipping, and steps back when a step is no longer done.
     *
     * @since 1.0.0
     *
     * @return void
     */
    #[On( 'ecommerce-cart-updated' )]
    public function cartUpdated(): void
    {
        if ( $this->signInRequired || null !== $this->unavailable || null !== $this->placement ) {
            return;
        }

        $cart = $this->cart()?->refresh();

        if ( ! $this->guard( $cart ) || ! $this->begin( $cart, false ) ) {
            return;
        }

        $cart = $this->cart();

        if ( null !== $this->rates ) {
            $this->quoteRates( $cart );
        }

        if ( ! $this->canVisit( $this->step, $cart ) ) {
            $this->moveTo( $this->furthestStep( $cart ), $cart );
        } elseif ( 'payment' === $this->step ) {
            // A session made for the old total can't pay for the new one.
            $this->preparePayment( $cart );
        }
    }

    /**
     * Opens a step the shopper can reach.
     *
     * @since 1.0.0
     *
     * @param  string  $step  Step key.
     *
     * @return void
     */
    public function goTo( string $step ): void
    {
        $cart = $this->signInRequired || null !== $this->placement ? null : $this->openCart();

        if ( null === $cart || ! $this->canVisit( $step, $cart ) ) {
            return;
        }

        $this->resetErrorBag();
        $this->moveTo( $step, $cart );
    }

    /**
     * Saves the contact step: the email (to the cart) and marketing
     * consent.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveContact(): void
    {
        $this->resetErrorBag();

        $cart = $this->openCart();

        if ( null === $cart ) {
            return;
        }

        $email     = trim( $this->email );
        $validator = Validator::make( [ 'email' => $email ], [ 'email' => [ 'required', 'string', 'email', 'max:254' ] ], [
            'email.required' => __( 'Enter your email address.' ),
            'email.email'    => __( 'Enter a valid email address.' ),
            'email.max'      => __( 'Enter a valid email address.' ),
            'email.string'   => __( 'Enter a valid email address.' ),
        ] );

        if ( $validator->fails() ) {
            $this->addError( 'email', (string) $validator->errors()->first( 'email' ) );
            $this->errorSummary++;

            return;
        }

        if ( ! $this->attempt( fn () => $this->checkout()->setEmail( $cart, $email ), 'email' ) ) {
            return;
        }

        $this->email = $email;
        $this->storeMarketingConsent( $cart );
        $this->completeStep( 'contact' );
    }

    /**
     * Fills the shipping form from a saved address, or clears it for a new
     * one.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedSavedShipping(): void
    {
        $this->shipping = $this->addressFromBook( $this->savedShipping ) ?? $this->blankAddress();
        $this->resetErrorBag();
    }

    /**
     * Fills the billing form from a saved address, or clears it for a new
     * one.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedSavedBilling(): void
    {
        $this->billing = $this->addressFromBook( $this->savedBilling ) ?? $this->blankAddress();
        $this->resetErrorBag();
    }

    /**
     * Clears the region when the shipping country changes, and edits to a
     * saved address make it a new one.
     *
     * @since 1.0.0
     *
     * @param  mixed        $value  The new value.
     * @param  string|null  $field  The changed field (null when the whole address was set).
     *
     * @return void
     */
    public function updatedShipping( mixed $value, ?string $field = null ): void
    {
        $this->shipping      = $this->addressChanged( $this->shipping, $field );
        $this->savedShipping = 'new';
    }

    /**
     * Clears the region when the billing country changes, and edits to a
     * saved address make it a new one.
     *
     * @since 1.0.0
     *
     * @param  mixed        $value  The new value.
     * @param  string|null  $field  The changed field (null when the whole address was set).
     *
     * @return void
     */
    public function updatedBilling( mixed $value, ?string $field = null ): void
    {
        $this->billing      = $this->addressChanged( $this->billing, $field );
        $this->savedBilling = 'new';
    }

    /**
     * Saves the address step: validates the addresses, sends them to the
     * engine (which re-quotes a chosen shipping rate), and saves a new
     * address to the address book when asked.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveAddress(): void
    {
        $this->resetErrorBag();

        $cart = $this->openCart();

        if ( null === $cart ) {
            return;
        }

        $ships       = $this->requiresShipping( $cart );
        $needBilling = ! $ships || ! $this->billingSameAsShipping;
        $errors      = [
            ...( $ships ? $this->addressErrors( $this->shipping, 'shipping' ) : [] ),
            ...( $needBilling ? $this->addressErrors( $this->billing, 'billing' ) : [] ),
        ];

        if ( [] !== $errors ) {
            foreach ( $errors as $field => $message ) {
                $this->addError( $field, $message );
            }

            $this->errorSummary++;

            return;
        }

        $shipping = $ships ? $this->toAddress( $this->shipping ) : null;
        $billing  = $needBilling ? $this->toAddress( $this->billing ) : null;
        $hadRate  = null !== $this->chosenRate( $cart );

        if ( ! $this->attempt( fn () => $this->checkout()->setAddress( $cart, $shipping, $billing ), 'address' ) ) {
            return;
        }

        $cart = $this->cart();

        if ( $hadRate && null === $this->chosenRate( $cart ) ) {
            $this->shippingRate   = '';
            $this->shippingNotice = __( 'Your shipping choice isn\'t available for this address. Choose a shipping option.' );
        }

        $this->rates = null;

        $this->saveToBook( $ships ? $this->shipping : $this->billing, $ships ? $this->savedShipping : $this->savedBilling );
        $this->completeStep( 'address' );
    }

    /**
     * Chooses the shipping rate as soon as the shopper picks it, so the
     * totals show it.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedShippingRate(): void
    {
        $this->resetErrorBag( 'shippingRate' );

        $cart = $this->openCart();

        if ( null === $cart || ! in_array( $this->shippingRate, array_column( $this->rates ?? [], 'id' ), true ) ) {
            return;
        }

        $chosen = $this->rateLimited( 'ecommerce.cart.mutate', fn (): bool => $this->attempt( fn () => $this->checkout()->setShippingMethod( $cart, $this->shippingRate ), 'shippingRate' ) );

        if ( true !== $chosen ) {
            if ( ! $this->wasThrottled() ) {
                $this->shippingRate = '';
                $this->quoteRates( $cart->refresh() );
            }

            return;
        }

        $this->shippingNotice = null;
        $this->cartChanged( $this->cart() );
        $this->announceTotal( __( 'Shipping updated.' ) );
    }

    /**
     * Saves the shipping step.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveShipping(): void
    {
        $this->resetErrorBag();

        $cart = $this->openCart();

        if ( null === $cart ) {
            return;
        }

        if ( ! in_array( $this->shippingRate, array_column( $this->rates ?? [], 'id' ), true ) ) {
            $this->addError( 'shippingRate', __( 'Choose a shipping option.' ) );
            $this->errorSummary++;

            return;
        }

        if ( $this->shippingRate !== (string) ( $this->chosenRate( $cart )['id'] ?? '' ) ) {
            if ( ! $this->attempt( fn () => $this->checkout()->setShippingMethod( $cart, $this->shippingRate ), 'shippingRate' ) ) {
                $this->quoteRates( $this->cart() );

                return;
            }
        }

        $this->shippingNotice = null;
        $this->completeStep( 'shipping' );
    }

    /**
     * Sets up the payment with the gateway the shopper picked.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedGateway(): void
    {
        $this->resetErrorBag( 'gateway' );

        $cart = $this->openCart();

        if ( null !== $cart ) {
            $this->startPayment( $cart );
        }
    }

    /**
     * Moves past the payment step for an order with nothing to pay.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function continueWithoutPayment(): void
    {
        $cart = $this->openCart();

        if ( null !== $cart && (int) $cart->total_amount <= 0 ) {
            $this->completeStep( 'payment' );
        }
    }

    /**
     * Moves on once the payment driver reports the payment confirmed, it
     * matches the cart's session, and the gateway agrees.
     *
     * @since 1.0.0
     *
     * @param  string  $reference  The confirmed session.
     *
     * @return void
     */
    #[On( 'payment-confirmed' )]
    public function paymentConfirmed( string $reference = '' ): void
    {
        if ( null !== $this->placement ) {
            $this->placementPaymentConfirmed( $reference );

            return;
        }

        $cart = $this->openCart();

        if ( null === $cart ) {
            return;
        }

        $current = (string) ( $cart->payment_reference ?? '' );

        if ( '' === $current || '' === $reference || ! hash_equals( $current, $reference ) ) {
            $this->addError( 'gateway', __( 'That payment doesn\'t belong to this checkout. Try again.' ) );

            return;
        }

        // The event comes from the browser: only the gateway can say the
        // payment really is confirmed.
        if ( ! $this->confirmedByGateway( $cart, $current ) ) {
            $this->confirmedPayment = null;
            $this->addError( 'gateway', __( 'Your payment couldn\'t be confirmed. Try again.' ) );

            return;
        }

        $this->confirmedPayment = [ 'reference' => $reference, 'total' => (int) $cart->total_amount ];
        $this->completeStep( 'payment' );
    }

    /**
     * Keeps the shopper on the payment step when the driver reports a
     * failed payment. The driver shows the message.
     *
     * @since 1.0.0
     *
     * @return void
     */
    #[On( 'payment-failed' )]
    public function paymentFailed(): void
    {
        $this->confirmedPayment = null;
    }

    /**
     * Moves on once a satellite's step reports it is done.
     *
     * @since 1.0.0
     *
     * @param  string  $step  The step key.
     *
     * @return void
     */
    #[On( 'ecommerce-checkout-step-completed' )]
    public function stepCompleted( string $step = '' ): void
    {
        $cart  = $this->cart();
        $steps = null === $cart ? [] : $this->steps( $cart );

        if ( null === $cart || null === ( $steps[ $step ]['component'] ?? null ) ) {
            return;
        }

        if ( ! in_array( $step, $this->completedSteps, true ) ) {
            $this->completedSteps[] = $step;
        }

        if ( $step === $this->step ) {
            $this->completeStep( $step );
        }
    }

    /**
     * Clears the password when the shopper no longer wants an account.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedCreateAccount(): void
    {
        if ( ! $this->createAccount ) {
            $this->password = '';
            $this->resetErrorBag( 'password' );
        }
    }

    /**
     * Places the order (spec §7.4): checks the review form, then finalizes
     * the cart through the engine under the action token and the
     * `ecommerce.checkout.finalize` limit, and acts on how the payment
     * went.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted when the button rendered.
     *
     * @return void
     */
    public function placeOrder( string $token = '' ): void
    {
        $this->resetErrorBag();

        $cart = null === $this->placement ? $this->openCart() : null;

        if ( null === $cart ) {
            return;
        }

        if ( 'review' !== $this->step || ! $this->canVisit( 'review', $cart ) ) {
            $this->moveTo( $this->furthestStep( $cart ), $cart );
            $this->addError( 'review', __( 'Finish the steps above before placing your order.' ) );
            $this->errorSummary++;

            return;
        }

        $errors = $this->reviewErrors( $cart );

        if ( [] !== $errors ) {
            foreach ( $errors as $field => $message ) {
                $this->addError( $field, $message );
            }

            $this->errorSummary++;

            return;
        }

        $note      = trim( sanitizeText( $this->orderNote ) );
        $reference = (int) $cart->total_amount > 0 ? (string) ( $cart->payment_reference ?? '' ) : null;
        $context   = array_filter( [
            'customer_note' => '' === $note ? null : $note,
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
        ], static fn ( mixed $value ): bool => null !== $value && '' !== $value );

        try {
            $outcome = $this->rateLimited(
                'ecommerce.checkout.finalize',
                fn (): ?array => $this->withActionToken( $token, 'place-order', fn (): array => $this->finalizeCart( $cart, $reference, $context ), $cart ),
            );
        } catch ( CartOperationException $exception ) {
            $this->placementRefused( $cart, $exception );

            return;
        } catch ( Throwable $exception ) {
            report( $exception );

            if ( ! $this->placedButUnpaid( $cart, __( 'Your payment couldn\'t be completed. Try again or use another payment method.' ) ) ) {
                $this->addError( 'review', __( 'We couldn\'t place your order. Try again in a moment.' ) );
                $this->errorSummary++;
            }

            return;
        }

        if ( is_array( $outcome ) ) {
            $this->settleOutcome( $outcome );
        }
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $cart   = null === $this->unavailable && null === $this->placement && null === $this->placedOrder ? $this->cart() : null;
        $ready  = null !== $cart && ! $this->signInRequired;
        $ships  = $ready && $this->requiresShipping( $cart );
        $steps  = $ready ? $this->steps( $cart ) : [];
        $policy = $this->checkout()->guestCheckout();
        $listed = $this->describeSteps( $steps, $cart );

        $this->progress = max( 1, (int) ( collect( $listed )->firstWhere( 'current', true )['number'] ?? 1 ) );

        return view( 'ecommerce-storefront::livewire.checkout.index', [
            'cart'             => $cart,
            'steps'            => $listed,
            'placedOrderModel' => null === $this->placement ? null : Order::query()->with( 'items' )->find( $this->placement['order'] ),
            'placeOrderToken'  => $ready && 'review' === $this->step ? $this->actionToken( 'place-order', $cart ) : null,
            'termsUrl'         => $ready && 'review' === $this->step ? CheckoutLayout::termsUrl() : null,
            'offersAccount'    => $ready && 'review' === $this->step && $this->offersAccount( $cart ),
            'noteMaxLength'    => self::NOTE_MAX_LENGTH,
            'ships'            => $ships,
            'lines'            => null === $cart ? [] : $this->lines( $cart ),
            'totals'           => null === $cart ? null : $this->totals( $cart ),
            'isGuest'          => null !== $cart && $this->isGuest( $cart ),
            'needsAccount'     => null !== $cart && $this->isGuest( $cart ) && CheckoutService::GUESTS_REQUIRE_ACCOUNT === $policy,
            'loginUrl'         => $this->authUrl( 'login_route' ),
            'registerUrl'      => $this->authUrl( 'register_route' ),
            'cartUrl'          => Route::has( 'artisanpack.ecommerce.storefront.cart' ) ? route( 'artisanpack.ecommerce.storefront.cart' ) : null,
            'savedAddresses'   => $ready ? $this->savedAddresses( $cart ) : [],
            'gateways'         => $ready && 'payment' === $this->step ? $this->gatewayOptions( $cart ) : [],
            'paymentRequired'  => null !== $cart && (int) $cart->total_amount > 0,
            'chosenRate'       => $this->chosenRate( $cart ),
            'gatewayLabel'     => $this->gatewayLabel( $cart ),
            'regions'          => [ 'shipping' => AddressFormats::regions( $this->shipping['country_code'] ?? null ), 'billing' => AddressFormats::regions( $this->billing['country_code'] ?? null ) ],
            'errorLabels'      => $this->errorLabels(),
        ] );
    }

    /**
     * The review form's problems, keyed by field: a note that's too long,
     * terms not accepted (when a terms page is set), and — when a guest
     * asked for an account — a password the account action refuses or an
     * email that already has an account.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return array<string, string>
     */
    protected function reviewErrors( Cart $cart ): array
    {
        $errors = [];

        if ( mb_strlen( trim( $this->orderNote ) ) > self::NOTE_MAX_LENGTH ) {
            $errors['orderNote'] = __( 'Keep your note to :max characters or fewer.', [ 'max' => self::NOTE_MAX_LENGTH ] );
        }

        if ( null !== CheckoutLayout::termsUrl() && ! $this->acceptTerms ) {
            $errors['acceptTerms'] = __( 'Accept the terms and conditions to place your order.' );
        }

        $action = $this->createAccount && $this->offersAccount( $cart ) ? $this->accountAction() : null;

        if ( null !== $action ) {
            $validator = Validator::make( [ 'password' => $this->password ], [ 'password' => $action->passwordRules() ], [
                'password.required' => __( 'Choose a password for your account.' ),
            ] );

            if ( $validator->fails() ) {
                $errors['password'] = (string) $validator->errors()->first( 'password' );
            } elseif ( ! $action->emailIsAvailable( (string) $cart->email ) ) {
                $errors['password'] = __( 'There\'s already an account for :email. Sign in to use it, or place the order without an account.', [ 'email' => (string) $cart->email ] );
            }
        }

        return $errors;
    }

    /**
     * Places the order through the engine, after
     * `ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder`. Runs once
     * per action token.
     *
     * @since 1.0.0
     *
     * @param  Cart                  $cart       The cart.
     * @param  string|null           $reference  The payment session the shopper confirmed (null when nothing is due).
     * @param  array<string, mixed>  $context    Placement context (note, IP, user agent).
     *
     * @return array{cart: int, order: int, status: string, message: string|null}
     */
    protected function finalizeCart( Cart $cart, ?string $reference, array $context ): array
    {
        doAction( 'ap.ecommerceStorefrontLivewire.checkout.beforePlaceOrder', $cart, $context );

        return $this->describeResult( $cart, $this->checkout()->finalize( $cart, $reference, $context ) );
    }

    /**
     * What a finalize call came to, in a form the action token can replay.
     *
     * @since 1.0.0
     *
     * @param  Cart            $cart    The cart.
     * @param  CheckoutResult  $result  The engine's result.
     *
     * @return array{cart: int, order: int, status: string, message: string|null}
     */
    protected function describeResult( Cart $cart, CheckoutResult $result ): array
    {
        $message = $result->payment?->payment?->errorMessage;

        return [
            'cart'    => (int) $cart->id,
            'order'   => (int) $result->order->id,
            'status'  => $result->status(),
            'message' => is_string( $message ) && '' !== trim( $message ) ? trim( strip_tags( $message ) ) : null,
        ];
    }

    /**
     * Acts on how placing the order went: captured goes to the
     * confirmation; challenged or failed shows the payment again for the
     * placed order; blocked shows a generic message.
     *
     * @since 1.0.0
     *
     * @param  array{cart: int, order: int, status: string, message: string|null}  $outcome  From {@see self::describeResult()}.
     *
     * @return void
     */
    protected function settleOutcome( array $outcome ): void
    {
        $cart  = Cart::query()->find( $outcome['cart'] );
        $order = Order::query()->find( $outcome['order'] );

        if ( null === $cart || null === $order ) {
            $this->addError( 'review', __( 'We couldn\'t place your order. Try again in a moment.' ) );
            $this->errorSummary++;

            return;
        }

        match ( $outcome['status'] ) {
            PaymentFinalization::STATUS_CAPTURED   => $this->orderPlaced( $order ),
            PaymentFinalization::STATUS_BLOCKED    => $this->orderBlocked(),
            PaymentFinalization::STATUS_CHALLENGED => $this->awaitPayment( $cart, $order, __( 'Your bank needs you to confirm this payment. Confirm it below to finish your order.' ) ),
            default                                => $this->awaitPayment( $cart, $order, $outcome['message'] ?? __( 'Your payment couldn\'t be completed. Try again or use another payment method.' ) ),
        };
    }

    /**
     * The order is paid: creates the account the shopper asked for and
     * goes to the confirmation page (or, without one, says the order is
     * placed).
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return void
     */
    protected function orderPlaced( Order $order ): void
    {
        CheckoutPlacement::forget();

        $this->placement      = null;
        $this->payment        = null;
        $this->paymentProblem = null;

        $this->createAccountFor( $order );
        $this->cartChanged();

        $url = CheckoutPlacement::confirmationUrl( $order->refresh() );

        if ( null === $url ) {
            $this->placedOrder  = (string) $order->order_number;
            $this->announcement = __( 'Your order :number is placed.', [ 'number' => $this->placedOrder ] );

            return;
        }

        $this->redirect( $url );
    }

    /**
     * Fraud screening blocked the payment: the shopper gets a generic
     * message, never the reason.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function orderBlocked(): void
    {
        CheckoutPlacement::forget();

        $this->placement   = null;
        $this->payment     = null;
        $this->unavailable = __( 'We couldn\'t process your order. Please contact the store for help.' );

        $this->cartChanged();
    }

    /**
     * The order is placed but not paid (the payment needs confirming, or
     * was declined): shows the payment step again for the order's own
     * payment, and remembers the order across reloads.
     *
     * @since 1.0.0
     *
     * @param  Cart    $cart     The converted cart.
     * @param  Order   $order    The order.
     * @param  string  $message  What happened.
     *
     * @return void
     */
    protected function awaitPayment( Cart $cart, Order $order, string $message ): void
    {
        CheckoutPlacement::remember( $cart, $order );

        $this->placement        = [ 'cart' => (int) $cart->id, 'order' => (int) $order->id, 'number' => (string) $order->order_number ];
        $this->paymentProblem   = $message;
        $this->confirmedPayment = null;
        $this->step             = 'payment';
        $this->focusStep        = true;
        $this->announcement     = $message;

        $this->preparePlacementPayment( $cart, $order );
        $this->cartChanged();
    }

    /**
     * Picks up an order placed earlier in this session whose payment
     * isn't finished: a paid one goes to its confirmation; a payment the
     * return route found confirmed finishes it; otherwise its payment is
     * shown again.
     *
     * @since 1.0.0
     *
     * @return bool True when there was such an order.
     */
    protected function resumePlacement(): bool
    {
        $placement = CheckoutPlacement::current();

        if ( null === $placement ) {
            return false;
        }

        [ 'cart' => $cart, 'order' => $order ] = $placement;

        if ( CheckoutPlacement::isPaid( $order ) ) {
            $this->orderPlaced( $order );

            return true;
        }

        if ( in_array( (string) $order->system_status, [ 'cancelled', 'failed', 'refunded' ], true ) ) {
            CheckoutPlacement::forget();

            return false;
        }

        $this->placement = [ 'cart' => (int) $cart->id, 'order' => (int) $order->id, 'number' => (string) $order->order_number ];
        $this->step      = 'payment';

        $returned  = app()->bound( 'session' ) ? session()->pull( self::CONFIRMED_PAYMENT_SESSION_KEY ) : null;
        $reference = (string) ( $order->payment_reference ?? '' );

        if ( is_array( $returned ) && is_string( $returned['reference'] ?? null ) && '' !== $reference && hash_equals( $reference, $returned['reference'] ) ) {
            $this->finishPlacement( $cart, $reference );

            return true;
        }

        $this->paymentProblem = __( 'Your order :number is placed, but its payment isn\'t finished. Pay below to complete it.', [ 'number' => (string) $order->order_number ] );

        $this->preparePlacementPayment( $cart, $order );

        return true;
    }

    /**
     * Checks a payment the driver confirmed for the placed order, then
     * finishes the order.
     *
     * @since 1.0.0
     *
     * @param  string  $reference  The confirmed session.
     *
     * @return void
     */
    protected function placementPaymentConfirmed( string $reference ): void
    {
        $placement = CheckoutPlacement::current();

        if ( null === $placement || (int) $placement['order']->id !== (int) ( $this->placement['order'] ?? 0 ) ) {
            $this->placement = null;
            $this->payment   = null;
            $this->leave( __( 'Your cart is empty' ), __( 'Add something to your cart to check out.' ) );

            return;
        }

        if ( CheckoutPlacement::isPaid( $placement['order'] ) ) {
            $this->orderPlaced( $placement['order'] );

            return;
        }

        $current = (string) ( $placement['order']->payment_reference ?? '' );

        if ( '' === $current || '' === $reference || ! hash_equals( $current, $reference ) ) {
            $this->addError( 'gateway', __( 'That payment doesn\'t belong to this checkout. Try again.' ) );

            return;
        }

        $this->finishPlacement( $placement['cart'], $reference );
    }

    /**
     * Finalizes the placed order again (the engine resumes its payment)
     * and acts on the outcome.
     *
     * @since 1.0.0
     *
     * @param  Cart         $cart       The converted cart.
     * @param  string|null  $reference  The confirmed session.
     *
     * @return void
     */
    protected function finishPlacement( Cart $cart, ?string $reference ): void
    {
        try {
            $outcome = $this->rateLimited( 'ecommerce.checkout.finalize', fn (): array => $this->describeResult( $cart, $this->checkout()->finalize( $cart, $reference ) ) );
        } catch ( CartOperationException $exception ) {
            $this->addError( 'gateway', $exception->getMessage() );
            $this->errorSummary++;

            return;
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->addError( 'gateway', __( 'Your payment couldn\'t be checked. Try again in a moment.' ) );
            $this->errorSummary++;

            return;
        }

        if ( is_array( $outcome ) ) {
            $this->settleOutcome( $outcome );
        }
    }

    /**
     * Shows the placed order's own payment session through its gateway's
     * driver, so the shopper can confirm (or retry) it.
     *
     * @since 1.0.0
     *
     * @param  Cart   $cart   The converted cart.
     * @param  Order  $order  The order.
     *
     * @return void
     */
    protected function preparePlacementPayment( Cart $cart, Order $order ): void
    {
        $key       = (string) ( $order->payment_gateway_key ?? '' );
        $reference = (string) ( $order->payment_reference ?? '' );
        $gateway   = '' === $key ? null : app( PaymentGatewayRegistry::class )->find( $key );

        $this->payment = null;

        if ( null === $gateway || '' === $reference ) {
            return;
        }

        try {
            $session = $gateway->retrievePaymentSession( $reference );
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->addError( 'gateway', __( 'Your payment couldn\'t be loaded. Try again in a moment.' ) );

            return;
        }

        $config    = ClientPaymentConfig::for( $gateway, $cart, $session );
        $component = is_array( $config ) && is_string( $config['driver'] ?? null ) ? app( PaymentDriverRegistry::class )->component( $config['driver'] ) : null;

        if ( null === $component ) {
            $this->addError( 'gateway', __( 'This payment method isn\'t available.' ) );

            return;
        }

        $this->gateway = $key;
        $this->payment = [
            'gateway'   => $key,
            'label'     => $gateway->label(),
            'reference' => $reference,
            'component' => $component,
            'config'    => $config,
        ];
    }

    /**
     * The engine refused to place the order (nothing was placed, or the
     * order exists but its payment couldn't be attempted): shows why, on
     * the step that needs fixing.
     *
     * @since 1.0.0
     *
     * @param  Cart                    $cart       The cart the shopper tried to check out.
     * @param  CartOperationException  $exception  The refusal.
     *
     * @return void
     */
    protected function placementRefused( Cart $cart, CartOperationException $exception ): void
    {
        if ( $this->placedButUnpaid( $cart, $exception->getMessage() ) ) {
            return;
        }

        $cart = $this->cart()?->refresh();

        if ( null === $cart ) {
            $this->leave( __( 'Your cart is empty' ), __( 'Add something to your cart to check out.' ) );

            return;
        }

        if ( $exception instanceof CheckoutException && 'account-required' === $exception->errorCode ) {
            $this->signInRequired = CheckoutService::GUESTS_DISABLED === $this->checkout()->guestCheckout();
        }

        $paymentIssue = in_array( $exception->field, [ 'payment', 'payment_reference', 'payment_gateway' ], true )
            || ( $exception instanceof CheckoutException && 'totals-changed' === $exception->errorCode );

        if ( $paymentIssue ) {
            $this->confirmedPayment = null;
        }

        $target = $this->furthestStep( $cart );

        if ( $target !== $this->step ) {
            $this->moveTo( $target, $cart );
        }

        $this->addError( $this->errorField( $exception->field, 'payment' === $target ? 'gateway' : 'review' ), $exception->getMessage() );
        $this->errorSummary++;
    }

    /**
     * When a finalize call failed after the cart became an order, shows
     * the order's payment again with `$message`.
     *
     * @since 1.0.0
     *
     * @param  Cart    $cart     The cart the shopper tried to check out.
     * @param  string  $message  What went wrong.
     *
     * @return bool True when the cart became an order.
     */
    protected function placedButUnpaid( Cart $cart, string $message ): bool
    {
        $order = $this->checkout()->placedOrder( $cart );

        if ( null === $order ) {
            return false;
        }

        if ( CheckoutPlacement::isPaid( $order ) ) {
            $this->orderPlaced( $order );
        } else {
            $this->awaitPayment( $cart->refresh(), $order, $message );
        }

        return true;
    }

    /**
     * Creates the account a guest asked for, once their order is paid. A
     * failure doesn't hold up the order; the shopper is told.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return void
     */
    protected function createAccountFor( Order $order ): void
    {
        $password = $this->password;

        $this->password = '';

        if ( ! $this->createAccount || '' === $password || auth()->check() || ! $this->checkout()->offersAccountCreation() ) {
            return;
        }

        $action = $this->accountAction();

        if ( null === $action ) {
            return;
        }

        try {
            $action->create( $order, $password );
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->flashToastWarning( __( 'We couldn\'t create your account' ), __( 'Your order is placed. You can create an account later with :email.', [ 'email' => (string) $order->email ] ) );

            return;
        }

        $this->flashToastSuccess( __( 'Your account is ready' ), __( 'You\'re signed in as :email.', [ 'email' => (string) $order->email ] ) );
    }

    /**
     * Whether a guest is offered an account with their order
     * (`checkout.account_creation`).
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return bool
     */
    protected function offersAccount( Cart $cart ): bool
    {
        return $this->isGuest( $cart ) && ! auth()->check() && $this->checkout()->offersAccountCreation() && null !== $this->accountAction();
    }

    /**
     * The configured account action (`auth.create_account_action`), or
     * null when it isn't usable.
     *
     * @since 1.0.0
     *
     * @return CreatesCustomerAccounts|null
     */
    protected function accountAction(): ?CreatesCustomerAccounts
    {
        $class = config( 'artisanpack.ecommerce-storefront-livewire.auth.create_account_action' );

        if ( ! is_string( $class ) || ! is_a( $class, CreatesCustomerAccounts::class, true ) ) {
            return null;
        }

        return app( $class );
    }

    /**
     * The engine's checkout service.
     *
     * @since 1.0.0
     *
     * @return CheckoutService
     */
    protected function checkout(): CheckoutService
    {
        return app( CheckoutService::class );
    }

    /**
     * The shopper's open cart for an action. When it has gone (it expired,
     * or became an order in another tab), the shopper is sent back to the
     * cart.
     *
     * @since 1.0.0
     *
     * @return Cart|null
     */
    protected function openCart(): ?Cart
    {
        $cart = $this->cart();

        if ( null === $cart ) {
            $this->leave( __( 'Your cart is empty' ), __( 'Add something to your cart to check out.' ) );
        }

        return $cart;
    }

    /**
     * Sends the shopper back to the cart when it can't check out (empty,
     * or with lines that can't be bought). Without a cart page, the reason
     * is shown in place of the checkout.
     *
     * @since 1.0.0
     *
     * @param  Cart|null  $cart  The cart.
     *
     * @return bool True when checkout can go ahead.
     */
    protected function guard( ?Cart $cart ): bool
    {
        if ( null === $cart || [] === $this->paidLines( $cart ) ) {
            return $this->leave( __( 'Your cart is empty' ), __( 'Add something to your cart to check out.' ) );
        }

        $unsellable = array_filter( $this->lines( $cart ), static fn ( array $line ): bool => null !== $line['unsellable'] );

        if ( [] !== $unsellable ) {
            return $this->leave(
                __( 'Some items can\'t be bought' ),
                trans_choice(
                    'Remove the item marked in your cart to check out.|Remove the :count items marked in your cart to check out.',
                    count( $unsellable ),
                    [ 'count' => count( $unsellable ) ],
                ),
            );
        }

        return true;
    }

    /**
     * Redirects to the cart with a warning, or (without a cart page) shows
     * the warning in place of the checkout.
     *
     * @since 1.0.0
     *
     * @param  string       $title        What happened.
     * @param  string|null  $description  What to do.
     *
     * @return bool Always false.
     */
    protected function leave( string $title, ?string $description = null ): bool
    {
        $this->unavailable = $title;

        if ( Route::has( 'artisanpack.ecommerce.storefront.cart' ) ) {
            $this->flashToastWarning( $title, $description );
            $this->redirectRoute( 'artisanpack.ecommerce.storefront.cart' );
        }

        return false;
    }

    /**
     * Starts (or refreshes) checkout, holding the cart's stock, and lists
     * the lines it had to reduce or remove.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart    The cart.
     * @param  bool  $notify  Tell the rest of the page when the cart changed.
     *
     * @return bool True when checkout can go ahead.
     */
    protected function begin( Cart $cart, bool $notify ): bool
    {
        try {
            $start = $this->checkout()->start( $cart );
        } catch ( CheckoutException $exception ) {
            if ( 'account-required' === $exception->errorCode ) {
                $this->signInRequired = true;

                return false;
            }

            return $this->leave( $exception->getMessage() );
        } catch ( CartOperationException $exception ) {
            return $this->leave( $exception->getMessage() );
        }

        $this->adjustments = $this->describeAdjustments( $start->adjustments );

        if ( [] === $start->adjustments ) {
            return true;
        }

        if ( $notify ) {
            $this->cartChanged( $start->cart );
        } else {
            app( StorefrontCart::class )->remember( $start->cart );
        }

        if ( [] === $this->paidLines( $start->cart ) ) {
            return $this->leave( __( 'The items in your cart are out of stock' ), __( 'We\'ve removed them from your cart.' ) );
        }

        return true;
    }

    /**
     * One shopper-facing message per line `start()` reduced or removed.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{item_id: int, product_id: int, requested: int, available: int}>  $adjustments  From `CheckoutStart`.
     *
     * @return array<int, string>
     */
    protected function describeAdjustments( array $adjustments ): array
    {
        if ( [] === $adjustments ) {
            return [];
        }

        $names = Product::query()->whereKey( array_column( $adjustments, 'product_id' ) )->pluck( 'name', 'id' );

        return array_map( static function ( array $adjustment ) use ( $names ): string {
            $name = (string) ( $names[ $adjustment['product_id'] ] ?? __( 'An item' ) );

            return (int) $adjustment['available'] > 0
                ? trans_choice(
                    'Only :available of :name is in stock, so we\'ve changed the quantity in your cart.|Only :available of :name are in stock, so we\'ve changed the quantity in your cart.',
                    (int) $adjustment['available'],
                    [ 'available' => (int) $adjustment['available'], 'name' => $name ],
                )
                : __( ':name is out of stock, so we\'ve removed it from your cart.', [ 'name' => $name ] );
        }, array_values( $adjustments ) );
    }

    /**
     * Fills the forms from what the cart already holds (or the shopper's
     * account and saved addresses).
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return void
     */
    protected function fillFromCart( Cart $cart ): void
    {
        $user = auth()->user();

        $this->email            = (string) ( $cart->email ?? ( is_string( $user?->email ?? null ) ? $user->email : '' ) );
        $this->marketingConsent = true === ( ( (array) ( $cart->meta ?? [] ) )[ self::MARKETING_CONSENT_META_KEY ] ?? false );

        $book     = $this->addressBook( $cart );
        $shipping = $book->firstWhere( 'is_default_shipping', true ) ?? $book->first();
        $billing  = $book->firstWhere( 'is_default_billing', true ) ?? $shipping;

        $this->shipping = is_array( $cart->shipping_address ) ? $this->formAddress( $cart->shipping_address ) : ( null === $shipping ? $this->blankAddress() : $this->formAddress( $shipping->toArray() ) );
        $this->billing  = is_array( $cart->billing_address ) ? $this->formAddress( $cart->billing_address ) : ( null === $billing ? $this->blankAddress() : $this->formAddress( $billing->toArray() ) );

        $this->savedShipping = ! is_array( $cart->shipping_address ) && null !== $shipping ? (string) $shipping->id : 'new';
        $this->savedBilling  = ! is_array( $cart->billing_address ) && null !== $billing ? (string) $billing->id : 'new';

        $this->billingSameAsShipping = ! is_array( $cart->billing_address );

        if ( ! $this->requiresShipping( $cart ) && ! is_array( $cart->billing_address ) && is_array( $cart->shipping_address ) ) {
            $this->billing      = $this->formAddress( $cart->shipping_address );
            $this->savedBilling = 'new';
        }

        $this->shippingRate = (string) ( $this->chosenRate( $cart )['id'] ?? '' );
        $this->gateway      = (string) ( $cart->payment_gateway_key ?? '' );
    }

    /**
     * Picks up a payment the return route found confirmed, when it is
     * still the cart's session and for the cart's total.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return void
     */
    protected function acceptReturnedPayment( Cart $cart ): void
    {
        $returned = app()->bound( 'session' ) ? session()->pull( self::CONFIRMED_PAYMENT_SESSION_KEY ) : null;

        if ( ! is_array( $returned ) || ! is_string( $returned['reference'] ?? null ) ) {
            return;
        }

        $current = (string) ( $cart->payment_reference ?? '' );

        if ( '' !== $current && hash_equals( $current, $returned['reference'] ) && (int) ( $returned['total'] ?? -1 ) === (int) $cart->total_amount ) {
            $this->confirmedPayment = [ 'reference' => $current, 'total' => (int) $cart->total_amount ];
        }
    }

    /**
     * The checkout's steps for the cart.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return array<string, array{key: string, label: string, component: string|null}>
     */
    protected function steps( Cart $cart ): array
    {
        return CheckoutSteps::for( $cart, $this->requiresShipping( $cart ) );
    }

    /**
     * Whether a step is done, judged from the cart and the engine's
     * checkout state.
     *
     * @since 1.0.0
     *
     * @param  string                                                                     $key    Step key.
     * @param  array<string, array{key: string, label: string, component: string|null}>  $steps  The steps.
     * @param  Cart                                                                       $cart   The cart.
     *
     * @return bool
     */
    protected function stepIsComplete( string $key, array $steps, Cart $cart ): bool
    {
        $state = (string) $cart->checkout_state;
        $ships = isset( $steps['shipping'] );

        return match ( $key ) {
            'contact'  => '' !== trim( (string) $cart->email ),
            'address'  => ( $ships ? is_array( $cart->shipping_address ) : ( is_array( $cart->billing_address ) || is_array( $cart->shipping_address ) ) )
                && CheckoutState::rank( $state ) >= CheckoutState::rank( $ships ? CheckoutState::SHIPPING_SELECTION : CheckoutState::PAYMENT_SELECTION ),
            'shipping' => null !== $this->chosenRate( $cart ) && CheckoutState::rank( $state ) >= CheckoutState::rank( CheckoutState::PAYMENT_SELECTION ),
            'payment'  => (int) $cart->total_amount <= 0 || $this->paymentIsConfirmed( $cart ),
            'review'   => false,
            default    => in_array( $key, $this->completedSteps, true ),
        };
    }

    /**
     * Whether the driver confirmed the cart's current session for its
     * current total.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return bool
     */
    protected function paymentIsConfirmed( Cart $cart ): bool
    {
        $current = (string) ( $cart->payment_reference ?? '' );

        return null !== $this->confirmedPayment
            && '' !== $current
            && hash_equals( $current, $this->confirmedPayment['reference'] )
            && $this->confirmedPayment['total'] === (int) $cart->total_amount;
    }

    /**
     * Whether the cart's gateway says the session is confirmed (authorized,
     * succeeded, or processing) for the cart's current total and currency.
     *
     * @since 1.0.0
     *
     * @param  Cart    $cart       The cart.
     * @param  string  $reference  The session reference.
     *
     * @return bool
     */
    protected function confirmedByGateway( Cart $cart, string $reference ): bool
    {
        $gateway = null === $cart->payment_gateway_key ? null : app( PaymentGatewayRegistry::class )->find( (string) $cart->payment_gateway_key );

        if ( null === $gateway ) {
            return false;
        }

        try {
            return PaymentSessions::confirmedFor( $gateway->retrievePaymentSession( $reference ), $cart );
        } catch ( Throwable $exception ) {
            report( $exception );

            return false;
        }
    }

    /**
     * The first step that isn't done.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return string
     */
    protected function furthestStep( Cart $cart ): string
    {
        $steps = $this->steps( $cart );

        foreach ( array_keys( $steps ) as $key ) {
            if ( ! $this->stepIsComplete( $key, $steps, $cart ) ) {
                return $key;
            }
        }

        return 'review';
    }

    /**
     * Whether the shopper can open a step: it exists and every step before
     * it is done.
     *
     * @since 1.0.0
     *
     * @param  string  $step  Step key.
     * @param  Cart    $cart  The cart.
     *
     * @return bool
     */
    protected function canVisit( string $step, Cart $cart ): bool
    {
        $keys = array_keys( $this->steps( $cart ) );
        $at   = array_search( $step, $keys, true );

        return false !== $at && $at <= (int) array_search( $this->furthestStep( $cart ), $keys, true );
    }

    /**
     * Moves on from a step the shopper just completed: to the next step,
     * or to the first one still to do.
     *
     * @since 1.0.0
     *
     * @param  string  $step  The completed step.
     *
     * @return void
     */
    protected function completeStep( string $step ): void
    {
        $cart = $this->openCart();

        if ( null === $cart ) {
            return;
        }

        $keys = array_keys( $this->steps( $cart ) );
        $at   = array_search( $step, $keys, true );
        $next = false === $at ? null : ( $keys[ $at + 1 ] ?? null );

        $this->moveTo( null !== $next && $this->canVisit( $next, $cart ) ? $next : $this->furthestStep( $cart ), $cart );
    }

    /**
     * Shows a step, moves focus to its heading, and announces it.
     *
     * @since 1.0.0
     *
     * @param  string  $step  Step key.
     * @param  Cart    $cart  The cart.
     *
     * @return void
     */
    protected function moveTo( string $step, Cart $cart ): void
    {
        $keys = array_keys( $this->steps( $cart ) );

        $this->step      = $step;
        $this->focusStep = true;

        $this->announcement = __( 'Step :number of :total: :step', [
            'number' => (int) array_search( $step, $keys, true ) + 1,
            'total'  => count( $keys ),
            'step'   => $this->steps( $cart )[ $step ]['label'] ?? $step,
        ] );

        $this->enterStep( $cart );
    }

    /**
     * Prepares the step on screen: quotes shipping, or sets up the payment.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return void
     */
    protected function enterStep( Cart $cart ): void
    {
        match ( $this->step ) {
            'shipping' => $this->quoteRates( $cart ),
            'payment'  => $this->preparePayment( $cart ),
            default    => null,
        };
    }

    /**
     * Quotes the shipping rates for the cart's address, and clears a
     * chosen rate the engine no longer offers.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return void
     */
    protected function quoteRates( Cart $cart ): void
    {
        try {
            $this->rates = $this->checkout()->shippingRates( $cart )
                ->map( static fn ( ShippingRate $rate ): array => [
                    'id'       => $rate->id(),
                    'label'    => $rate->label,
                    'amount'   => (int) $rate->amount->getAmount(),
                    'currency' => $rate->amount->getCurrency()->getCode(),
                    'estimate' => is_string( $rate->meta['delivery_estimate'] ?? null ) && '' !== trim( $rate->meta['delivery_estimate'] ) ? trim( $rate->meta['delivery_estimate'] ) : null,
                ] )
                ->values()
                ->all();
        } catch ( CartOperationException $exception ) {
            $this->rates = [];
            $this->addError( 'shippingRate', $exception->getMessage() );

            return;
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->rates = [];
            $this->addError( 'shippingRate', __( 'Shipping rates are unavailable right now. Try again in a moment.' ) );

            return;
        }

        $chosen = (string) ( $this->chosenRate( $cart->refresh() )['id'] ?? '' );

        if ( '' !== $this->shippingRate && $this->shippingRate !== $chosen ) {
            $this->shippingNotice = __( 'Your shipping choice is no longer available. Choose a shipping option.' );
        }

        $this->shippingRate = in_array( $chosen, array_column( $this->rates, 'id' ), true ) ? $chosen : '';
    }

    /**
     * Sets up the payment step: a single gateway is chosen for the
     * shopper, and a chosen gateway's session is created (or reused).
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return void
     */
    protected function preparePayment( Cart $cart ): void
    {
        if ( (int) $cart->total_amount <= 0 || ( $this->isGuest( $cart ) && CheckoutService::GUESTS_REQUIRE_ACCOUNT === $this->checkout()->guestCheckout() ) ) {
            $this->payment = null;

            return;
        }

        $gateways = $this->gatewayOptions( $cart );

        if ( ! isset( $gateways[ $this->gateway ] ) ) {
            $this->gateway = 1 === count( $gateways ) ? (string) array_key_first( $gateways ) : '';
        }

        if ( '' === $this->gateway ) {
            $this->payment = null;

            return;
        }

        $this->startPayment( $cart );
    }

    /**
     * Chooses the gateway and creates (or reuses) its payment session,
     * then works out the driver that renders it. A gateway whose payment
     * UI can't be shown is hidden.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return void
     */
    protected function startPayment( Cart $cart ): void
    {
        $key      = $this->gateway;
        $gateways = $this->checkout()->availableGateways( $cart );
        $gateway  = in_array( $key, $this->hiddenGateways, true ) ? null : ( $gateways[ $key ] ?? null );

        if ( ! $gateway instanceof PaymentGateway ) {
            $this->gateway = '';
            $this->payment = null;
            $this->addError( 'gateway', __( 'Choose a payment method.' ) );

            return;
        }

        $checkout = $this->checkout();
        $context  = array_filter( [
            'return_url' => Route::has( 'artisanpack.ecommerce.storefront.checkout.return' ) ? route( 'artisanpack.ecommerce.storefront.checkout.return' ) : null,
        ] );

        $session = $this->rateLimited( 'ecommerce.cart.mutate', function () use ( $checkout, $cart, $key, $context ) {
            $session = null;

            $this->attempt( function () use ( $checkout, $cart, $key, $context, &$session ): void {
                $checkout->setPaymentGateway( $cart, $key );
                $session = $checkout->createPaymentSession( $cart, $context );
            }, 'gateway' );

            return $session;
        } );

        if ( null === $session ) {
            $this->payment = null;

            return;
        }

        $config    = ClientPaymentConfig::for( $gateway, $cart->refresh(), $session );
        $component = is_array( $config ) && is_string( $config['driver'] ?? null ) ? app( PaymentDriverRegistry::class )->component( $config['driver'] ) : null;

        if ( null === $component ) {
            $this->hiddenGateways[] = $key;
            $this->gateway          = '';
            $this->payment          = null;
            $this->addError( 'gateway', __( 'This payment method isn\'t available.' ) );

            return;
        }

        if ( null !== $this->confirmedPayment && $this->confirmedPayment['reference'] !== $session->reference ) {
            $this->confirmedPayment = null;
        }

        $this->payment = [
            'gateway'   => $key,
            'label'     => $gateway->label(),
            'reference' => $session->reference,
            'component' => $component,
            'config'    => $config,
        ];
    }

    /**
     * Runs an engine call, showing its refusal under `$field` (or the
     * field it names) and in the error summary.
     *
     * @since 1.0.0
     *
     * @param  callable  $call   The engine call.
     * @param  string    $field  Where its errors go by default.
     *
     * @return bool True when it succeeded.
     */
    protected function attempt( callable $call, string $field ): bool
    {
        try {
            $call();
        } catch ( CartOperationException $exception ) {
            if ( $exception instanceof CheckoutException && 'account-required' === $exception->errorCode && CheckoutService::GUESTS_DISABLED === $this->checkout()->guestCheckout() ) {
                $this->signInRequired = true;
            }

            $this->addError( $this->errorField( $exception->field, $field ), $exception->getMessage() );
            $this->errorSummary++;

            return false;
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->addError( $field, __( 'Something went wrong. Try again in a moment.' ) );
            $this->errorSummary++;

            return false;
        }

        return true;
    }

    /**
     * The form field an engine error belongs to: `shipping_address.city`
     * is `shipping.city`; a whole-address error goes to the street line;
     * anything else to `$fallback`.
     *
     * @since 1.0.0
     *
     * @param  string  $engineField  The engine's field.
     * @param  string  $fallback     Default field.
     *
     * @return string
     */
    protected function errorField( string $engineField, string $fallback ): string
    {
        foreach ( [ 'shipping_address' => 'shipping', 'billing_address' => 'billing' ] as $prefix => $model ) {
            if ( $engineField === $prefix ) {
                return $model . '.address1';
            }

            if ( str_starts_with( $engineField, $prefix . '.' ) ) {
                $sub = substr( $engineField, strlen( $prefix ) + 1 );

                return in_array( $sub, AddressForm::FIELDS, true ) ? $model . '.' . $sub : $model . '.address1';
            }
        }

        return 'email' === $engineField ? 'email' : $fallback;
    }

    /**
     * The address errors for a form, keyed by field (`shipping.city`).
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $address  The form's fields.
     * @param  string                $model    `shipping` or `billing`.
     *
     * @return array<string, string>
     */
    protected function addressErrors( array $address, string $model ): array
    {
        $value   = static fn ( string $field ): string => trim( (string) ( $address[ $field ] ?? '' ) );
        $country = strtoupper( $value( 'country_code' ) );
        $regions = AddressFormats::regions( $country );
        $errors  = [];

        if ( ! in_array( $country, Countries::CODES, true ) ) {
            $errors[ $model . '.country_code' ] = __( 'Choose a country.' );
        }

        foreach ( [ 'first_name' => __( 'Enter the first name.' ), 'last_name' => __( 'Enter the last name.' ), 'address1' => __( 'Enter the street address.' ), 'city' => __( 'Enter the city.' ) ] as $field => $message ) {
            if ( '' === $value( $field ) ) {
                $errors[ $model . '.' . $field ] = $message;
            }
        }

        foreach ( AddressForm::FIELDS as $field ) {
            if ( mb_strlen( $value( $field ) ) > 255 ) {
                $errors[ $model . '.' . $field ] = __( 'Keep this under 255 characters.' );
            }
        }

        if ( [] !== $regions && ! in_array( $value( 'region_code' ), array_column( $regions, 'id' ), true ) ) {
            $errors[ $model . '.region_code' ] = __( 'Choose a state or region.' );
        }

        $postcode = $value( 'postal_code' );
        $label    = AddressFormats::postcodeLabel( $country );

        if ( null !== AddressFormats::postcodePattern( $country ) ) {
            if ( '' === $postcode ) {
                $errors[ $model . '.postal_code' ] = __( 'Enter the :label.', [ 'label' => $label ] );
            } elseif ( ! AddressFormats::postcodeIsValid( $country, $postcode ) ) {
                $errors[ $model . '.postal_code' ] = (string) ( AddressFormats::postcodeHint( $country ) ?? __( 'Check the :label.', [ 'label' => $label ] ) );
            }
        }

        return $errors;
    }

    /**
     * A form's fields as the engine's address.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $address  The form's fields.
     *
     * @return Address
     */
    protected function toAddress( array $address ): Address
    {
        $fields  = [];
        $country = strtoupper( trim( (string) ( $address['country_code'] ?? '' ) ) );

        foreach ( AddressForm::FIELDS as $field ) {
            $value            = trim( (string) ( $address[ $field ] ?? '' ) );
            $fields[ $field ] = '' === $value ? null : $value;
        }

        $fields['country_code'] = $country;

        if ( [] !== AddressFormats::regions( $country ) && null !== $fields['region_code'] ) {
            $fields['region'] = AddressFormats::regionName( $country, $fields['region_code'] );
        } elseif ( [] === AddressFormats::regions( $country ) ) {
            $fields['region_code'] = null;
        }

        return Address::fromArray( $fields );
    }

    /**
     * An empty form, in the store's country.
     *
     * @since 1.0.0
     *
     * @return array<string, string|null>
     */
    protected function blankAddress(): array
    {
        $country = strtoupper( (string) config( 'artisanpack.ecommerce.store.country', 'US' ) );

        return array_merge( array_fill_keys( AddressForm::FIELDS, '' ), [ 'country_code' => in_array( $country, Countries::CODES, true ) ? $country : '' ] );
    }

    /**
     * A stored address as the form's fields.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $address  Stored address.
     *
     * @return array<string, string|null>
     */
    protected function formAddress( array $address ): array
    {
        $form = [];

        foreach ( AddressForm::FIELDS as $field ) {
            $form[ $field ] = is_scalar( $address[ $field ] ?? null ) ? (string) $address[ $field ] : '';
        }

        return $form;
    }

    /**
     * After an edit: a new country clears the region.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $address  The form's fields.
     * @param  string|null           $field    The changed field.
     *
     * @return array<string, mixed>
     */
    protected function addressChanged( array $address, ?string $field ): array
    {
        if ( 'country_code' === $field ) {
            $address['region_code'] = '';
            $address['region']      = '';
        }

        return $address;
    }

    /**
     * The shopper's customer record, when they are signed in and the cart
     * is theirs.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return Customer|null
     */
    protected function customer( Cart $cart ): ?Customer
    {
        $customer = Customer::forUser( auth()->user() );

        return null !== $customer && (int) $customer->id === (int) $cart->customer_id ? $customer : null;
    }

    /**
     * The shopper's saved addresses (none for guests).
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return \Illuminate\Support\Collection<int, CustomerAddress>
     */
    protected function addressBook( Cart $cart ): \Illuminate\Support\Collection
    {
        $customer = $this->customer( $cart );

        return null === $customer ? collect() : $customer->addresses()->orderByDesc( 'is_default_shipping' )->orderBy( 'id' )->limit( 50 )->get();
    }

    /**
     * The saved addresses as radio options (`id`, `name`, `hint`), with
     * "Use a new address" last.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return array<int, array{id: string, name: string, hint: string|null}>
     */
    protected function savedAddresses( Cart $cart ): array
    {
        $book = $this->addressBook( $cart );

        if ( $book->isEmpty() ) {
            return [];
        }

        $options = $book->map( static function ( CustomerAddress $address ): array {
            $name     = trim( implode( ' ', array_filter( [ $address->first_name, $address->last_name ] ) ) );
            $locality = implode( ', ', array_filter( [ $address->address1, $address->city, $address->region_code ?: $address->region, $address->postal_code, Countries::name( (string) $address->country_code ) ] ) );
            $label    = trim( (string) $address->label );

            return [
                'id'   => (string) $address->id,
                'name' => '' !== $label ? $label : ( '' !== $name ? $name : $locality ),
                'hint' => '' !== $label || '' !== $name ? $locality : null,
            ];
        } )->values()->all();

        $options[] = [ 'id' => 'new', 'name' => __( 'Use a new address' ), 'hint' => null ];

        return $options;
    }

    /**
     * A saved address's fields, or null for `new` or an address that isn't
     * the shopper's.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Address id.
     *
     * @return array<string, string|null>|null
     */
    protected function addressFromBook( string $id ): ?array
    {
        $cart = $this->cart();

        if ( null === $cart || ! ctype_digit( $id ) ) {
            return null;
        }

        $address = $this->addressBook( $cart )->firstWhere( 'id', (int) $id );

        return null === $address ? null : $this->formAddress( $address->toArray() );
    }

    /**
     * Saves a new address to the shopper's address book, when they asked.
     * A failure here doesn't hold up checkout.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $address  The form's fields.
     * @param  string                $saved    The saved-address choice (`new` for a new address).
     *
     * @return void
     */
    protected function saveToBook( array $address, string $saved ): void
    {
        $cart     = $this->cart();
        $customer = null === $cart ? null : $this->customer( $cart );

        if ( ! $this->saveToAddressBook || 'new' !== $saved || null === $customer ) {
            return;
        }

        try {
            app( CustomerAddressService::class )->create( $customer, $this->toAddress( $address )->toArray(), (int) auth()->id() );
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->toastWarning( __( 'We couldn\'t save the address to your address book.' ), __( 'Your order will still use it.' ) );

            return;
        }

        $this->saveToAddressBook = false;
    }

    /**
     * Keeps the shopper's marketing choice on the cart until the order is
     * placed.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return void
     */
    protected function storeMarketingConsent( Cart $cart ): void
    {
        $cart = $cart->fresh() ?? $cart;
        $meta = (array) ( $cart->meta ?? [] );

        if ( ( $meta[ self::MARKETING_CONSENT_META_KEY ] ?? false ) === $this->marketingConsent ) {
            return;
        }

        $meta[ self::MARKETING_CONSENT_META_KEY ] = $this->marketingConsent;

        $cart->meta = $meta;
        $cart->save();
    }

    /**
     * The gateways the shopper can choose, key => name.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return array<string, string>
     */
    protected function gatewayOptions( Cart $cart ): array
    {
        try {
            $gateways = $this->checkout()->availableGateways( $cart );
        } catch ( Throwable $exception ) {
            report( $exception );

            return [];
        }

        $options = [];

        foreach ( $gateways as $key => $gateway ) {
            if ( ! in_array( $key, $this->hiddenGateways, true ) ) {
                $options[ $key ] = $gateway->label();
            }
        }

        return $options;
    }

    /**
     * The chosen gateway's name, for the review step.
     *
     * @since 1.0.0
     *
     * @param  Cart|null  $cart  The cart.
     *
     * @return string|null
     */
    protected function gatewayLabel( ?Cart $cart ): ?string
    {
        if ( null !== $this->payment ) {
            return $this->payment['label'];
        }

        $key = null === $cart ? null : $cart->payment_gateway_key;

        return null === $key ? null : ( $this->gatewayOptions( $cart )[ (string) $key ] ?? null );
    }

    /**
     * The steps as the view shows them: `key`, `label`, `component`,
     * `number`, `complete`, `current`, and `reachable`.
     *
     * @since 1.0.0
     *
     * @param  array<string, array{key: string, label: string, component: string|null}>  $steps  The steps.
     * @param  Cart|null                                                                  $cart   The cart.
     *
     * @return array<int, array{key: string, label: string, component: string|null, number: int, complete: bool, current: bool, reachable: bool}>
     */
    protected function describeSteps( array $steps, ?Cart $cart ): array
    {
        if ( null === $cart || [] === $steps ) {
            return [];
        }

        $furthest  = $this->furthestStep( $cart );
        $reachable = true;
        $described = [];

        foreach ( array_values( $steps ) as $index => $step ) {
            $described[] = $step + [
                'number'    => $index + 1,
                'complete'  => $this->stepIsComplete( $step['key'], $steps, $cart ),
                'current'   => $step['key'] === $this->step,
                'reachable' => $reachable,
            ];

            if ( $step['key'] === $furthest ) {
                $reachable = false;
            }
        }

        return $described;
    }

    /**
     * Readable names for the error summary's fields.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function errorLabels(): array
    {
        $fields = [
            'first_name'   => __( 'First name' ),
            'last_name'    => __( 'Last name' ),
            'company'      => __( 'Company' ),
            'phone'        => __( 'Phone' ),
            'country_code' => __( 'Country' ),
            'address1'     => __( 'Address' ),
            'address2'     => __( 'Apartment, suite, etc.' ),
            'city'         => __( 'City' ),
            'region'       => __( 'State / region' ),
            'region_code'  => __( 'State / region' ),
            'postal_code'  => __( 'Postal code' ),
        ];

        $labels = [
            'email'        => __( 'Email' ),
            'shippingRate' => __( 'Shipping option' ),
            'gateway'      => __( 'Payment method' ),
            'orderNote'    => __( 'Order note' ),
            'acceptTerms'  => __( 'Terms and conditions' ),
            'password'     => __( 'Password' ),
            'review'       => __( 'Review order' ),
        ];

        foreach ( [ 'shipping' => __( 'Shipping address' ), 'billing' => __( 'Billing address' ) ] as $model => $section ) {
            foreach ( $fields as $field => $label ) {
                $labels[ $model . '.' . $field ] = __( ':section: :field', [ 'section' => $section, 'field' => $label ] );
            }
        }

        return $labels;
    }

    /**
     * Where a host auth route is, when it exists.
     *
     * @since 1.0.0
     *
     * @param  string  $key  `login_route` or `register_route`.
     *
     * @return string|null
     */
    protected function authUrl( string $key ): ?string
    {
        $name = config( 'artisanpack.ecommerce-storefront-livewire.auth.' . $key );

        return is_string( $name ) && '' !== $name && Route::has( $name ) ? route( $name ) : null;
    }

    /**
     * Makes checkout where a guest comes back to after signing in or
     * registering on the host's screens.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return void
     */
    protected function rememberCheckoutAsIntended( Cart $cart ): void
    {
        if ( $this->isGuest( $cart ) && app()->bound( 'session' ) && Route::has( 'artisanpack.ecommerce.storefront.checkout' ) ) {
            session()->put( 'url.intended', route( 'artisanpack.ecommerce.storefront.checkout' ) );
        }
    }

    /**
     * Whether the cart belongs to a guest.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return bool
     */
    protected function isGuest( Cart $cart ): bool
    {
        return null === $cart->customer_id;
    }

    /**
     * The lines the shopper chose (promotion-granted lines aside).
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return array<int, CartItem>
     */
    protected function paidLines( Cart $cart ): array
    {
        return $cart->items()->get()->reject( static fn ( CartItem $item ): bool => $item->isFreeItem() )->values()->all();
    }

    /**
     * Sets the live-region message to `$message` and the new total.
     *
     * @since 1.0.0
     *
     * @param  string  $message  What changed.
     *
     * @return void
     */
    protected function announceTotal( string $message ): void
    {
        $cart = $this->cart();

        $this->announcement = null === $cart
            ? $message
            : __( ':message Total: :total.', [ 'message' => $message, 'total' => $this->formatMoney( (int) $cart->total_amount, (string) $cart->currency ) ] );
    }
}
