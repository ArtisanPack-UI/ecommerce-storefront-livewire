<?php

/**
 * Product page component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product;

use ArtisanPackUI\Ecommerce\Catalog\ProductViews;
use ArtisanPackUI\Ecommerce\Inventory\StockStatus;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductRelation;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\Ecommerce\Services\CurrencyConverter;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\PurchaseForm;
use ArtisanPackUI\EcommerceStorefrontLivewire\Registries\ProductFormRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\DisplayData;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\SafeHtml;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-show :product="$product" />`
 *
 * The product page (spec §7.2):
 *
 * - **Gallery** — the featured image, gallery images, and variant images,
 *   with thumbnails, zoom, and a lightbox; switches to a variant's image.
 * - **Summary** — name, rating summary linking to `#reviews`, display
 *   price, short description (through `kses()` in safe mode), stock status, and SKU, all
 *   following the chosen variant.
 * - **Purchase form** — the product type's form from the
 *   `ProductFormRegistry`, or "can't be purchased online".
 * - **Details** — description (through `kses()` in safe mode), an attributes table, and
 *   shipping/returns content from `ap.ecommerceStorefrontLivewire.product.shippingReturns`
 *   (filter: HTML string, product; empty hides it), in an accordion.
 * - **Sections** — Livewire components from `ap.ecommerceStorefrontLivewire.product.sections`
 *   (filter: key => `[ 'component' => name, 'position' => int, 'params' => [] ]`,
 *   product), rendered below the details in position order with `product`
 *   added to their params. The defaults are `reviews` (position 10, the
 *   reviews and `#reviews` anchor), `upsells` (20, "You may also like"),
 *   and `related` (30); unset a key to drop one.
 *
 * Mounting it records a view through the engine (`ap.ecommerce.product.viewed`).
 * A product that isn't storefront-visible, or whose type is missing, is a 404.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Show extends Component
{
    /**
     * The product.
     *
     * @since 1.0.0
     *
     * @var Product
     */
    #[Locked]
    public Product $product;

    /**
     * The variant the purchase form has matched.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $variantId = null;

    /**
     * Record the view (`ap.ecommerce.product.viewed`); off for editor
     * previews.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $recordView = true;

    /**
     * What doesn't change with the chosen variant, worked out once in
     * `mount()` so a variant change doesn't re-query the gallery or
     * re-clean the descriptions: `images`, `shortDescription`,
     * `description`, and `specifications`.
     *
     * @since 1.0.0
     *
     * @var array{images: array<int, array<string, mixed>>, shortDescription: string|null, description: string|null, specifications: array<int, array{label: string, value: string}>}
     */
    #[Locked]
    public array $content = [ 'images' => [], 'shortDescription' => null, 'description' => null, 'specifications' => [] ];

    /**
     * The open details section.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    public ?string $openSection = 'description';

    /**
     * Checks the product can be shown, works out its gallery, descriptions,
     * and attributes table, picks up a variant from the query string, and
     * records the view.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        abort_if( ! Product::query()->storefrontVisible()->whereKey( $this->product->id )->exists() || $this->product->typeIsMissing(), 404 );

        $this->content = [
            'images'           => $this->images(),
            'shortDescription' => SafeHtml::clean( $this->product->short_description ),
            'description'      => SafeHtml::clean( $this->product->description ),
            'specifications'   => $this->specifications(),
        ];

        $variant = request()->query( 'variant' );

        if ( is_numeric( $variant ) ) {
            $this->variantId = $this->ownVariant( (int) $variant )?->id;
        }

        if ( ! $this->recordView ) {
            return;
        }

        try {
            ProductViews::record( $this->product, Customer::forUser( auth()->user() ) );
        } catch ( Throwable $exception ) {
            // A listener's failure mustn't take the product page down.
            report( $exception );
        }
    }

    /**
     * Follows the purchase form's variant.
     *
     * @since 1.0.0
     *
     * @param  int       $productId  The product the form belongs to.
     * @param  int|null  $variantId  The matched variant, or null.
     *
     * @return void
     */
    #[On( 'ecommerce-product-variant-selected' )]
    public function variantSelected( int $productId, ?int $variantId = null ): void
    {
        if ( $productId !== (int) $this->product->id ) {
            return;
        }

        $variant         = null === $variantId ? null : $this->ownVariant( $variantId );
        $this->variantId = $variant?->id;

        $image = null === $variant?->image_media_id ? null : ProductImages::media( (int) $variant->image_media_id, (string) $this->product->name );

        if ( null !== $image ) {
            $this->dispatch( 'ecommerce-gallery-show', scope: 'product-' . $this->product->id, url: $image['url'] );
        }
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     * @since 2.0.0 Passes the `category`, `breadcrumbs`, `highlights` and `soldOut` notice.
     *
     * @return View
     */
    public function render(): View
    {
        $product  = $this->product;
        $variant  = null === $this->variantId ? null : $this->ownVariant( $this->variantId );
        $subject  = $variant ?? DisplayData::for( $product );
        $currency = app( StorefrontCart::class )->currency();
        $category = $this->category();
        $stock    = StockStatus::for( $subject );

        return view( 'ecommerce-storefront::livewire.product.show', [
            'images'           => $this->content['images'],
            'price'            => app( PriceDisplayResolver::class )->for( $subject, $currency, null, app( StorefrontCart::class )->customer() ),
            'stock'            => $stock,
            'sku'              => $variant?->sku ?? $product->sku,
            'shortDescription' => $this->content['shortDescription'],
            'description'      => $this->content['description'],
            'specifications'   => $this->content['specifications'],
            'shippingReturns'  => $this->shippingReturns(),
            'form'             => app( ProductFormRegistry::class )->for( $product ),
            'sections'         => $this->sections(),
            'reviewsUrl'       => '#reviews',
            'category'         => $category,
            'breadcrumbs'      => $this->breadcrumbs( $category ),
            'highlights'       => $this->highlights( $currency ),
            'soldOut'          => StockStatus::OUT_OF_STOCK === $stock->status ? $this->soldOutNotice( $variant ) : null,
        ] );
    }

    /**
     * The sections every product page starts with.
     *
     * @since 1.0.0
     *
     * @return array<string, array{component: string, position: int, params: array<string, mixed>}>
     */
    public static function defaultSections(): array
    {
        return [
            'reviews' => [ 'component' => 'artisanpack-ecommerce-storefront-product-reviews', 'position' => 10, 'params' => [] ],
            'upsells' => [ 'component' => 'artisanpack-ecommerce-storefront-related-products', 'position' => 20, 'params' => [ 'type' => ProductRelation::UPSELL ] ],
            'related' => [ 'component' => 'artisanpack-ecommerce-storefront-related-products', 'position' => 30, 'params' => [ 'type' => ProductRelation::RELATED ] ],
        ];
    }

    /**
     * A variant of this product, with the product relation set.
     *
     * @since 1.0.0
     *
     * @param  int  $variantId  Variant id.
     *
     * @return ProductVariant|null
     */
    protected function ownVariant( int $variantId ): ?ProductVariant
    {
        $variant = ProductVariant::query()->whereKey( $variantId )->where( 'product_id', $this->product->id )->first();

        return $variant?->setRelation( 'product', $this->product );
    }

    /**
     * The gallery: the product's images, then any variant images not
     * already in it.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function images(): array
    {
        return ProductImages::withVariants( $this->product );
    }

    /**
     * The attributes table: each attribute's label and its values.
     *
     * @since 1.0.0
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected function specifications(): array
    {
        return $this->product->productAttributes()
            ->with( [ 'values' => static fn ( $query ) => $query->orderBy( 'position' )->orderBy( 'id' ) ] )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get()
            ->map( static fn ( ProductAttribute $attribute ): array => [
                'label' => (string) $attribute->label,
                'value' => $attribute->values->map( static fn ( $value ): string => '' !== trim( (string) $value->label ) ? (string) $value->label : (string) $value->value )->implode( ', ' ),
            ] )
            ->filter( static fn ( array $row ): bool => '' !== $row['label'] && '' !== $row['value'] )
            ->values()
            ->all();
    }

    /**
     * Shipping and returns content from the filter, made safe.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    protected function shippingReturns(): ?string
    {
        $content = applyFilters( 'ap.ecommerceStorefrontLivewire.product.shippingReturns', '', $this->product );

        return is_string( $content ) ? SafeHtml::clean( $content ) : null;
    }

    /**
     * The product's category for the line above its name and the
     * breadcrumbs: its first category (as on the product card), with its
     * page's URL when it has one.
     *
     * @since 2.0.0
     *
     * @return array{id: int, name: string, url: string|null}|null
     */
    protected function category(): ?array
    {
        try {
            $category = $this->product->categories()->orderBy( 'id' )->first();
        } catch ( Throwable $exception ) {
            report( $exception );

            return null;
        }

        if ( null === $category ) {
            return null;
        }

        return [ 'id' => (int) $category->id, 'name' => (string) $category->name, 'url' => app( CategoryPaths::class )->url( (int) $category->id ) ];
    }

    /**
     * The breadcrumb trail: the shop, the category's ancestors and the
     * category (linked), then the product (unlinked).
     *
     * @since 2.0.0
     *
     * @param  array{id: int, name: string, url: string|null}|null  $category  The product's category.
     *
     * @return array<int, array{label: string, link?: string}>
     */
    protected function breadcrumbs( ?array $category ): array
    {
        $items = Route::has( 'artisanpack.ecommerce.storefront.catalog' )
            ? [ [ 'label' => __( 'Shop' ), 'link' => route( 'artisanpack.ecommerce.storefront.catalog' ) ] ]
            : [];

        if ( null !== $category ) {
            $paths = app( CategoryPaths::class );

            foreach ( $paths->ancestry( $category['id'] ) as $node ) {
                $url     = $paths->url( (int) $node['id'] );
                $items[] = null === $url ? [ 'label' => (string) $node['name'] ] : [ 'label' => (string) $node['name'], 'link' => $url ];
            }
        }

        $items[] = [ 'label' => (string) $this->product->name ];

        return $items;
    }

    /**
     * The delivery and returns list under the purchase form, through
     * `ap.ecommerceStorefrontLivewire.product.highlights`: a list of `icon`
     * (a design-system icon name), `title` and optional `description`. By
     * default, "Free shipping over $75" when `cart.free_shipping_threshold`
     * is set for the shopper's currency. Incomplete entries are dropped.
     *
     * ```php
     * addFilter( 'ap.ecommerceStorefrontLivewire.product.highlights', function ( array $highlights ): array {
     *     $highlights[] = [ 'icon' => 'rotate-ccw', 'title' => __( '30-day returns' ), 'description' => __( 'Unused and in original packaging' ) ];
     *
     *     return $highlights;
     * } );
     * ```
     *
     * @since 2.0.0
     *
     * @param  string  $currency  The shopper's currency.
     *
     * @return array<int, array{icon: string, title: string, description: string|null}>
     */
    protected function highlights( string $currency ): array
    {
        $defaults   = [];
        $configured = config( 'artisanpack.ecommerce-storefront-livewire.cart.free_shipping_threshold' );

        if ( null !== $configured ) {
            try {
                $threshold = app( CurrencyConverter::class )->fromConfigured( $configured, $currency );

                if ( null !== $threshold && (int) $threshold->getAmount() > 0 ) {
                    $defaults[] = [ 'icon' => 'truck', 'title' => __( 'Free shipping over :amount', [ 'amount' => MoneyFormatter::format( (int) $threshold->getAmount(), $currency ) ] ), 'description' => null ];
                }
            } catch ( Throwable $exception ) {
                report( $exception );
            }
        }

        $highlights = [];

        foreach ( (array) applyFilters( 'ap.ecommerceStorefrontLivewire.product.highlights', $defaults, $this->product ) as $highlight ) {
            if ( ! is_array( $highlight ) || ! is_string( $highlight['title'] ?? null ) || '' === trim( $highlight['title'] ) ) {
                continue;
            }

            $icon = is_string( $highlight['icon'] ?? null ) && 1 === preg_match( '/^[a-z0-9-]+$/', $highlight['icon'] ) ? $highlight['icon'] : 'info';

            $highlights[] = [
                'icon'        => $icon,
                'title'       => $highlight['title'],
                'description' => is_string( $highlight['description'] ?? null ) && '' !== trim( $highlight['description'] ) ? $highlight['description'] : null,
            ];
        }

        return $highlights;
    }

    /**
     * What the page says under the purchase form when the product (or the
     * chosen variant) is sold out, through
     * `ap.ecommerceStorefrontLivewire.product.soldOut`: a view name or an
     * Htmlable, such as a back-in-stock satellite's "Notify me" form. By
     * default, an alert saying it is sold out. A view that doesn't exist, or
     * anything else, shows nothing. The default is left out when the
     * purchase form offers its own back-in-stock signup
     * ({@see PurchaseForm::backInStockEnabled()}).
     *
     * @since 2.0.0
     *
     * @param  ProductVariant|null  $variant  The chosen variant.
     *
     * @return Htmlable|string|null A view name, Htmlable, or null.
     */
    protected function soldOutNotice( ?ProductVariant $variant ): Htmlable|string|null
    {
        $default = 'ecommerce-storefront::livewire.product.partials.sold-out';
        $notice  = applyFilters( 'ap.ecommerceStorefrontLivewire.product.soldOut', $default, $this->product, $variant );

        // The purchase form's "Notify me" alert already says it's sold out.
        if ( $default === $notice && PurchaseForm::backInStockEnabled( $this->product ) ) {
            return null;
        }

        if ( $notice instanceof Htmlable ) {
            return $notice;
        }

        return is_string( $notice ) && '' !== $notice && view()->exists( $notice ) ? $notice : null;
    }

    /**
     * Page sections from the filter, in position order.
     *
     * @since 1.0.0
     *
     * @return array<string, array{component: string, params: array<string, mixed>}>
     */
    protected function sections(): array
    {
        $filtered = applyFilters( 'ap.ecommerceStorefrontLivewire.product.sections', self::defaultSections(), $this->product );
        $sections = [];

        foreach ( is_array( $filtered ) ? $filtered : [] as $key => $section ) {
            if ( ! is_string( $key ) || ! is_array( $section ) || ! is_string( $section['component'] ?? null ) || '' === $section['component'] ) {
                continue;
            }

            $sections[ $key ] = [
                'component' => $section['component'],
                'position'  => is_int( $section['position'] ?? null ) ? $section['position'] : 100,
                'params'    => [ ...( is_array( $section['params'] ?? null ) ? $section['params'] : [] ), 'product' => $this->product ],
            ];
        }

        uasort( $sections, static fn ( array $a, array $b ): int => $a['position'] <=> $b['position'] );

        return array_map( static fn ( array $section ): array => [ 'component' => $section['component'], 'params' => $section['params'] ], $sections );
    }
}
