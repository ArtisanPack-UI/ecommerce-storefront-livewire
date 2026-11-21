<?php

/**
 * Bundled product purchase form.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductChild;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\DisplayData;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Money\Money;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-form-bundled :product="$product" />`
 *
 * A bundle is bought as one line at the bundle's own price (from the
 * engine, shown in the page summary). The form lists what it includes and
 * how many of each, then the quantity and "Add to cart". Stock follows the
 * bundle's members (the engine's `StockStatus`).
 *
 * Since 2.0.0 the members are a checked, disabled checkbox list ("In this
 * set", each "Included"), and a total line above "Add set to cart" shows
 * the line price with a "You save" badge when the members cost more on
 * their own.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class BundledForm extends PurchaseForm
{
    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $blockedReason = $this->blockedReason();

        $items = $this->product->children()->with( [ 'product', 'variant' ] )->get()
            ->filter( static fn ( ProductChild $child ): bool => null !== $child->product )
            ->map( static fn ( ProductChild $child ): array => [
                'id'       => (int) $child->id,
                'name'     => null !== $child->variant?->name ? $child->product->name . ' — ' . $child->variant->name : (string) $child->product->name,
                'quantity' => max( 1, (int) $child->quantity ),
                'subject'  => $child->variant ?? $child->product,
            ] )
            ->values()
            ->all();

        $line   = null === $blockedReason ? $this->lineMoney() : null;
        $saving = null === $line ? null : $this->saving( $items );

        return view( 'ecommerce-storefront::livewire.product.forms.bundled', [
            'items'         => array_map( static fn ( array $item ): array => array_diff_key( $item, [ 'subject' => true ] ), $items ),
            'total'         => null === $line ? null : MoneyFormatter::formatMoney( $line ),
            'saving'        => null === $saving ? null : MoneyFormatter::formatMoney( $saving ),
            'canAdd'        => null === $blockedReason,
            'blockedReason' => $blockedReason,
        ] );
    }

    /**
     * The bundle's own price, so the total follows the quantity.
     *
     * @since 2.0.0
     *
     * @return Money|null
     */
    protected function unitPrice(): ?Money
    {
        $cart  = app( StorefrontCart::class );
        $price = app( PriceDisplayResolver::class )->for( DisplayData::for( $this->product ), $cart->currency(), null, $cart->customer() );

        return null === $price || $price->isRange() ? null : $price->price;
    }

    /**
     * What the shopper saves on the line against buying the members on
     * their own: each member's single price times its quantity in the
     * bundle, less the bundle price, times the quantity. Null when a member
     * has no single price in the shopper's currency or there is no saving.
     *
     * @since 2.0.0
     *
     * @param  array<int, array{quantity: int, subject: Product|ProductVariant}>  $items  The members.
     *
     * @return Money|null
     */
    protected function saving( array $items ): ?Money
    {
        $unit = $this->cachedUnitPrice();

        if ( null === $unit || [] === $items ) {
            return null;
        }

        $cart    = app( StorefrontCart::class );
        $prices  = app( PriceDisplayResolver::class );
        $members = null;

        foreach ( $items as $item ) {
            $price = $prices->for( $item['subject'], $cart->currency(), null, $cart->customer() );

            if ( null === $price || $price->isRange() || ! $price->price->isSameCurrency( $unit ) ) {
                return null;
            }

            $cost    = $price->price->multiply( (string) $item['quantity'] );
            $members = null === $members ? $cost : $members->add( $cost );
        }

        if ( null === $members || ! $members->greaterThan( $unit ) ) {
            return null;
        }

        $quantity = (int) filter_var( $this->quantity, FILTER_VALIDATE_INT );

        return $members->subtract( $unit )->multiply( (string) max( 1, $quantity ) );
    }
}
