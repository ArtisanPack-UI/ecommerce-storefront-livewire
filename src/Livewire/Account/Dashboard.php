<?php

/**
 * Account dashboard component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Services\CustomerOrderHistory;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\Ecommerce\Services\DigitalDownloadService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\DescribesOrder;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-account-dashboard />`
 *
 * The account's front page (spec §7.5, S25): a greeting, the latest
 * orders, the default shipping and billing addresses, and how many
 * downloads the shopper has, with links to the account pages and to the
 * host's own profile and password screens (`auth.profile_route`,
 * `auth.password_route`; the host owns auth, D6).
 *
 * When guest orders were placed with the shopper's (verified) email and
 * haven't been claimed, it prompts them to claim those orders (S30).
 *
 * A signed-in user the engine has no customer record for yet (they
 * haven't ordered) gets the same page with nothing in it. When the engine
 * can't load part of it, the rest still shows with a notice.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Dashboard extends Component
{
    use DescribesOrder;

    /**
     * How many recent orders the dashboard lists.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const RECENT_ORDERS = 3;

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $customer = Customer::forUser( auth()->user() );
        $failed   = false;
        $orders   = [];
        $shipping = null;
        $billing  = null;
        $count    = 0;

        if ( null !== $customer ) {
            try {
                $orders = app( CustomerOrderHistory::class )->query( $customer )->limit( self::RECENT_ORDERS )->get()
                    ->map( fn ( Order $order ): array => $this->orderRow( $order ) )
                    ->all();

                $addresses = $customer->addresses()->where( fn ( $query ) => $query->where( 'is_default_shipping', true )->orWhere( 'is_default_billing', true ) )->get();
                $shipping  = $addresses->firstWhere( 'is_default_shipping', true );
                $billing   = $addresses->firstWhere( 'is_default_billing', true );
                $count     = app( DigitalDownloadService::class )->forCustomer( $customer )->count();
            } catch ( Throwable $exception ) {
                report( $exception );

                $failed = true;
            }
        }

        return view( 'ecommerce-storefront::livewire.account.dashboard', [
            'name'            => $this->greetingName( $customer ),
            'orders'          => $orders,
            'shippingAddress' => $shipping instanceof CustomerAddress ? $shipping->toArray() : null,
            'billingAddress'  => $billing instanceof CustomerAddress ? $billing->toArray() : null,
            'downloads'       => $count,
            'failed'          => $failed,
            'claimable'       => $this->claimableOrders( $customer ),
            'claimUrl'        => $this->routeUrl( 'artisanpack.ecommerce.account.claim' ),
            'ordersUrl'       => $this->routeUrl( 'artisanpack.ecommerce.account.orders.index' ),
            'addressesUrl'    => $this->routeUrl( 'artisanpack.ecommerce.account.addresses' ),
            'downloadsUrl'    => $this->routeUrl( 'artisanpack.ecommerce.account.downloads' ),
            'catalogUrl'      => $this->routeUrl( 'artisanpack.ecommerce.storefront.catalog' ),
            'profileUrl'      => $this->routeUrl( (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.profile_route', '' ) ),
            'passwordUrl'     => $this->routeUrl( (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.password_route', '' ) ),
        ] );
    }

    /**
     * Who the greeting names: the customer's first name, else the user's
     * name, else nobody.
     *
     * @since 1.0.0
     *
     * @param  Customer|null  $customer  The shopper's customer record.
     *
     * @return string|null
     */
    protected function greetingName( ?Customer $customer ): ?string
    {
        $user = auth()->user();

        foreach ( [ $customer?->first_name, is_string( $user?->name ?? null ) ? $user->name : null ] as $name ) {
            if ( is_string( $name ) && '' !== trim( $name ) ) {
                return trim( $name );
            }
        }

        return null;
    }

    /**
     * How many unclaimed guest orders were placed with the shopper's email.
     * None while the email is unverified, so an account can't learn what a
     * stranger's address ordered.
     *
     * @since 1.0.0
     *
     * @param  Customer|null  $customer  The shopper's customer record.
     *
     * @return int
     */
    protected function claimableOrders( ?Customer $customer ): int
    {
        $user  = auth()->user();
        $email = $customer?->email ?? ( is_string( $user?->email ?? null ) ? $user->email : null );

        if ( null === $user || ! is_string( $email ) || '' === trim( $email ) || ( $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail() ) ) {
            return 0;
        }

        try {
            return Order::query()
                ->whereNull( 'customer_id' )
                ->where( 'is_claimed', false )
                ->where( 'email', mb_strtolower( trim( $email ) ) )
                ->where( 'email', '!=', CustomerService::ANONYMIZED_EMAIL )
                ->count();
        } catch ( Throwable $exception ) {
            report( $exception );

            return 0;
        }
    }

    /**
     * A named route's URL, when the route exists.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Route name.
     *
     * @return string|null
     */
    protected function routeUrl( string $name ): ?string
    {
        return '' !== $name && Route::has( $name ) ? route( $name ) : null;
    }
}
