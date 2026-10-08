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
use ArtisanPackUI\Ecommerce\RateLimiting\RateLimitSubject;
use ArtisanPackUI\Ecommerce\Services\CurrentCart;
use ArtisanPackUI\Ecommerce\Support\GuestCartCookie;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

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
     * @since 1.0.0
     *
     * @return string
     */
    public function currency(): string
    {
        return $this->currencies->resolve( $this->request() );
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
