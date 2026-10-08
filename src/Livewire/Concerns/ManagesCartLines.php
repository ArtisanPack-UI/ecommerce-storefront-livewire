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
use ArtisanPackUI\Ecommerce\Inventory\StockStatus;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\CartItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
 * Lines are listed by {@see self::lines()} with the reason a line can't be
 * bought, when it can't: its product was deleted, unpublished, or its type
 * uninstalled, its option is gone, or it is out of stock.
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

    /**
     * The cart's lines as the views show them.
     *
     * Keys: `id`, `name`, `url`, `image`, `options` (strings), `quantity`,
     * `unit` and `total` (minor units), `currency`, `free` (a promotion's
     * free item, which can't be changed), and `unsellable` (why the line
     * can't be bought, or null).
     *
     * @since 1.0.0
     *
     * @param  Cart  $cart  The cart.
     *
     * @return array<int, array{id: int, name: string, url: string|null, image: array{url: string, srcset: string|null, alt: string}|null, options: array<int, string>, quantity: int, unit: int, total: int, currency: string, free: bool, unsellable: string|null}>
     */
    protected function lines( Cart $cart ): array
    {
        $items   = $cart->items()->with( [ 'product.images', 'variant' ] )->orderBy( 'id' )->get();
        $visible = Product::query()->storefrontVisible()->whereKey( $items->pluck( 'product_id' )->filter()->unique()->all() )->pluck( 'id' )->map( static fn ( $id ): int => (int) $id )->all();
        $hasUrl  = Route::has( 'artisanpack.ecommerce.storefront.product' );
        $code    = (string) $cart->currency;

        return $items->map( function ( CartItem $item ) use ( $visible, $hasUrl, $code ): array {
            $product = $item->product;
            $name    = null === $product ? __( 'Unavailable product' ) : (string) $product->name;

            return [
                'id'         => (int) $item->id,
                'name'       => $name,
                'url'        => null !== $product && $hasUrl && in_array( (int) $product->id, $visible, true ) ? route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) : null,
                'image'      => $this->lineImage( $item, $name ),
                'options'    => $this->lineOptions( $item ),
                'quantity'   => (int) $item->quantity,
                'unit'       => (int) $item->unit_price_amount,
                'total'      => (int) $item->line_subtotal_amount,
                'currency'   => (string) ( $item->unit_price_currency ?? $code ),
                'free'       => $item->isFreeItem(),
                'unsellable' => $this->unsellableReason( $item, $visible ),
            ];
        } )->all();
    }

    /**
     * Why a line can't be bought, or null when it can.
     *
     * @since 1.0.0
     *
     * @param  CartItem         $item     The line.
     * @param  array<int, int>  $visible  Ids of the cart's products shoppers can see.
     *
     * @return string|null
     */
    protected function unsellableReason( CartItem $item, array $visible ): ?string
    {
        $product = $item->product;

        if ( null === $product || ! in_array( (int) $product->id, $visible, true ) ) {
            return __( 'This product is no longer available.' );
        }

        if ( $product->typeIsMissing() ) {
            return __( 'This product can no longer be bought.' );
        }

        if ( null !== $item->product_variant_id && null === $item->variant ) {
            return __( 'This option is no longer available.' );
        }

        if ( ! $item->isFreeItem() && ! StockStatus::for( $item->variant?->setRelation( 'product', $product ) ?? $product )->purchasable() ) {
            return __( 'This item is out of stock.' );
        }

        return null;
    }

    /**
     * The line's image: the variant's, else the product's card image.
     *
     * @since 1.0.0
     *
     * @param  CartItem  $item  The line.
     * @param  string    $name  The product name, for the alt text.
     *
     * @return array{url: string, srcset: string|null, alt: string}|null
     */
    protected function lineImage( CartItem $item, string $name ): ?array
    {
        if ( null !== $item->variant?->image_media_id ) {
            $image = ProductImages::media( (int) $item->variant->image_media_id, $name );

            if ( null !== $image ) {
                return [ 'url' => (string) $image['url'], 'srcset' => is_string( $image['srcset'] ?? null ) ? $image['srcset'] : null, 'alt' => $name ];
            }
        }

        return null === $item->product ? null : ProductImages::card( $item->product );
    }

    /**
     * The line's variant and options as short texts ("Red / M",
     * "Engraving: Happy birthday").
     *
     * @since 1.0.0
     *
     * @param  CartItem  $item  The line.
     *
     * @return array<int, string>
     */
    protected function lineOptions( CartItem $item ): array
    {
        $options = [];

        if ( null !== $item->variant && '' !== trim( (string) $item->variant->name ) ) {
            $options[] = (string) $item->variant->name;
        }

        foreach ( (array) ( $item->options ?? [] ) as $key => $value ) {
            if ( is_string( $key ) && ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) && '' !== trim( (string) $value ) ) {
                $options[] = __( ':option: :value', [ 'option' => Str::headline( $key ), 'value' => (string) $value ] );
            }
        }

        return $options;
    }

    /**
     * A money amount for an announcement.
     *
     * @since 1.0.0
     *
     * @param  int     $amount    Minor units.
     * @param  string  $currency  ISO 4217 code.
     *
     * @return string
     */
    protected function formatMoney( int $amount, string $currency ): string
    {
        return MoneyFormatter::format( $amount, $currency );
    }
}
