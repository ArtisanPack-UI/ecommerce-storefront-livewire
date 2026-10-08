<?php

/**
 * Storefront product card component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\Ecommerce\Contracts\CurrencyResolver;
use ArtisanPackUI\Ecommerce\Inventory\StockStatus as EngineStockStatus;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Pricing\DisplayPrice;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\Ecommerce\ProductTypes\SimpleProductType;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-sf-product-card :product="$product" />`
 *
 * The card every product listing uses: catalog grids, search, related
 * products, and visual-editor blocks (spec §8.1). It shows the image
 * (media-library sizes when installed), the name linked to the product
 * page, the display price, the rating, the stock state, and, for simple
 * products that can be bought, an "Add to cart" button that calls the
 * parent component's `quickAdd()` ({@see \ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\AddsToCart}).
 *
 * The card's data runs through the `ap.ecommerceStorefrontLivewire.productCard`
 * filter (the data array and the product) so a satellite can add a badge or
 * change the link; see {@see self::cardData()} for the keys.
 *
 * Eager-load the product's `images` when rendering many cards.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class ProductCard extends Component
{
    /**
     * The card's data after the filter.
     *
     * @since 1.0.0
     *
     * @var array{id: int, name: string, url: string|null, image: array{url: string, srcset: string|null, alt: string}|null, price: DisplayPrice|null, stock: EngineStockStatus, rating: float, reviews: int, reviews_url: string|null, quick_add: bool, badges: array<int, string>, heading_level: int}
     */
    public array $card;

    /**
     * @since 1.0.0
     *
     * @param  Product                 $product       The product.
     * @param  string|null             $currency      The price currency; defaults to the shopper's.
     * @param  DisplayPrice|null       $price         The display price, when the caller already has it.
     * @param  EngineStockStatus|null  $stock         The availability, when the caller already has it.
     * @param  bool                    $quickAdd      Offer "Add to cart" for simple products.
     * @param  int                     $headingLevel  The heading level of the product name (2–6).
     * @param  bool                    $showPrice     Show the price.
     * @param  bool                    $showRating    Show the rating.
     */
    public function __construct(
        public Product $product,
        public ?string $currency = null,
        public ?DisplayPrice $price = null,
        public ?EngineStockStatus $stock = null,
        public bool $quickAdd = true,
        public int $headingLevel = 3,
        public bool $showPrice = true,
        public bool $showRating = true,
    ) {
        $this->card = $this->cardData();
    }

    /**
     * The card's data, through the `productCard` filter.
     *
     * Keys: `id`, `name`, `url` (null renders the name unlinked), `image`
     * (`url`, `srcset`, `alt`, or null), `price` (a `DisplayPrice` or null),
     * `stock` (a `StockStatus`), `rating`, `reviews`, `reviews_url`,
     * `quick_add`, `badges` (extra badge texts), and `heading_level`.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function cardData(): array
    {
        $product  = $this->product;
        $currency = $this->currency ?? app( CurrencyResolver::class )->resolve( request() );
        $price    = $this->price ?? app( PriceDisplayResolver::class )->for( $product, $currency );
        $stock    = $this->stock ?? EngineStockStatus::for( $product );
        $url      = Route::has( 'artisanpack.ecommerce.storefront.product' )
            ? route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] )
            : null;

        $defaults = [
            'id'            => (int) $product->id,
            'name'          => (string) $product->name,
            'url'           => $url,
            'image'         => ProductImages::card( $product ),
            'price'         => $price,
            'stock'         => $stock,
            'rating'        => (float) $product->avg_rating,
            'reviews'       => (int) $product->reviews_count,
            'reviews_url'   => null === $url ? null : $url . '#reviews',
            'quick_add'     => $this->quickAdd
                && SimpleProductType::KEY === $product->type
                && ! $product->typeIsMissing()
                && null !== $price
                && $stock->purchasable(),
            'badges'        => [],
            'heading_level' => max( 2, min( 6, $this->headingLevel ) ),
        ];

        $filtered = applyFilters( 'ap.ecommerceStorefrontLivewire.productCard', $defaults, $product );

        return is_array( $filtered ) ? self::normalize( $filtered, $defaults ) : $defaults;
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
        return view( 'ecommerce-storefront::components.product-card' );
    }

    /**
     * Keeps each filtered value only when it has the type the view expects.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $filtered  The filter's result.
     * @param  array<string, mixed>  $defaults  The card's own data.
     *
     * @return array<string, mixed>
     */
    private static function normalize( array $filtered, array $defaults ): array
    {
        $checks = [
            'id'            => static fn ( mixed $value ): bool => is_int( $value ),
            'name'          => static fn ( mixed $value ): bool => is_string( $value ),
            'url'           => static fn ( mixed $value ): bool => null === $value || is_string( $value ),
            'image'         => static fn ( mixed $value ): bool => null === $value || ( is_array( $value ) && is_string( $value['url'] ?? null ) ),
            'price'         => static fn ( mixed $value ): bool => null === $value || $value instanceof DisplayPrice,
            'stock'         => static fn ( mixed $value ): bool => $value instanceof EngineStockStatus,
            'rating'        => static fn ( mixed $value ): bool => is_int( $value ) || is_float( $value ),
            'reviews'       => static fn ( mixed $value ): bool => is_int( $value ),
            'reviews_url'   => static fn ( mixed $value ): bool => null === $value || is_string( $value ),
            'quick_add'     => static fn ( mixed $value ): bool => is_bool( $value ),
            'badges'        => static fn ( mixed $value ): bool => is_array( $value ),
            'heading_level' => static fn ( mixed $value ): bool => is_int( $value ) && $value >= 2 && $value <= 6,
        ];

        $card = $defaults;

        foreach ( $checks as $key => $check ) {
            if ( array_key_exists( $key, $filtered ) && $check( $filtered[ $key ] ) ) {
                $card[ $key ] = $filtered[ $key ];
            }
        }

        $card['image']  = null === $card['image'] ? null : [ 'url' => (string) $card['image']['url'], 'srcset' => is_string( $card['image']['srcset'] ?? null ) ? $card['image']['srcset'] : null, 'alt' => (string) ( $card['image']['alt'] ?? $card['name'] ) ];
        $card['badges'] = array_values( array_filter( $card['badges'], static fn ( mixed $badge ): bool => is_string( $badge ) && '' !== $badge ) );

        return $card;
    }
}
