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
 * Shows the engine's {@see EngineStockStatus} as an icon and text: "In
 * stock", "Low stock" (or "Only 2 left" with `catalog.show_stock_count` and
 * a known quantity), "Available on backorder", or "Out of stock". The text
 * always carries the meaning; colour only tints the icon (WCAG 1.4.1).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class StockStatus extends Component
{
    /**
     * The icon and its tint per state.
     *
     * @since 1.0.0
     *
     * @var array<string, array{icon: string, tint: string}>
     */
    public const STATES = [
        EngineStockStatus::IN_STOCK     => [ 'icon' => 'o-check-circle', 'tint' => 'text-success' ],
        EngineStockStatus::LOW_STOCK    => [ 'icon' => 'o-exclamation-triangle', 'tint' => 'text-warning' ],
        EngineStockStatus::BACKORDER    => [ 'icon' => 'o-clock', 'tint' => 'text-info' ],
        EngineStockStatus::OUT_OF_STOCK => [ 'icon' => 'o-x-circle', 'tint' => 'text-error' ],
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
     *
     * @param  EngineStockStatus|null           $status     The availability, when already known.
     * @param  Product|ProductVariant|null      $product    The product or variant to look up otherwise.
     * @param  bool|null                        $showCount  Say "Only 2 left"; defaults to `catalog.show_stock_count`.
     */
    public function __construct(
        public ?EngineStockStatus $status = null,
        public Product|ProductVariant|null $product = null,
        public ?bool $showCount = null,
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
