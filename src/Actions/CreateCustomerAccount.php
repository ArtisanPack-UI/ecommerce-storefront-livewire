<?php

/**
 * Default account creation at checkout.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Actions;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\Ecommerce\Services\CustomerStatsService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Contracts\CreatesCustomerAccounts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutMarketingConsent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

/**
 * Creates the host user for a guest who asked for an account at checkout
 * (spec §7.4, D6).
 *
 * The user is an instance of `auth.providers.users.model` with `name`
 * (from the order's address, else the email), `email`, and the hashed
 * password, and is signed in. `Registered` fires, so a host that verifies
 * email sends its verification message.
 *
 * The checkout email isn't verified, so the user only gets a customer
 * record the engine's `CustomerService::customerForUser( $user, true )`
 * allows: a new one when no customer has the email yet, or the existing
 * one when the user's email is verified (`MustVerifyEmail`). An existing
 * customer record — saved addresses, orders, preferences someone else
 * built up under the email — is never handed to an unverified account;
 * the engine links it once the user verifies their email. The one
 * exception is a record this order's own marketing consent just created
 * ({@see CheckoutMarketingConsent}), which holds nothing else. When there
 * is no customer for the user, the order stays a guest order.
 *
 * The order just placed is attached to the customer. Earlier guest orders
 * under the same email still need the claim flow, which verifies the
 * shopper.
 *
 * Swap it through `auth.create_account_action` when registration needs
 * more (a username, extra consent, another user model).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class CreateCustomerAccount implements CreatesCustomerAccounts
{
    /**
     * @since 1.0.0
     *
     * @param  CustomerService       $customers  The engine's customers.
     * @param  CustomerStatsService  $stats      Customer order stats.
     */
    public function __construct(
        protected CustomerService $customers,
        protected CustomerStatsService $stats,
    ) {
    }

    /**
     * The application's default password rules.
     *
     * @since 1.0.0
     *
     * @return array<int, mixed>
     */
    public function passwordRules(): array
    {
        return [ 'required', 'string', 'max:255', Password::default() ];
    }

    /**
     * Whether no user has `$email` yet (compared case-insensitively).
     *
     * @since 1.0.0
     *
     * @param  string  $email  The order's email.
     *
     * @return bool
     */
    public function emailIsAvailable( string $email ): bool
    {
        return ! $this->userModel()->newQuery()->whereRaw( 'LOWER(email) = ?', [ mb_strtolower( trim( $email ) ) ] )->exists();
    }

    /**
     * Creates and signs in the user, and links the customer and order to
     * them.
     *
     * @since 1.0.0
     *
     * @param  Order   $order     The order just placed.
     * @param  string  $password  The password the shopper chose.
     *
     * @throws RuntimeException When a user already has the order's email.
     *
     * @return Authenticatable
     */
    public function create( Order $order, string $password ): Authenticatable
    {
        if ( ! $this->emailIsAvailable( (string) $order->email ) ) {
            throw new RuntimeException( 'A user already has this email.' );
        }

        [ $user, $customer, $attached ] = DB::transaction( function () use ( $order, $password ): array {
            $user = $this->userModel();

            $user->forceFill( [
                'name'     => $this->name( $order ),
                'email'    => (string) $order->email,
                'password' => Hash::make( $password ),
            ] )->save();

            $customer = $this->customers->customerForUser( $user, true ) ?? $this->consentCustomer( $order, $user );
            $attached = null !== $customer && 1 === Order::query()
                ->whereKey( $order->getKey() )
                ->whereNull( 'customer_id' )
                ->update( [ 'customer_id' => $customer->id, 'is_claimed' => true ] );

            return [ $user, $customer, $attached ];
        } );

        if ( $attached ) {
            doAction( 'ap.ecommerce.customer.orderClaimed', $customer, $order->refresh() );
            $this->stats->recalculate( $customer );
        }

        event( new Registered( $user ) );

        Auth::login( $user );

        if ( app()->bound( 'session' ) ) {
            session()->regenerate();
        }

        return $user;
    }

    /**
     * The guest customer record this order's marketing consent created,
     * linked to the new user; null when there is none (or it has since
     * been linked to someone else).
     *
     * @since 1.0.0
     *
     * @param  Order            $order  The order just placed.
     * @param  Authenticatable  $user   The new user.
     *
     * @return Customer|null
     */
    protected function consentCustomer( Order $order, Authenticatable $user ): ?Customer
    {
        $customer = Customer::query()
            ->whereNull( 'user_id' )
            ->where( 'email', mb_strtolower( trim( (string) $order->email ) ) )
            ->first();

        if ( null === $customer || ! CheckoutMarketingConsent::createdCustomerFor( $order->fresh() ?? $order, $customer ) ) {
            return null;
        }

        return $this->customers->linkUser( $user );
    }

    /**
     * A new, unsaved instance of the host's user model.
     *
     * @since 1.0.0
     *
     * @throws RuntimeException When the configured model isn't an authenticatable Eloquent model.
     *
     * @return Authenticatable&Model
     */
    protected function userModel(): Model&Authenticatable
    {
        $class = config( 'auth.providers.users.model' );
        $model = is_string( $class ) && class_exists( $class ) ? new $class() : null;

        if ( ! $model instanceof Model || ! $model instanceof Authenticatable ) {
            throw new RuntimeException( 'auth.providers.users.model must be an authenticatable Eloquent model.' );
        }

        return $model;
    }

    /**
     * The new user's name: the name on the order's billing (or shipping)
     * address, else the part of the email before the `@`.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string
     */
    protected function name( Order $order ): string
    {
        foreach ( [ $order->billing_address, $order->shipping_address ] as $address ) {
            $name = is_array( $address ) ? trim( implode( ' ', array_filter( [ $address['first_name'] ?? null, $address['last_name'] ?? null ], 'is_string' ) ) ) : '';

            if ( '' !== $name ) {
                return mb_substr( $name, 0, 255 );
            }
        }

        return mb_substr( (string) strstr( (string) $order->email, '@', true ) ?: (string) $order->email, 0, 255 );
    }
}
