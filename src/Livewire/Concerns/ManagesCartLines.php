<?php

/**
 * Cart line concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Exceptions\CartOperationException;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\CartItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;

/**
 * Lists the shopper's cart lines and lets them change quantities, remove
 * lines, and undo a removal (the cart page and the cart drawer).
 *
 * Quantities live in `$quantities` (keyed by line id) and are bound with
 * `wire:model.live.debounce` to the quantity stepper; each change goes to
 * the engine's `StorefrontCartService::updateItem()` (0 removes the line),
 * counts against `ecommerce.cart.mutate`, and shows the engine's message
 * under the line's field (`quantities.{id}`) when it is refused.
 *
 * Lines are listed by {@see DescribesCart::lines()} with the reason a line
 * can't be bought, when it can't: its product was deleted, unpublished, or
 * its type uninstalled, its option is gone, or it is out of stock.
 *
 * Uses {@see InteractsWithStorefrontCart}, {@see RateLimitsStorefront}, and
 * {@see SendsToasts}. Call {@see self::syncQuantities()} from `mount()`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
trait ManagesCartLines
{
    use DescribesCart;

    /**
     * Each line's quantity, keyed by line id.
     *
     * @since 1.0.0
     *
     * @var array<int|string, int|string|null>
     */
    public array $quantities = [];

    /**
     * The line removed last, for "Undo": `product_id`, `variant_id`,
     * `quantity`, `options`, and `name`.
     *
     * @since 1.0.0
     *
     * @var array{product_id: int, variant_id: int|null, quantity: int, options: array<string, mixed>, name: string}|null
     */
    #[Locked]
    public ?array $removedLine = null;

    /**
     * The polite live-region message after a change.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $announcement = '';

    /**
     * Sets `$quantities` from the cart.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function syncQuantities(): void
    {
        $cart = $this->cart();

        $this->quantities = null === $cart
            ? []
            : $cart->items()->pluck( 'quantity', 'id' )->map( static fn ( $quantity ): int => (int) $quantity )->all();
    }

    /**
     * Sends a changed quantity to the engine.
     *
     * @since 1.0.0
     *
     * @param  mixed       $value  The new quantity.
     * @param  int|string  $key    The line id.
     *
     * @return void
     */
    public function updatedQuantities( mixed $value, int|string $key ): void
    {
        $itemId = (int) $key;
        $field  = 'quantities.' . $itemId;

        $this->resetErrorBag( $field );
        $this->announcement = '';

        $validator = Validator::make(
            [ 'quantity' => $value ],
            [ 'quantity' => [ 'required', 'integer', 'min:0', 'max:' . StorefrontCartService::MAX_LINE_QUANTITY ] ],
            [
                'quantity.required' => __( 'Enter a quantity.' ),
                'quantity.integer'  => __( 'Enter a whole number.' ),
                'quantity.min'      => __( 'Enter 0 or more.' ),
                'quantity.max'      => __( 'You can add at most :max at once.', [ 'max' => StorefrontCartService::MAX_LINE_QUANTITY ] ),
            ],
        );

        if ( $validator->fails() ) {
            $this->addError( $field, (string) $validator->errors()->first( 'quantity' ) );

            return;
        }

        $cart = $this->cart();
        $item = null === $cart ? null : $this->ownLine( $cart, $itemId );

        if ( null === $cart || null === $item ) {
            $this->syncQuantities();

            return;
        }

        $quantity = (int) $value;

        if ( $quantity === (int) $item->quantity ) {
            return;
        }

        if ( 0 === $quantity ) {
            $this->removeLine( $itemId );

            return;
        }

        try {
            $this->rateLimited( 'ecommerce.cart.mutate', static fn (): ?CartItem => app( StorefrontCartService::class )->updateItem( $cart, $item, $quantity ) );
        } catch ( CartOperationException $exception ) {
            $this->quantities[ $itemId ] = (int) $item->quantity;
            $this->addError( $field, $exception->getMessage() );

            return;
        }

        if ( $this->wasThrottled() ) {
            $this->quantities[ $itemId ] = (int) $item->quantity;

            return;
        }

        $this->cartChanged( $cart );
        $this->linesChanged();
    }

    /**
     * Removes a line, keeping it for "Undo".
     *
     * @since 1.0.0
     *
     * @param  int  $itemId  The line id.
     *
     * @return void
     */
    public function removeLine( int $itemId ): void
    {
        $this->announcement = '';

        $cart = $this->cart();
        $item = null === $cart ? null : $this->ownLine( $cart, $itemId );

        if ( null === $cart || null === $item ) {
            $this->syncQuantities();

            return;
        }

        $item->loadMissing( 'product' );

        $removed = [
            'product_id' => (int) $item->product_id,
            'variant_id' => null === $item->product_variant_id ? null : (int) $item->product_variant_id,
            'quantity'   => (int) $item->quantity,
            'options'    => (array) ( $item->options ?? [] ),
            'name'       => (string) ( $item->product?->name ?? __( 'Item' ) ),
        ];

        try {
            $this->rateLimited( 'ecommerce.cart.mutate', static function () use ( $cart, $item ): bool {
                app( StorefrontCartService::class )->removeItem( $cart, $item );

                return true;
            } );
        } catch ( CartOperationException $exception ) {
            $this->addError( 'quantities.' . $itemId, $exception->getMessage() );

            return;
        }

        if ( $this->wasThrottled() ) {
            return;
        }

        // A line whose product is gone can't be added back.
        $this->removedLine  = null === $item->product ? null : $removed;
        $this->announcement = __( ':name was removed from your cart.', [ 'name' => $removed['name'] ] );

        $this->cartChanged( $cart );
        $this->syncQuantities();
        $this->linesChanged();

        $this->toastSuccess( __( 'Removed from your cart' ), __( ':name was removed from your cart.', [ 'name' => $removed['name'] ] ) );
    }

    /**
     * Adds the line removed last back to the cart.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function undoRemove(): void
    {
        $line = $this->removedLine;

        $this->removedLine  = null;
        $this->announcement = '';

        if ( null === $line ) {
            return;
        }

        try {
            $item = $this->rateLimited(
                'ecommerce.cart.mutate',
                fn (): CartItem => app( StorefrontCartService::class )->addItem( $this->cart( true ), $line['product_id'], $line['variant_id'], $line['quantity'], $line['options'] ),
            );
        } catch ( CartOperationException $exception ) {
            $this->toastError( __( ':name could not be added back.', [ 'name' => $line['name'] ] ), $exception->getMessage() );

            return;
        }

        if ( ! $item instanceof CartItem ) {
            $this->removedLine = $line;

            return;
        }

        $item->loadMissing( 'cart' );

        $this->announcement = __( ':name is back in your cart.', [ 'name' => $line['name'] ] );

        $this->cartChanged( $item->cart );
        $this->syncQuantities();
        $this->linesChanged();
    }

    /**
     * Runs after a quantity change, removal, or undo; the cart page uses
     * it to announce the new total.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function linesChanged(): void
    {
    }

    /**
     * A line of the shopper's cart.
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart    The cart.
     * @param  int   $itemId  The line id.
     *
     * @return CartItem|null
     */
    protected function ownLine( Cart $cart, int $itemId ): ?CartItem
    {
        return $cart->items()->whereKey( $itemId )->first();
    }
}
