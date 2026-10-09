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
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\AddsToCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
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
 * }, 10, 4 );
 * ```
 *
 * Only storefront-visible products show; with none, nothing renders. The
 * section loads after the page (`#[Lazy]`), showing skeleton cards until
 * then.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
#[Lazy]
class RecentlyViewed extends Component
{
    use AddsToCart;
    use InteractsWithStorefrontCart;
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * The recently-viewed satellite's package name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SATELLITE = 'artisanpack-ui/ecommerce-recently-viewed';

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
     * Columns from `lg` up (1–6).
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
     * Skeleton cards while the section loads.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $params  The mount parameters.
     *
     * @return View
     */
    public function placeholder( array $params = [] ): View
    {
        $heading = is_string( $params['heading'] ?? null ) && '' !== trim( $params['heading'] ) ? $params['heading'] : __( 'Recently viewed' );
        $limit   = is_int( $params['limit'] ?? null ) ? $params['limit'] : $this->limit;

        return view( 'ecommerce-storefront::livewire.product.related-products-placeholder', [
            'title' => $heading,
            'count' => max( 1, min( 4, $limit ) ),
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
        return view( 'ecommerce-storefront::livewire.product.recently-viewed', [
            'products'  => $this->products(),
            'currency'  => app( StorefrontCart::class )->currency(),
            'gridClass' => GridColumns::classes( $this->columns ),
            'headingId' => 'ec-recently-viewed-' . $this->getId(),
            'title'     => null === $this->heading || '' === trim( $this->heading ) ? __( 'Recently viewed' ) : $this->heading,
        ] );
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

            $ids   = array_slice( $ids, 0, $this->limit );
            $order = array_flip( $ids );

            return Product::query()->storefrontVisible()->whereKey( $ids )->with( ProductCard::relations() )->get()
                ->sortBy( static fn ( Product $product ): int => $order[ (int) $product->id ] )
                ->values();
        } catch ( Throwable $exception ) {
            report( $exception );

            return new Collection();
        }
    }
}
