<?php

/**
 * Stock status component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\Ecommerce\Inventory\StockStatus as EngineStockStatus;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-stock-status :status="$stock" />` or `:product="$product"`
 *
 * Shows the engine's {@see EngineStockStatus} as a design-system Badge: a
 * soft badge with an icon and a label, "In stock", "Low stock" (or "Only 2
 * left" with `catalog.show_stock_count` and a known quantity), "Available
 * on backorder", or "Out of stock". The label always carries the meaning;
 * the color and icon only reinforce it (WCAG 1.4.1). With `compact` (the
 * product card) it's a ghost badge with a dot, and "In stock" is not shown.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class StockStatus extends Component
{
    /**
     * The icon and the design-system color per state.
     *
     * @since 1.0.0
     * @since 2.0.0 Each state names a design-system badge `color` and a
     *              registry icon instead of a Tailwind tint class.
     *
     * @var array<string, array{icon: string, color: string}>
     */
    public const STATES = [
        EngineStockStatus::IN_STOCK     => [ 'icon' => 'circle-check', 'color' => 'success' ],
        EngineStockStatus::LOW_STOCK    => [ 'icon' => 'circle-alert', 'color' => 'warning' ],
        EngineStockStatus::BACKORDER    => [ 'icon' => 'clock', 'color' => 'info' ],
        EngineStockStatus::OUT_OF_STOCK => [ 'icon' => 'x', 'color' => 'error' ],
    ];

    /**
     * The resolved availability.
     *
     * @since 1.0.0
     *
     * @var EngineStockStatus
     */
    public EngineStockStatus $stock;

    /**
     * @since 1.0.0
     * @since 2.0.0 Added `compact`.
     *
     * @param  EngineStockStatus|null           $status     The availability, when already known.
     * @param  Product|ProductVariant|null      $product    The product or variant to look up otherwise.
     * @param  bool|null                        $showCount  Say "Only 2 left"; defaults to `catalog.show_stock_count`.
     * @param  bool                             $compact    The product card's ghost badge with a dot, which hides "In stock".
     */
    public function __construct(
        public ?EngineStockStatus $status = null,
        public Product|ProductVariant|null $product = null,
        public ?bool $showCount = null,
        public bool $compact = false,
    ) {
        $this->stock = $this->status
            ?? ( null === $this->product ? new EngineStockStatus( EngineStockStatus::IN_STOCK ) : EngineStockStatus::for( $this->product ) );

        $this->showCount ??= (bool) config( 'artisanpack.ecommerce-storefront-livewire.catalog.show_stock_count', false );
    }

    /**
     * The text shown for the state.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function label(): string
    {
        return match ( $this->stock->status ) {
            EngineStockStatus::LOW_STOCK    => $this->showCount && null !== $this->stock->quantity
                ? trans_choice( 'Only :count left|Only :count left', $this->stock->quantity, [ 'count' => $this->stock->quantity ] )
                : __( 'Low stock' ),
            EngineStockStatus::BACKORDER    => __( 'Available on backorder' ),
            EngineStockStatus::OUT_OF_STOCK => __( 'Out of stock' ),
            default                         => __( 'In stock' ),
        };
    }

    /**
     * The icon and tint for the state.
     *
     * @since 1.0.0
     *
     * @return array{icon: string, tint: string}
     */
    public function state(): array
    {
        return self::STATES[ $this->stock->status ] ?? self::STATES[ EngineStockStatus::IN_STOCK ];
    }

    /**
     * Whether anything shows: the compact form leaves out "In stock".
     *
     * @since 2.0.0
     *
     * @return bool
     */
    public function visible(): bool
    {
        return ! $this->compact || EngineStockStatus::IN_STOCK !== $this->stock->status;
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
        return view( 'ecommerce-storefront::components.stock-status' );
    }
}
