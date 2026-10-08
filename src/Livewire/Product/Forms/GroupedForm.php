<?php

/**
 * Grouped product purchase form.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms;

use ArtisanPackUI\Ecommerce\Inventory\StockStatus;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductChild;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-form-grouped :product="$product" />`
 *
 * A grouped product is never in the cart itself: each of its visible
 * products has its own price, stock, and quantity, and one "Add to cart"
 * adds every product with a quantity as its own line. A product the engine
 * refuses (out of stock, too many) shows the reason next to its quantity;
 * the others are still added.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class GroupedForm extends PurchaseForm
{
    /**
     * The quantity per child row id.
     *
     * @since 1.0.0
     *
     * @var array<int|string, int|string|null>
     */
    public array $quantities = [];

    /**
     * The visible children, for this request.
     *
     * @since 1.0.0
     *
     * @var Collection<int, ProductChild>|null
     */
    protected ?Collection $childCache = null;

    /**
     * Adds every product with a quantity.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addToCart(): void
    {
        $this->announcement = '';
        $children           = $this->children()->keyBy( 'id' );

        $this->validate(
            [ 'quantities.*' => [ 'nullable', 'integer', 'min:0', 'max:' . StorefrontCartService::MAX_LINE_QUANTITY ] ],
            [
                'quantities.*.integer' => __( 'Enter a whole number.' ),
                'quantities.*.min'     => __( 'Enter 0 or more.' ),
                'quantities.*.max'     => __( 'You can add at most :max at once.', [ 'max' => StorefrontCartService::MAX_LINE_QUANTITY ] ),
            ],
        );

        $lines = [];

        foreach ( $this->quantities as $childId => $quantity ) {
            $child = $children->get( (int) $childId );

            if ( null === $child || (int) $quantity < 1 ) {
                continue;
            }

            $lines[] = [
                'product_id' => (int) $child->child_product_id,
                'variant_id' => null === $child->child_variant_id ? null : (int) $child->child_variant_id,
                'quantity'   => (int) $quantity,
                'options'    => [],
                'field'      => 'quantities.' . $child->id,
            ];
        }

        if ( [] === $lines ) {
            $this->addError( 'quantities', __( 'Choose a quantity for at least one product.' ) );

            return;
        }

        if ( [] !== $this->addLines( $lines ) ) {
            $this->quantities = [];
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
        $currency = app( StorefrontCart::class )->currency();
        $prices   = app( PriceDisplayResolver::class );

        $rows = $this->children()->map( static function ( ProductChild $child ) use ( $currency, $prices ): array {
            $subject = $child->variant ?? $child->product;
            $stock   = StockStatus::for( $subject );
            $price   = $prices->for( $subject, $currency );

            return [
                'id'        => (int) $child->id,
                'name'      => null !== $child->variant?->name ? $child->product->name . ' — ' . $child->variant->name : (string) $child->product->name,
                'price'     => $price,
                'stock'     => $stock,
                'buyable'   => $stock->purchasable() && null !== $price,
            ];
        } )->all();

        return view( 'ecommerce-storefront::livewire.product.forms.grouped', [
            'rows'   => $rows,
            'canAdd' => [] !== array_filter( $rows, static fn ( array $row ): bool => $row['buyable'] ),
        ] );
    }

    /**
     * The group's storefront-visible products, in position order.
     *
     * @since 1.0.0
     *
     * @return Collection<int, ProductChild>
     */
    protected function children(): Collection
    {
        return $this->childCache ??= $this->product->children()
            ->with( [ 'product' => static fn ( $query ) => $query->storefrontVisible(), 'variant' ] )
            ->get()
            ->filter( static fn ( ProductChild $child ): bool => $child->product instanceof Product && ! $child->product->typeIsMissing() )
            ->each( static fn ( ProductChild $child ) => $child->variant?->setRelation( 'product', $child->product ) )
            ->values();
    }
}
