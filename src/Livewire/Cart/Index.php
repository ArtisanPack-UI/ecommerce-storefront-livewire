<?php

/**
 * Cart page component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart;

use ArtisanPackUI\Ecommerce\Exceptions\CartOperationException;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\Ecommerce\ValueObjects\Address;
use ArtisanPackUI\Ecommerce\ValueObjects\ShippingRate;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\ManagesCartLines;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\AddressFormats;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-cart />`
 *
 * The cart page (spec §7.3, S14), on the engine's `StorefrontCartService`:
 *
 * - **Lines** — image, name, options, unit price, a quantity stepper
 *   (debounced `updateItem()`), line total, and "Remove" with an "Undo"
 *   notice ({@see ManagesCartLines}).
 * - **Coupon** — `applyCoupon()` / `removeCoupon()`, with the engine's
 *   message under the field; attempts count against
 *   `ecommerce.coupon.attempt`.
 * - **Totals** — subtotal, each promotion's discount, shipping (the chosen
 *   estimate, "Free", or "Calculated at checkout"), tax (marked estimated
 *   until the engine knows a destination), and total. Changes are
 *   announced in a polite live region.
 * - **Shipping estimate** — country, region, and postcode, quoted through
 *   `quoteShipping()`; choosing a rate calls `selectShippingMethod()`, so
 *   it carries into checkout and lets the engine estimate tax.
 * - **Unsellable lines** — flagged with the reason and a "Remove" button;
 *   "Checkout" is disabled until they are gone.
 * - **Cross-sells** for the cart, and an empty state with "Continue
 *   shopping".
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use InteractsWithStorefrontCart;
    use ManagesCartLines;
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * The coupon code being entered.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $couponCode = '';

    /**
     * The shipping estimate's country.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $estimateCountry = '';

    /**
     * The shipping estimate's region code.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $estimateRegion = '';

    /**
     * The shipping estimate's postcode.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $estimatePostcode = '';

    /**
     * The quoted rates: `id`, `label`, `amount`, `currency`. Null until a
     * quote has been asked for.
     *
     * @since 1.0.0
     *
     * @var array<int, array{id: string, label: string, amount: int, currency: string}>|null
     */
    #[Locked]
    public ?array $rates = null;

    /**
     * The chosen rate id.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $selectedRate = '';

    /**
     * Picks up the quantities and the destination of a rate chosen
     * earlier.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->syncQuantities();

        $rate = $this->chosenRate( $this->cart() );

        if ( null !== $rate ) {
            $this->estimateCountry  = (string) ( $rate['destination']['country_code'] ?? '' );
            $this->estimateRegion   = (string) ( $rate['destination']['region_code'] ?? '' );
            $this->estimatePostcode = (string) ( $rate['destination']['postal_code'] ?? '' );
            $this->selectedRate     = (string) ( $rate['id'] ?? '' );
        }
    }

    /**
     * Follows changes made elsewhere (the cart drawer).
     *
     * @since 1.0.0
     *
     * @return void
     */
    #[On( 'ecommerce-cart-updated' )]
    public function cartUpdated(): void
    {
        $this->syncQuantities();
    }

    /**
     * Applies the entered coupon code.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function applyCoupon(): void
    {
        $this->resetErrorBag( 'couponCode' );
        $this->announcement = '';

        $code = trim( $this->couponCode );

        if ( '' === $code ) {
            $this->addError( 'couponCode', __( 'Enter a coupon code.' ) );

            return;
        }

        if ( mb_strlen( $code ) > 64 ) {
            $this->addError( 'couponCode', __( 'That coupon code is not valid.' ) );

            return;
        }

        $cart = $this->cart();

        if ( null === $cart ) {
            return;
        }

        try {
            $this->rateLimited( 'ecommerce.coupon.attempt', static fn (): Cart => app( StorefrontCartService::class )->applyCoupon( $cart, $code ) );
        } catch ( CartOperationException $exception ) {
            $this->addError( 'couponCode', $exception->getMessage() );

            return;
        }

        if ( $this->wasThrottled() ) {
            return;
        }

        $this->couponCode = '';
        $this->cartChanged( $cart );
        $this->announceTotal( __( 'Coupon applied.' ) );
    }

    /**
     * Removes an applied coupon.
     *
     * @since 1.0.0
     *
     * @param  string  $code  The applied code.
     *
     * @return void
     */
    public function removeCoupon( string $code ): void
    {
        $this->resetErrorBag( 'couponCode' );

        $cart = $this->cart();

        if ( null === $cart ) {
            return;
        }

        try {
            $this->rateLimited( 'ecommerce.cart.mutate', static fn (): Cart => app( StorefrontCartService::class )->removeCoupon( $cart, $code ) );
        } catch ( CartOperationException $exception ) {
            $this->addError( 'couponCode', $exception->getMessage() );

            return;
        }

        if ( $this->wasThrottled() ) {
            return;
        }

        $this->cartChanged( $cart );
        $this->announceTotal( __( 'Coupon removed.' ) );
    }

    /**
     * Clears the region, quotes, and chosen rate, which belong to the old
     * country.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedEstimateCountry(): void
    {
        $this->estimateRegion = '';
        $this->rates          = null;
        $this->selectedRate   = '';
    }

    /**
     * Quotes shipping rates for the estimate's destination.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function estimateShipping(): void
    {
        $this->resetErrorBag( [ 'estimateCountry', 'estimateRegion', 'estimatePostcode', 'selectedRate' ] );
        $this->rates = null;

        $destination = $this->destination();
        $cart        = $this->cart();

        if ( null === $destination || null === $cart ) {
            return;
        }

        try {
            $rates = $this->rateLimited(
                'ecommerce.cart.mutate',
                static fn (): array => app( StorefrontCartService::class )->quoteShipping( $cart, $destination )
                    ->map( static fn ( ShippingRate $rate ): array => [
                        'id'       => $rate->id(),
                        'label'    => $rate->label,
                        'amount'   => (int) $rate->amount->getAmount(),
                        'currency' => $rate->amount->getCurrency()->getCode(),
                    ] )
                    ->values()
                    ->all(),
            );
        } catch ( Throwable $exception ) {
            report( $exception );
            $this->addError( 'selectedRate', __( 'Shipping rates are unavailable right now. You can still check out.' ) );

            return;
        }

        if ( ! is_array( $rates ) ) {
            return;
        }

        $this->rates = $rates;

        if ( ! in_array( $this->selectedRate, array_column( $rates, 'id' ), true ) ) {
            $this->selectedRate = '';
        }

        $this->announcement = [] === $rates
            ? __( 'No shipping options for this address.' )
            : trans_choice( ':count shipping option found.|:count shipping options found.', count( $rates ), [ 'count' => count( $rates ) ] );
    }

    /**
     * Chooses one of the quoted rates.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedSelectedRate(): void
    {
        $this->resetErrorBag( 'selectedRate' );

        $destination = $this->destination();
        $cart        = $this->cart();

        if ( null === $destination || null === $cart || ! in_array( $this->selectedRate, array_column( $this->rates ?? [], 'id' ), true ) ) {
            return;
        }

        try {
            $this->rateLimited( 'ecommerce.cart.mutate', fn (): Cart => app( StorefrontCartService::class )->selectShippingMethod( $cart, $destination, $this->selectedRate ) );
        } catch ( CartOperationException $exception ) {
            $this->selectedRate = '';
            $this->addError( 'selectedRate', $exception->getMessage() );

            return;
        }

        if ( $this->wasThrottled() ) {
            return;
        }

        $this->cartChanged( $cart );
        $this->announceTotal( __( 'Shipping estimate updated.' ) );
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
        $cart  = $this->cart();
        $lines = null === $cart ? [] : $this->lines( $cart );

        return view( 'ecommerce-storefront::livewire.cart.index', [
            'cart'             => $cart,
            'lines'            => $lines,
            'totals'           => null === $cart || [] === $lines ? null : $this->totals( $cart ),
            'coupon'           => null === $cart ? null : $this->appliedCoupon( $cart ),
            'hasUnsellable'    => [] !== array_filter( $lines, static fn ( array $line ): bool => null !== $line['unsellable'] ),
            'requiresShipping' => null !== $cart && [] !== $lines && $this->requiresShipping( $cart ),
            'countries'        => Countries::options(),
            'regions'          => AddressFormats::regions( $this->estimateCountry ),
            'postcodeLabel'    => AddressFormats::postcodeLabel( $this->estimateCountry ),
            'catalogUrl'       => Route::has( 'artisanpack.ecommerce.storefront.catalog' ) ? route( 'artisanpack.ecommerce.storefront.catalog' ) : null,
            'checkoutUrl'      => Route::has( 'artisanpack.ecommerce.storefront.checkout' ) ? route( 'artisanpack.ecommerce.storefront.checkout' ) : null,
        ] );
    }

    /**
     * Announces the new total after a change to the lines.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function linesChanged(): void
    {
        $this->announceTotal( '' === $this->announcement ? __( 'Cart updated.' ) : $this->announcement );
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

        $this->announcement = null === $cart || 0 === (int) $cart->items()->count()
            ? $message
            : __( ':message Total: :total.', [ 'message' => $message, 'total' => $this->formatMoney( (int) $cart->total_amount, (string) $cart->currency ) ] );
    }

    /**
     * The coupon code applied to the cart, if any.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return string|null
     */
    protected function appliedCoupon( Cart $cart ): ?string
    {
        $code = ( (array) ( $cart->meta ?? [] ) )[ StorefrontCartService::COUPON_META_KEY ] ?? null;

        return is_string( $code ) && '' !== $code ? $code : null;
    }

    /**
     * The estimate's destination, or null (with errors) when it is
     * incomplete.
     *
     * @since 1.0.0
     *
     * @return Address|null
     */
    protected function destination(): ?Address
    {
        $country  = strtoupper( trim( $this->estimateCountry ) );
        $region   = trim( $this->estimateRegion );
        $postcode = mb_substr( trim( $this->estimatePostcode ), 0, 32 );
        $regions  = AddressFormats::regions( $country );

        if ( ! in_array( $country, Countries::CODES, true ) ) {
            $this->addError( 'estimateCountry', __( 'Choose a country.' ) );

            return null;
        }

        if ( '' !== $region && [] !== $regions && ! in_array( $region, array_column( $regions, 'id' ), true ) ) {
            $this->addError( 'estimateRegion', __( 'Choose a region from the list.' ) );

            return null;
        }

        return new Address(
            address1: '',
            city: '',
            countryCode: $country,
            regionCode: '' === $region ? null : mb_substr( $region, 0, 64 ),
            postalCode: '' === $postcode ? null : $postcode,
        );
    }
}
