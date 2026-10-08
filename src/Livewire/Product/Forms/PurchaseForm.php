<?php

/**
 * Purchase form base component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms;

use ArtisanPackUI\Ecommerce\Exceptions\CartOperationException;
use ArtisanPackUI\Ecommerce\Inventory\StockStatus;
use ArtisanPackUI\Ecommerce\Models\CartItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The base for every purchase form on the product page (spec §7.2, §8.2).
 *
 * It holds the product and quantity and adds lines to the shopper's cart
 * through the engine's `StorefrontCartService`: the batch counts once
 * against the `ecommerce.cart.mutate` limit, an engine
 * `CartOperationException` is shown on the line's field, and on success it
 * dispatches `ecommerce-cart-updated`, announces the addition in a live
 * region, and then (per `cart.after_add`) dispatches `ecommerce-cart-open`
 * for the cart drawer or shows a toast.
 *
 * The default `addToCart()` adds `quantity` of the product with
 * {@see self::lineVariantId()} and {@see self::lineOptions()}; override
 * those (or `addToCart()` itself) in a type's form. Forms registered in the
 * `ProductFormRegistry` should extend this class.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
abstract class PurchaseForm extends Component
{
    use InteractsWithStorefrontCart;
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * The product being bought.
     *
     * @since 1.0.0
     *
     * @var Product
     */
    #[Locked]
    public Product $product;

    /**
     * How many to add. Starts at 1 in mount(): with a property default,
     * Livewire would restore it when the shopper empties the field.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $quantity = null;

    /**
     * The polite live-region message after an addition.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $announcement = '';

    /**
     * Starts the quantity at 1. Forms that mount call this first.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->quantity = 1;
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    abstract public function render(): View;

    /**
     * Adds `quantity` of the product to the cart.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addToCart(): void
    {
        $this->announcement = '';
        $this->validateQuantity();

        if ( ! $this->readyToAdd() ) {
            return;
        }

        $this->addLines( [ [
            'product_id' => (int) $this->product->id,
            'variant_id' => $this->lineVariantId(),
            'quantity'   => (int) $this->quantity,
            'options'    => $this->lineOptions(),
            'field'      => 'quantity',
        ] ] );
    }

    /**
     * Why the product can't be bought now (out of stock, or no price in the
     * shopper's currency), or null when it can.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function blockedReason(): ?string
    {
        if ( ! StockStatus::for( $this->product )->purchasable() ) {
            return __( 'This product is out of stock.' );
        }

        if ( null === app( PriceDisplayResolver::class )->for( $this->product, app( StorefrontCart::class )->currency() ) ) {
            return __( 'This product isn\'t available to buy right now.' );
        }

        return null;
    }

    /**
     * Validates the quantity (a whole number from 1 to the engine's line
     * limit).
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function validateQuantity(): void
    {
        $this->validate(
            [ 'quantity' => [ 'required', 'integer', 'min:1', 'max:' . StorefrontCartService::MAX_LINE_QUANTITY ] ],
            [
                'quantity.required' => __( 'Enter a quantity.' ),
                'quantity.integer'  => __( 'Enter a whole number.' ),
                'quantity.min'      => __( 'Add at least 1.' ),
                'quantity.max'      => __( 'You can add at most :max at once.', [ 'max' => StorefrontCartService::MAX_LINE_QUANTITY ] ),
            ],
        );
    }

    /**
     * Whether the form has everything it needs (a variant chosen, …);
     * adds its own errors when not.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function readyToAdd(): bool
    {
        return true;
    }

    /**
     * The variant to add.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    protected function lineVariantId(): ?int
    {
        return null;
    }

    /**
     * The cart line options to add (validated by the engine product type).
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function lineOptions(): array
    {
        return [];
    }

    /**
     * Adds lines to the cart, one rate-limited batch. Each line is
     * `product_id`, `variant_id`, `quantity`, `options`, and `field` (where
     * its engine error is shown). Lines the engine refuses are skipped; the
     * rest are added.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{product_id: int, variant_id: int|null, quantity: int, options: array<string, mixed>, field: string}>  $lines  Lines.
     *
     * @return array<int, CartItem> The added lines.
     */
    protected function addLines( array $lines ): array
    {
        $added = $this->rateLimited( 'ecommerce.cart.mutate', function () use ( $lines ): array {
            $cart    = $this->cart( true );
            $service = app( StorefrontCartService::class );
            $items   = [];

            foreach ( $lines as $line ) {
                try {
                    $items[] = $service->addItem( $cart, $line['product_id'], $line['variant_id'], $line['quantity'], $line['options'] );
                } catch ( CartOperationException $exception ) {
                    $this->addError( $line['field'], $exception->getMessage() );
                }
            }

            return $items;
        } );

        if ( ! is_array( $added ) || [] === $added ) {
            return [];
        }

        $this->added( $added );

        return $added;
    }

    /**
     * Announces added lines and opens the drawer (or toasts).
     *
     * @since 1.0.0
     *
     * @param  array<int, CartItem>  $items  The added lines.
     *
     * @return void
     */
    protected function added( array $items ): void
    {
        $last = end( $items );

        // Loaded explicitly so hosts that prevent lazy loading don't throw.
        $last->loadMissing( 'cart' );

        $this->cartChanged( $last->cart );

        $this->announcement = __( 'Added to your cart.' );

        match ( (string) config( 'artisanpack.ecommerce-storefront-livewire.cart.after_add', 'drawer' ) ) {
            'toast' => $this->toastSuccess(
                __( 'Added to your cart' ),
                __( ':name is in your cart.', [ 'name' => (string) $this->product->name ] ),
            ),
            'none'  => null,
            default => $this->dispatch( 'ecommerce-cart-open' ),
        };
    }
}
