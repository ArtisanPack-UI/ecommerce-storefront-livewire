<?php

/**
 * Order confirmation component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Order;

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\DescribesOrder;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\OrderAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * `<livewire:artisanpack-ecommerce-storefront-order-confirmation :order="$id" :token="$token" />`
 *
 * The page a shopper lands on after placing an order (spec §7.4, S24),
 * and can come back to.
 *
 * The signed-in owner is authorized by the engine's order policy; anyone
 * else needs the signed order-view token checkout put in the link (E12).
 * Without either the page is a 404, so an order's existence isn't given
 * away. The page only reads the order: refreshing it, or coming back,
 * changes nothing.
 *
 * It shows the order number, status, lines, totals, addresses, payment
 * method, and the downloads digital lines unlocked. The owner gets a link
 * to the order in their account; a guest gets "create an account" and
 * "look up this order later" hints.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Confirmation extends Component
{
    use DescribesOrder;

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
     * The signed order-view token a guest came with.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $orderToken = '';

    /**
     * Whether the signed-in shopper owns the order.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $owner = false;

    /**
     * Loads and authorizes the order.
     *
     * @since 1.0.0
     *
     * @param  int|string   $order  The order id.
     * @param  string|null  $token  The signed order-view token (guests).
     *
     * @return void
     */
    public function mount( int|string $order, ?string $token = null ): void
    {
        $model       = OrderAccess::owned( (string) $order );
        $this->owner = null !== $model;
        $model ??= OrderAccess::signed( (string) $order, (string) $token );

        abort_if( null === $model, 404 );

        $this->orderId      = (int) $model->id;
        $this->orderToken   = $this->owner ? '' : (string) $token;
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

        return view( 'ecommerce-storefront::livewire.order.confirmation', [
            'order'         => $order,
            'status'        => $this->orderStatusLabel( $order ),
            'statusColor'   => $this->orderStatusColor( $order ),
            'date'          => $this->orderDate( $order ),
            'lines'         => $this->orderLines( $order ),
            'totals'        => $this->orderTotals( $order ),
            'paymentMethod' => $this->orderPaymentMethod( $order ),
            'downloads'     => $this->orderDownloads( $order ),
            'accountUrl'    => $this->owner && Route::has( 'artisanpack.ecommerce.account.orders.show' ) ? route( 'artisanpack.ecommerce.account.orders.show', [ 'order' => $order->id ] ) : null,
            'downloadsUrl'  => $this->owner && Route::has( 'artisanpack.ecommerce.account.downloads' ) ? route( 'artisanpack.ecommerce.account.downloads' ) : null,
            'registerUrl'   => $this->owner || auth()->check() ? null : $this->routeUrl( (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.register_route', '' ) ),
            'lookupUrl'     => $this->owner ? null : $this->routeUrl( 'artisanpack.ecommerce.storefront.lookup' ),
            'catalogUrl'    => $this->routeUrl( 'artisanpack.ecommerce.storefront.catalog' ),
        ] );
    }

    /**
     * The order, authorized again on every render.
     *
     * @since 1.0.0
     *
     * @return Order
     */
    protected function order(): Order
    {
        $order = $this->owner ? OrderAccess::owned( (string) $this->orderId ) : OrderAccess::signed( (string) $this->orderId, $this->orderToken );

        abort_if( null === $order, 404 );

        return $order;
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
