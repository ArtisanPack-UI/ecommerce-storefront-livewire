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

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\Ecommerce\Services\CustomerStatsService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Contracts\CreatesCustomerAccounts;
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
 * password. The engine customer for the email is linked to the user
 * (`CustomerService::linkUser()`), the order just placed is attached to
 * that customer, and the user is signed in. `Registered` fires, so a
 * host that verifies email sends its verification message.
 *
 * Only the order just placed is attached: earlier guest orders under the
 * same email still need the claim flow, which verifies the shopper.
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

            $customer = $this->customers->linkUser( $user );
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
