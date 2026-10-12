<?php

/**
 * Storefront cart resolver.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Contracts\CurrencyResolver;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\RateLimiting\RateLimitSubject;
use ArtisanPackUI\Ecommerce\Services\CurrentCart;
use ArtisanPackUI\Ecommerce\Services\StoreCurrencies;
use ArtisanPackUI\Ecommerce\Support\GuestCartCookie;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Knows which cart belongs to the shopper (spec §5.2).
 *
 * Livewire components call engine services in-process, so nothing reads the
 * `X-Cart-Token` header the REST API uses. This class resolves the cart from
 * the guest-cart cookie (name and lifetime from the engine's `cart.cookie`
 * config) or the signed-in customer through the engine's `CurrentCart`, and
 * queues the cookie whenever a guest cart is created or its token changes.
 *
 * Bound per request (`scoped`), so the cart is looked up once and a cart
 * created during a request is found again before its cookie reaches the
 * browser.
 *
 * ```php
 * $cart = app( StorefrontCart::class )->current( create: true );
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class StorefrontCart
{
    /**
     * How long the currency cookie lasts, in minutes (a year).
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const CURRENCY_COOKIE_MINUTES = 525_600;

    /**
     * The cart resolved during this request.
     *
     * @since 1.0.0
     *
     * @var Cart|null
     */
    protected ?Cart $cart = null;

    /**
     * Whether a lookup has run, so a missing cart isn't looked up twice.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    protected bool $resolved = false;

    /**
     * The signed-in shopper's customer record, keyed by the user's
     * identifier, once looked up.
     *
     * @since 1.0.0
     *
     * @var array{0: mixed, 1: Customer|null}|null
     */
    protected ?array $customer = null;

    /**
     * @since 1.0.0
     *
     * @param  CurrentCart       $currentCart  The engine's cart resolver.
     * @param  CurrencyResolver  $currencies   The shopper's display currency.
     */
    public function __construct(
        protected CurrentCart $currentCart,
        protected CurrencyResolver $currencies,
    ) {
    }

    /**
     * The shopper's cart.
     *
     * With `$create`, a cart is created in the shopper's currency when none
     * exists (on the first add to cart), and a guest's token is queued as a
     * cookie on the response.
     *
     * @since 1.0.0
     *
     * @param  bool  $create  Create a cart when the shopper has none.
     *
     * @return Cart|null
     */
    public function current( bool $create = false ): ?Cart
    {
        if ( null !== $this->cart && $this->isOpen( $this->cart ) ) {
            return $this->cart;
        }

        if ( $this->resolved && ! $create ) {
            return null;
        }

        $token = $this->cookieToken();
        $cart  = $this->currentCart->resolve(
            $token,
            $this->user(),
            $create,
            $create ? $this->currency() : null,
        );

        $this->resolved = true;

        return $this->remember( $cart );
    }

    /**
     * Replaces the cart for the rest of the request and keeps the cookie in
     * step: a guest cart's token is queued (merges rotate tokens), and the
     * cookie is cleared once the cart belongs to an account.
     *
     * @since 1.0.0
     *
     * @param  Cart|null  $cart  The shopper's cart now.
     *
     * @return Cart|null
     */
    public function remember( ?Cart $cart ): ?Cart
    {
        $this->cart     = $cart;
        $this->resolved = true;

        if ( null === $cart ) {
            return null;
        }

        $token = $this->cookieToken();

        if ( null === $cart->customer_id ) {
            if ( $token !== (string) $cart->token ) {
                GuestCartCookie::queue( $cart );
            }
        } elseif ( null !== $token ) {
            GuestCartCookie::forget();
        }

        return $cart;
    }

    /**
     * Forgets the resolved cart, so the next call looks it up again.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function refresh(): void
    {
        $this->cart     = null;
        $this->resolved = false;
    }

    /**
     * How many units the cart holds.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function count(): int
    {
        $cart = $this->current();

        return null === $cart ? 0 : (int) $cart->items()->sum( 'quantity' );
    }

    /**
     * The signed-in shopper's engine customer, or null for a guest or a
     * user with no customer record. Looked up once per request (and again
     * if the signed-in user changes), for prices that depend on who is
     * buying (`PriceDisplayResolver::for()`'s `$customer`).
     *
     * @since 1.0.0
     *
     * @return Customer|null
     */
    public function customer(): ?Customer
    {
        $user = $this->user();
        $id   = $user?->getAuthIdentifier();

        if ( null === $this->customer || $this->customer[0] !== $id ) {
            $this->customer = [ $id, null === $user ? null : Customer::forUser( $user ) ];
        }

        return $this->customer[1];
    }

    /**
     * Who a rate-limited call counts against: the cart when the shopper has
     * one, else the signed-in shopper, else the client IP. Never the
     * `/livewire/update` route alone.
     *
     * @since 1.0.0
     *
     * @return RateLimitSubject
     */
    public function subject(): RateLimitSubject
    {
        $cart = $this->current();
        $user = $this->user();
        $ip   = $this->request()->ip() ?? '127.0.0.1';

        if ( null !== $user ) {
            $subject = RateLimitSubject::customer( $user, $ip );

            return null === $cart ? $subject : $subject->forCart( $cart );
        }

        return null === $cart ? RateLimitSubject::ip( $ip ) : RateLimitSubject::cart( $cart, $ip );
    }

    /**
     * The shopper's display currency.
     *
     * The open cart's currency when the shopper has a cart in an enabled
     * currency, so prices and availability are judged in the currency the
     * lines will be priced in (an account cart opened on another device,
     * or a merge into the account cart's currency, can leave it different
     * from the stored choice). Otherwise the engine's `CurrencyResolver`.
     * Only the currency switcher changes a cart's currency, since that
     * re-prices it.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function currency(): string
    {
        $cart     = $this->current();
        $currency = null === $cart ? '' : strtoupper( (string) $cart->currency );

        if ( '' !== $currency && app( StoreCurrencies::class )->isEnabled( $currency ) ) {
            return $currency;
        }

        return $this->currencies->resolve( $this->request() );
    }

    /**
     * Stores `$code` as the shopper's display currency: through the
     * resolver (the default resolver keeps it in the session) and in the
     * engine's `currency.cookie` cookie.
     *
     * The engine's `CurrencyResolver` contract only promises `resolve()`,
     * so the choice counts as kept only when the resolver now answers with
     * it.
     *
     * @since 1.0.0
     *
     * @param  string  $code  An ISO 4217 currency code.
     *
     * @return bool Whether the resolver now resolves to `$code`.
     */
    public function rememberCurrency( string $code ): bool
    {
        $code    = strtoupper( trim( $code ) );
        $request = $this->request();

        if ( method_exists( $this->currencies, 'remember' ) ) {
            $this->currencies->remember( $request, $code );
        }

        Cookie::queue( (string) config( 'artisanpack.ecommerce.currency.cookie', 'ecommerce_currency' ), $code, self::CURRENCY_COOKIE_MINUTES );

        return strtoupper( $this->currencies->resolve( $request ) ) === $code;
    }

    /**
     * The well-formed token in the guest-cart cookie, if any.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    protected function cookieToken(): ?string
    {
        return GuestCartCookie::tokenFrom( $this->request() );
    }

    /**
     * The signed-in shopper.
     *
     * @since 1.0.0
     *
     * @return Authenticatable|null
     */
    protected function user(): ?Authenticatable
    {
        return auth()->user();
    }

    /**
     * The current request, looked up on each call: Livewire and Octane may
     * swap it after this object was built.
     *
     * @since 1.0.0
     *
     * @return Request
     */
    protected function request(): Request
    {
        return app( 'request' );
    }

    /**
     * Whether a remembered cart can still be shopped with.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return bool
     */
    protected function isOpen( Cart $cart ): bool
    {
        return null === $cart->completed_order_id && ( null === $cart->expires_at || $cart->expires_at->isFuture() );
    }
}
