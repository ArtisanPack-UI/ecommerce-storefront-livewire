<?php

/**
 * Account order detail component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account;

use ArtisanPackUI\Ecommerce\Exceptions\CartOperationException;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\DescribesOrder;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\OrderAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-account-order :order="$id" />`
 *
 * One of the shopper's orders (spec §7.5, S26): number, date, status,
 * lines, totals, addresses, payment method, shipments with their carrier
 * tracking links, refunds, notes the store shared, and downloads.
 *
 * The signed-in owner is authorized by the engine's order policy; anyone
 * else's order is a 404. Given a signed order-view `token` instead, the
 * component shows that order read-only, for guest order lookup (S31).
 *
 * "Buy again" adds the order's lines that can still be bought to the
 * shopper's cart, at today's prices, and says which ones couldn't be
 * (gone, hidden, or out of stock). Lines a promotion added for free are
 * skipped. It counts once against `ecommerce.cart.mutate`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class OrderShow extends Component
{
    use DescribesOrder;
    use InteractsWithStorefrontCart;
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * The order id.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $orderId = 0;

    /**
     * The signed order-view token, in read-only guest mode.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $orderToken = '';

    /**
     * Whether the order is shown read-only (a guest with a signed link).
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $readOnly = false;

    /**
     * Loads and authorizes the order.
     *
     * @since 1.0.0
     *
     * @param  int|string   $order  The order id.
     * @param  string|null  $token  A signed order-view token (guest lookup).
     *
     * @return void
     */
    public function mount( int|string $order, ?string $token = null ): void
    {
        $model = OrderAccess::owned( (string) $order );

        if ( null === $model && null !== $token && '' !== $token ) {
            $model               = OrderAccess::signed( (string) $order, $token );
            $this->readOnly      = true;
            $this->orderToken    = $token;
        }

        abort_if( null === $model, 404 );

        $this->orderId = (int) $model->id;
    }

    /**
     * Adds the order's lines that can still be bought to the cart, and
     * says which couldn't be.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function buyAgain(): void
    {
        if ( $this->readOnly ) {
            return;
        }

        $order  = $this->order();
        $result = $this->rateLimited( 'ecommerce.cart.mutate', fn (): array => $this->addLines( $order ) );

        if ( ! is_array( $result ) ) {
            return;
        }

        [ $added, $missing ] = $result;

        if ( $added > 0 ) {
            $this->cartChanged( $this->cart() );
        }

        if ( 0 === $added ) {
            $this->toastError( __( 'Nothing was added to your cart' ), __( 'None of the items in this order can be bought right now.' ) );
        } elseif ( [] !== $missing ) {
            $this->toastWarning(
                trans_choice( 'Added :count item to your cart|Added :count items to your cart', $added, [ 'count' => $added ] ),
                __( 'These can\'t be bought right now: :items', [ 'items' => implode( ', ', array_unique( $missing ) ) ] ),
            );
        } else {
            $this->toastSuccess( trans_choice( 'Added :count item to your cart|Added :count items to your cart', $added, [ 'count' => $added ] ) );
        }

        if ( $added > 0 ) {
            $this->dispatch( 'ecommerce-cart-open' );
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
        $order = $this->order();

        return view( 'ecommerce-storefront::livewire.account.order-show', [
            'order'         => $order,
            'status'        => $this->orderStatusLabel( $order ),
            'statusColor'   => $this->orderStatusColor( $order ),
            'date'          => $this->orderDate( $order ),
            'lines'         => $this->orderLines( $order ),
            'totals'        => $this->orderTotals( $order ),
            'paymentMethod' => $this->orderPaymentMethod( $order ),
            'downloads'     => $this->orderDownloads( $order ),
            'shipments'     => $this->orderShipments( $order ),
            'refunds'       => $this->orderRefunds( $order ),
            'notes'         => $this->orderNotes( $order ),
            'downloadsUrl'  => ! $this->readOnly && Route::has( 'artisanpack.ecommerce.account.downloads' ) ? route( 'artisanpack.ecommerce.account.downloads' ) : null,
            'ordersUrl'     => ! $this->readOnly && Route::has( 'artisanpack.ecommerce.account.orders.index' ) ? route( 'artisanpack.ecommerce.account.orders.index' ) : null,
            'canBuyAgain'   => ! $this->readOnly && $order->items->contains( static fn ( OrderItem $item ): bool => null !== $item->product_id && true !== ( $item->product_snapshot['free_item'] ?? false ) ),
        ] );
    }

    /**
     * The order, authorized again on every request.
     *
     * @since 1.0.0
     *
     * @return Order
     */
    protected function order(): Order
    {
        $order = $this->readOnly ? OrderAccess::signed( (string) $this->orderId, $this->orderToken ) : OrderAccess::owned( (string) $this->orderId );

        abort_if( null === $order, 404 );

        return $order;
    }

    /**
     * Adds each line the shopper paid for to the cart.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array{0: int, 1: array<int, string>} Lines added, and the names of those that couldn't be.
     */
    protected function addLines( Order $order ): array
    {
        $carts   = app( StorefrontCartService::class );
        $cart    = $this->cart( true );
        $added   = 0;
        $missing = [];

        foreach ( $order->items as $item ) {
            $snapshot = (array) ( $item->product_snapshot ?? [] );

            if ( true === ( $snapshot['free_item'] ?? false ) ) {
                continue;
            }

            $name = is_string( $snapshot['name'] ?? null ) && '' !== trim( $snapshot['name'] ) ? trim( $snapshot['name'] ) : __( 'Item' );

            if ( null === $item->product_id || null === $cart ) {
                $missing[] = $name;

                continue;
            }

            try {
                $carts->addItem( $cart, (int) $item->product_id, null === $item->product_variant_id ? null : (int) $item->product_variant_id, max( 1, (int) $item->quantity ), is_array( $snapshot['options'] ?? null ) ? $snapshot['options'] : [] );

                $added++;
            } catch ( CartOperationException ) {
                $missing[] = $name;
            } catch ( Throwable $exception ) {
                report( $exception );

                $missing[] = $name;
            }
        }

        return [ $added, $missing ];
    }
}
