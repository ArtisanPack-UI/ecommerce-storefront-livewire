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
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
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
 * Since 2.0.0 the products are a table (item, price, quantity) with a
 * running "2 items · $56.00" line above "Add selected to cart" (#101); the
 * quantities bind on change so the line follows them.
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
        $this->startAdding();
        $children = $this->children()->keyBy( 'id' );

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
            // Keep the quantities the engine refused, next to their errors.
            $errors           = $this->getErrorBag();
            $this->quantities = array_filter( $this->quantities, static fn ( mixed $quantity, int|string $childId ): bool => $errors->has( 'quantities.' . $childId ), ARRAY_FILTER_USE_BOTH );
        }
    }

    /**
     * Each grouped product has its own stock, shown next to its quantity,
     * so the group never offers one back-in-stock signup.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    public function outOfStock(): bool
    {
        return false;
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
        $cart     = app( StorefrontCart::class );
        $currency = $cart->currency();
        $customer = $cart->customer();
        $prices   = app( PriceDisplayResolver::class );

        $rows = $this->children()->map( static function ( ProductChild $child ) use ( $currency, $customer, $prices ): array {
            $subject = $child->variant ?? $child->product;
            $stock   = StockStatus::for( $subject );
            $price   = $prices->for( $subject, $currency, null, $customer );

            return [
                'id'      => (int) $child->id,
                'name'    => null !== $child->variant?->name ? $child->product->name . ' — ' . $child->variant->name : (string) $child->product->name,
                'price'   => $price,
                'stock'   => $stock,
                'buyable' => $stock->purchasable() && null !== $price,
            ];
        } )->all();

        return view( 'ecommerce-storefront::livewire.product.forms.grouped', [
            'rows'    => $rows,
            'canAdd'  => [] !== array_filter( $rows, static fn ( array $row ): bool => $row['buyable'] ),
            'summary' => $this->selectionSummary( $rows ),
        ] );
    }

    /**
     * The running line above "Add selected to cart": how many items have a
     * quantity and what they cost together. An empty or 0 quantity is
     * skipped. The total is null when a product has no single price (a
     * range) or a filled-in quantity isn't a whole number from 1 to the line
     * limit, since "Add selected to cart" would refuse it; the count is then
     * of the valid quantities only.
     *
     * @since 2.0.0
     *
     * @param  array<int, array<string, mixed>>  $rows  The rows from {@see self::render()}.
     *
     * @return array{count: int, total: string|null}
     */
    protected function selectionSummary( array $rows ): array
    {
        $count = 0;
        $total = null;
        $known = true;

        foreach ( $rows as $row ) {
            $raw = $this->quantities[ $row['id'] ] ?? null;

            if ( ! $row['buyable'] || null === $raw || '' === $raw || 0 === $raw || '0' === $raw ) {
                continue;
            }

            $quantity = is_int( $raw ) || is_string( $raw ) ? filter_var( $raw, FILTER_VALIDATE_INT ) : false;

            if ( false === $quantity || $quantity < 0 || $quantity > StorefrontCartService::MAX_LINE_QUANTITY ) {
                $known = false;

                continue;
            }

            if ( 0 === $quantity ) {
                continue;
            }

            $count += $quantity;
            $price  = $row['price'];

            if ( $price->isRange() ) {
                $known = false;

                continue;
            }

            $line  = $price->price->multiply( (string) $quantity );
            $total = null === $total ? $line : $total->add( $line );
        }

        return [
            'count' => $count,
            'total' => $known && null !== $total ? MoneyFormatter::formatMoney( $total ) : null,
        ];
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
