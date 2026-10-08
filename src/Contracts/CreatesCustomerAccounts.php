<?php

/**
 * Account creation at checkout contract.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Contracts;

use ArtisanPackUI\Ecommerce\Models\Order;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Creates the host user when a guest asks for an account at checkout
 * (spec §7.4, D6).
 *
 * The class named by `auth.create_account_action` implements this. The
 * checkout checks {@see self::passwordRules()} and
 * {@see self::emailIsAvailable()} before the order is placed, so a
 * shopper isn't told about a problem only after paying, then calls
 * {@see self::create()} once the order is paid. Hosts with extra
 * registration rules (terms, a username, a verification step) swap in
 * their own class.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
interface CreatesCustomerAccounts
{
    /**
     * The validation rules for the password the shopper chose.
     *
     * @since 1.0.0
     *
     * @return array<int, mixed>
     */
    public function passwordRules(): array;

    /**
     * Whether an account can be created for `$email` (no user has it yet).
     *
     * @since 1.0.0
     *
     * @param  string  $email  The order's email.
     *
     * @return bool
     */
    public function emailIsAvailable( string $email ): bool;

    /**
     * Creates the user for the order's email, links the engine customer and
     * the order to them, and signs them in.
     *
     * @since 1.0.0
     *
     * @param  Order   $order     The order just placed.
     * @param  string  $password  The password the shopper chose.
     *
     * @return Authenticatable The new user.
     */
    public function create( Order $order, string $password ): Authenticatable;
}
