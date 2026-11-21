<?php

/**
 * Recently viewed products component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Pricing\DisplayPrice;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\GridColumns;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\ProductCard;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-recently-viewed :limit="4" />`
 *
 * The products the shopper viewed last (spec §11.3). The storefront keeps
 * no history itself (the engine fires `ap.ecommerce.product.viewed`); the
 * recently-viewed satellite answers the
 * `ap.ecommerceStorefrontLivewire.recentlyViewed.productIds` filter with
 * product ids, most recent first:
 *
 * ```php
 * addFilter( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds', function ( array $ids, int $limit, ?Customer $customer, ?Product $exclude ): array {
 *     return $history->latest( $customer, $limit, $exclude?->id );
 * } );
 * ```
 *
 * Only storefront-visible products whose type is installed show (of the
 * first 50 ids), up to `limit`, as compact rows (a thumbnail, the name and
 * the price) in a grid at most `columns` wide; with none, nothing renders. The
 * section loads after the page (`#[Lazy]`), showing skeleton rows of the
 * same size until then.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
#[Lazy]
class RecentlyViewed extends Component
{
    /**
     * The recently-viewed satellite's package name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SATELLITE = 'artisanpack-ui/ecommerce-recently-viewed';

    /**
     * The most ids taken from the satellite.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_IDS = 50;

    /**
     * The product being viewed, left out of the list.
     *
     * @since 1.0.0
     *
     * @var Product|null
     */
    #[Locked]
    public ?Product $product = null;

    /**
     * How many products (1–12).
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $limit = 4;

    /**
     * The most rows side by side (1–6); each row is at least 240px wide.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $columns = 4;

    /**
     * The heading; null says "Recently viewed".
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $heading = null;

    /**
     * Clamps the props.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->limit   = max( 1, min( 12, $this->limit ) );
        $this->columns = GridColumns::clamp( $this->columns );
    }

    /**
     * Skeleton rows the size of the real ones while the section loads.
     *
     * @since 1.0.0
     * @since 2.0.0 Compact skeleton rows in the section's grid.
     *
     * @param  array<string, mixed>  $params  The mount parameters.
     *
     * @return View
     */
    public function placeholder( array $params = [] ): View
    {
        $heading = is_string( $params['heading'] ?? null ) && '' !== trim( $params['heading'] ) ? $params['heading'] : __( 'Recently viewed' );
        $limit   = is_int( $params['limit'] ?? null ) ? $params['limit'] : $this->limit;

        $columns = is_int( $params['columns'] ?? null ) ? $params['columns'] : $this->columns;

        return view( 'ecommerce-storefront::livewire.product.recently-viewed-placeholder', [
            'title'   => $heading,
            'count'   => max( 1, min( 12, $limit ) ),
            'columns' => GridColumns::clamp( $columns ),
        ] );
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

        return view( 'ecommerce-storefront::livewire.product.recently-viewed', [
            'rows'      => $this->products()->map( fn ( Product $product ): array => $this->row( $product, $currency ) )->all(),
            'columns'   => $this->columns,
            'headingId' => 'ec-recently-viewed-' . $this->getId(),
            'title'     => null === $this->heading || '' === trim( $this->heading ) ? __( 'Recently viewed' ) : $this->heading,
        ] );
    }

    /**
     * One compact row: the product card's data (through the same
     * `productCard` filter) cut to what the row shows.
     *
     * @since 2.0.0
     *
     * @param  Product  $product   The product.
     * @param  string   $currency  The shopper's currency.
     *
     * @return array{id: int, name: string, url: string|null, image: array{url: string, srcset: string|null, alt: string}|null, price: DisplayPrice|null}
     */
    protected function row( Product $product, string $currency ): array
    {
        $card = ( new ProductCard( $product, $currency, quickAdd: false ) )->card;

        return [
            'id'    => $card['id'],
            'name'  => $card['name'],
            'url'   => $card['url'],
            'image' => $card['image'],
            'price' => $card['price'],
        ];
    }

    /**
     * The satellite's products, in its order.
     *
     * @since 1.0.0
     *
     * @return Collection<int, Product>
     */
    protected function products(): Collection
    {
        try {
            $ids = applyFilters( 'ap.ecommerceStorefrontLivewire.recentlyViewed.productIds', [], $this->limit, Customer::forUser( auth()->user() ), $this->product );
            $ids = array_values( array_unique( array_filter( array_map( 'intval', is_array( $ids ) ? $ids : [] ), fn ( int $id ): bool => $id > 0 && $id !== (int) $this->product?->id ) ) );

            if ( [] === $ids ) {
                return new Collection();
            }

            // Hidden products and ones whose type is uninstalled are dropped
            // before the list is cut to the limit, so they don't leave it short.
            $ids   = array_slice( $ids, 0, self::MAX_IDS );
            $order = array_flip( $ids );

            return Product::query()->storefrontVisible()->whereKey( $ids )->with( ProductCard::relations() )->get()
                ->reject( static fn ( Product $product ): bool => $product->typeIsMissing() )
                ->sortBy( static fn ( Product $product ): int => $order[ (int) $product->id ] )
                ->take( $this->limit )
                ->values();
        } catch ( Throwable $exception ) {
            report( $exception );

            return new Collection();
        }
    }
}
