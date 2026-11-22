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
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\Ecommerce\Services\BackInStockService;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SummarizesErrors;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\DisplayData;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Money\Money;
use Throwable;

/**
 * The base for every purchase form on the product page (spec §7.2, §8.2).
 *
 * It holds the product and quantity and adds lines to the shopper's cart
 * through the engine's `StorefrontCartService`: the batch counts once
 * against the `ecommerce.cart.mutate` limit, an engine
 * `CartOperationException` is shown on the line's field (and any refusal
 * is announced in an alert: "Not added to your cart: …"), and on success it
 * dispatches `ecommerce-cart-updated`, announces the addition in a live
 * region, and then (per `cart.after_add`) dispatches `ecommerce-cart-open`
 * for the cart drawer or shows a toast.
 *
 * The default `addToCart()` adds `quantity` of the product with
 * {@see self::lineVariantId()} and {@see self::lineOptions()}; override
 * those (or `addToCart()` itself) in a type's form. Forms registered in the
 * `ProductFormRegistry` should extend this class.
 *
 * Since 2.0.0 the "Add to cart" button shows the line price when the form
 * knows the unit price up front ({@see self::unitPrice()}), and the row
 * takes extra buttons, such as a wishlist heart, from the
 * `ap.ecommerceStorefrontLivewire.product.addToCartActions` filter
 * ({@see self::addToCartActions()}).
 *
 * Since 2.0.0, when the product (or the chosen variant) can't be bought
 * because it is out of stock ({@see self::outOfStock()}), the row offers a
 * "Notify me" back-in-stock signup instead ({@see self::notifyMe()}),
 * through the engine's `BackInStockService` (engine 1.4). The
 * `ap.ecommerceStorefrontLivewire.product.backInStockEnabled` filter turns
 * it off.
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
    use SummarizesErrors;

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
     * Show the quantity stepper; without it, one is added. The Add-to-Cart
     * block turns it off.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $showQuantity = true;

    /**
     * The button's text in place of "Add to cart" (at most 60 characters).
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $buttonText = null;

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
     * The email for a back-in-stock signup.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public string $notifyEmail = '';

    /**
     * Whether the shopper has signed up to hear when it's back.
     *
     * @since 2.0.0
     *
     * @var bool
     */
    public bool $notifySubscribed = false;

    /**
     * The unit price, memoized for the current request (protected, so
     * Livewire never stores it).
     *
     * @since 2.0.0
     *
     * @var Money|null
     */
    protected ?Money $unitPriceCache = null;

    /**
     * Whether {@see self::$unitPriceCache} has been resolved this request.
     *
     * @since 2.0.0
     *
     * @var bool
     */
    protected bool $unitPriceResolved = false;

    /**
     * Starts the quantity at 1. Forms that mount call this first.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->quantity    = 1;
        $this->buttonText  = null === $this->buttonText || '' === trim( $this->buttonText ) ? null : mb_substr( trim( $this->buttonText ), 0, 60 );
        $this->notifyEmail = $this->signedInEmail() ?? '';
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
        $this->startAdding();
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
        $product = DisplayData::for( $this->product );

        if ( ! StockStatus::for( $product )->purchasable() ) {
            return __( 'This product is out of stock.' );
        }

        if ( null === app( PriceDisplayResolver::class )->for( $product, app( StorefrontCart::class )->currency() ) ) {
            return __( 'This product isn\'t available to buy right now.' );
        }

        return null;
    }

    /**
     * Whether the product (or the chosen variant) can't be bought only
     * because it is out of stock: it has a price in the shopper's currency,
     * and no choice is still missing. Forms with their own stock rules
     * override it.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    public function outOfStock(): bool
    {
        $product = DisplayData::for( $this->product );

        return ! StockStatus::for( $product )->purchasable()
            && null !== app( PriceDisplayResolver::class )->for( $product, app( StorefrontCart::class )->currency() );
    }

    /**
     * Whether the row offers the "Notify me" signup: the engine takes
     * back-in-stock signups, the
     * `ap.ecommerceStorefrontLivewire.product.backInStockEnabled` filter
     * (true by default; gets the product) allows it, and the product is
     * out of stock.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    public function offersBackInStock(): bool
    {
        return self::backInStockEnabled( $this->product )
            && null !== $this->blockedReason()
            && $this->outOfStock();
    }

    /**
     * Whether back-in-stock signups are on for a product: the engine takes
     * them and the `ap.ecommerceStorefrontLivewire.product.backInStockEnabled`
     * filter allows it.
     *
     * @since 2.0.0
     *
     * @param  Product  $product  The product.
     *
     * @return bool
     */
    public static function backInStockEnabled( Product $product ): bool
    {
        return class_exists( BackInStockService::class )
            && true === applyFilters( 'ap.ecommerceStorefrontLivewire.product.backInStockEnabled', true, $product );
    }

    /**
     * The "Notify me" title: what's sold out.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function soldOutTitle(): string
    {
        return null === $this->notifyVariant() ? __( 'This product is sold out' ) : __( 'This option is sold out' );
    }

    /**
     * Whether the signup email is the signed-in shopper's (shown read-only).
     *
     * @since 2.0.0
     *
     * @return bool
     */
    public function notifyEmailLocked(): bool
    {
        return null !== $this->signedInEmail();
    }

    /**
     * Signs the shopper up to hear when the product (or the chosen variant)
     * is back: validates the email, counts against the
     * `ecommerce.backInStock.subscribe` limit, and stores the signup through
     * the engine. The limit counts per shopper (cart, account or IP) and per
     * email address. Signing up twice is a success too.
     *
     * @since 2.0.0
     *
     * @return void
     */
    public function notifyMe(): void
    {
        $this->announcement = '';
        $this->newErrorSummary();

        if ( ! $this->offersBackInStock() ) {
            return;
        }

        $this->notifyEmail = $this->notifyEmailLocked() ? (string) $this->signedInEmail() : trim( $this->notifyEmail );

        $this->validate(
            [ 'notifyEmail' => [ 'required', 'string', 'email:rfc', 'max:254' ] ],
            [
                'notifyEmail.required' => __( 'Enter your email address.' ),
                'notifyEmail.email'    => __( 'Enter an email address like name@example.com.' ),
                'notifyEmail.max'      => __( 'Enter an email address like name@example.com.' ),
            ],
        );

        $subscribed = $this->rateLimited( 'ecommerce.backInStock.subscribe', function (): bool {
            try {
                app( BackInStockService::class )->subscribe(
                    $this->product,
                    $this->notifyVariant(),
                    $this->notifyEmail,
                    app( StorefrontCart::class )->customer(),
                    app()->getLocale(),
                );
            } catch ( Throwable $exception ) {
                report( $exception );

                $this->addError( 'notifyEmail', __( 'We couldn\'t sign you up just now. Try again in a moment.' ) );

                return false;
            }

            return true;
        }, app( StorefrontCart::class )->subject()->with( [ 'email' => $this->notifyEmail ] ) );

        if ( true === $subscribed ) {
            $this->notifySubscribed = true;
            $this->announcement     = __( 'We\'ll email you when it\'s back.' );
        }
    }

    /**
     * Whether "Add to cart" shows a line price, so the quantity binds live
     * to keep it current.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    public function showsLinePrice(): bool
    {
        return null !== $this->cachedUnitPrice();
    }

    /**
     * The "Add to cart" line price: the unit price times the quantity, or
     * null when the form can't know it up front or the quantity isn't a
     * whole number in range.
     *
     * @since 2.0.0
     *
     * @return string|null
     */
    public function linePrice(): ?string
    {
        $line = $this->lineMoney();

        return null === $line ? null : MoneyFormatter::formatMoney( $line );
    }

    /**
     * Extra buttons after "Add to cart", such as a wishlist heart, from the
     * `ap.ecommerceStorefrontLivewire.product.addToCartActions` filter:
     * key => view name (rendered with `$product` and `$formId`) or an
     * `Htmlable`. Anything else is dropped.
     *
     * @since 2.0.0
     *
     * @return array<string, Htmlable|string>
     */
    public function addToCartActions(): array
    {
        $actions = applyFilters( 'ap.ecommerceStorefrontLivewire.product.addToCartActions', [], $this->product );

        if ( ! is_array( $actions ) ) {
            return [];
        }

        return array_filter(
            $actions,
            static fn ( mixed $action ): bool => $action instanceof Htmlable || ( is_string( $action ) && view()->exists( $action ) ),
        );
    }

    /**
     * Starts an "Add to cart": clears the last announcement and keys a new
     * failure alert, so a refusal is announced even when it repeats.
     * Forms that override {@see self::addToCart()} call this first.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function startAdding(): void
    {
        $this->announcement = '';
        $this->newErrorSummary();
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
     * The price of one unit, for the line price on "Add to cart", or null
     * when it depends on choices the shopper hasn't made (variants, options,
     * a group). Forms with a fixed price override this.
     *
     * @since 2.0.0
     *
     * @return Money|null
     */
    protected function unitPrice(): ?Money
    {
        return null;
    }

    /**
     * {@see self::unitPrice()}, resolved once per request: the row asks
     * for it twice per render.
     *
     * @since 2.0.0
     *
     * @return Money|null
     */
    protected function cachedUnitPrice(): ?Money
    {
        if ( ! $this->unitPriceResolved ) {
            $this->unitPriceCache    = $this->unitPrice();
            $this->unitPriceResolved = true;
        }

        return $this->unitPriceCache;
    }

    /**
     * The unit price times the quantity, or null when the unit price isn't
     * known up front or the quantity isn't a whole number in range.
     *
     * @since 2.0.0
     *
     * @return Money|null
     */
    protected function lineMoney(): ?Money
    {
        $unit     = $this->cachedUnitPrice();
        $quantity = filter_var( $this->quantity, FILTER_VALIDATE_INT );

        if ( null === $unit || false === $quantity || $quantity < 1 || $quantity > StorefrontCartService::MAX_LINE_QUANTITY ) {
            return null;
        }

        return $unit->multiply( (string) $quantity );
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
     * The variant a back-in-stock signup is for (the chosen one), or null
     * for the product.
     *
     * @since 2.0.0
     *
     * @return ProductVariant|null
     */
    protected function notifyVariant(): ?ProductVariant
    {
        return null;
    }

    /**
     * The signed-in shopper's email (their customer record's, else their
     * account's), or null for a guest.
     *
     * @since 2.0.0
     *
     * @return string|null
     */
    protected function signedInEmail(): ?string
    {
        $email = app( StorefrontCart::class )->customer()?->email ?? auth()->user()?->email ?? null;

        return is_string( $email ) && '' !== trim( $email ) ? trim( $email ) : null;
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
        $last->loadMissing( [ 'cart', 'variant' ] );

        $this->cartChanged( $last->cart );

        $this->announcement = __( 'Added to your cart.' );

        $this->afterAddToCart( (string) $this->product->name, 1 === count( $items ) ? $last->variant?->name : null );
    }
}
