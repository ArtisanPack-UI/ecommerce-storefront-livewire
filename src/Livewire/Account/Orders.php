<?php

/**
 * Account order history component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Services\CustomerOrderHistory;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\DescribesOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-account-orders />`
 *
 * The shopper's order history (spec §7.5, S26), newest first, from the
 * engine's `CustomerOrderHistory`: each order's number (linking to its
 * detail), date, customer-facing status, total, and item count. The
 * status filter (open, completed, cancelled) and the page live in the
 * query string.
 *
 * Only the signed-in shopper's own orders are listed; a user the engine
 * has no customer record for sees an empty list.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Orders extends Component
{
    use DescribesOrder;
    use WithPagination;

    /**
     * Orders per page.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const PER_PAGE = 10;

    /**
     * The status group shown (a `CustomerOrderHistory::GROUPS` key), or
     * empty for every order.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( as: 'status', history: true, except: '' )]
    public string $status = '';

    /**
     * Ignores an unknown status from the query string.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->status = $this->validStatus( $this->status );
    }

    /**
     * Goes back to the first page when the filter changes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedStatus(): void
    {
        $this->status = $this->validStatus( $this->status );
        $this->resetPage();
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
        $customer = Customer::forUser( auth()->user() );
        $orders   = null;
        $failed   = false;

        if ( null !== $customer ) {
            try {
                $orders = app( CustomerOrderHistory::class )
                    ->query( $customer, '' === $this->status ? null : $this->status )
                    ->paginate( self::PER_PAGE )
                    ->through( fn ( Order $order ): array => $this->orderRow( $order ) );
            } catch ( Throwable $exception ) {
                report( $exception );

                $failed = true;
            }
        }

        return view( 'ecommerce-storefront::livewire.account.orders', [
            'orders'     => $orders,
            'failed'     => $failed,
            'filters'    => $this->filters(),
            'headers'    => [
                [ 'key' => 'number', 'label' => __( 'Order' ) ],
                [ 'key' => 'date', 'label' => __( 'Date' ) ],
                [ 'key' => 'status', 'label' => __( 'Status' ) ],
                [ 'key' => 'items', 'label' => __( 'Items' ) ],
                [ 'key' => 'total', 'label' => __( 'Total' ), 'class' => 'text-end' ],
            ],
            'catalogUrl' => Route::has( 'artisanpack.ecommerce.storefront.catalog' ) ? route( 'artisanpack.ecommerce.storefront.catalog' ) : null,
        ] );
    }

    /**
     * The status filter's options.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function filters(): array
    {
        return [
            [ 'id' => '', 'name' => __( 'All orders' ) ],
            [ 'id' => 'open', 'name' => __( 'Open' ) ],
            [ 'id' => 'completed', 'name' => __( 'Completed' ) ],
            [ 'id' => 'cancelled', 'name' => __( 'Cancelled or refunded' ) ],
        ];
    }

    /**
     * `$status` when it is a known group, else empty.
     *
     * @since 1.0.0
     *
     * @param  string  $status  The requested group.
     *
     * @return string
     */
    protected function validStatus( string $status ): string
    {
        return array_key_exists( $status, CustomerOrderHistory::GROUPS ) ? $status : '';
    }
}
