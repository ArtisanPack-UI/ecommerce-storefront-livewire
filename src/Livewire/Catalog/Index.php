<?php

/**
 * Catalog component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog;

use ArtisanPackUI\Ecommerce\Catalog\CatalogQuery;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\AddsToCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * `<livewire:artisanpack-ecommerce-storefront-catalog />`
 *
 * The product grid (spec §7.1): storefront-visible products from the
 * engine's `CatalogQuery`, sorted and paginated, with sort, page, and page
 * size in the query string so a listing can be shared and back/forward
 * restores it.
 *
 * Pages and blocks reuse it by narrowing the listing with props:
 *
 * ```blade
 * <livewire:artisanpack-ecommerce-storefront-catalog :category="$category->id" />
 * <livewire:artisanpack-ecommerce-storefront-catalog tag="summer" />
 * <livewire:artisanpack-ecommerce-storefront-catalog featured />
 * <livewire:artisanpack-ecommerce-storefront-catalog :ids="[ 4, 8, 15 ]" />
 * ```
 *
 * The sort options run through `ap.ecommerceStorefrontLivewire.catalog.sorts`
 * (key => label; keys must be engine sorts).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use AddsToCart;
    use InteractsWithStorefrontCart;
    use RateLimitsStorefront;
    use SendsToasts;
    use WithPagination;

    /**
     * Only products in this category (id or slug) and its sub-categories.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    #[Locked]
    public int|string|null $category = null;

    /**
     * Only products with this tag (id or slug).
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    #[Locked]
    public int|string|null $tag = null;

    /**
     * Only featured products.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $featured = false;

    /**
     * Only these products.
     *
     * @since 1.0.0
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $ids = [];

    /**
     * The heading above the grid; null shows none (the page has its own).
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $heading = null;

    /**
     * The sort key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( history: true )]
    public string $sort = '';

    /**
     * Products per page.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Url( as: 'per_page', history: true )]
    public int $perPage = 0;

    /**
     * Falls back to the configured sort and page size when the query string
     * has none (or an unknown one), which also keeps them out of the URL.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->ids     = array_values( array_unique( array_filter( array_map( 'intval', $this->ids ), static fn ( int $id ): bool => $id > 0 ) ) );
        $this->sort    = $this->validSort( $this->sort );
        $this->perPage = $this->validPerPage( $this->perPage );
    }

    /**
     * Starts again from page one with a known sort.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedSort(): void
    {
        $this->sort = $this->validSort( $this->sort );
        $this->resetPage();
    }

    /**
     * Starts again from page one with an offered page size.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedPerPage(): void
    {
        $this->perPage = $this->validPerPage( $this->perPage );
        $this->resetPage();
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
        return view( 'ecommerce-storefront::livewire.catalog.index', [
            'products'      => $this->products(),
            'sorts'         => $this->sorts(),
            'perPageValues' => $this->perPageValues(),
            'currency'      => $this->currency(),
        ] );
    }

    /**
     * The sort options, key => label.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function sorts(): array
    {
        $defaults = [
            'newest'     => __( 'Newest' ),
            'price'      => __( 'Price: low to high' ),
            '-price'     => __( 'Price: high to low' ),
            'popularity' => __( 'Most popular' ),
            'rating'     => __( 'Top rated' ),
            'name'       => __( 'Name' ),
        ];

        $filtered = applyFilters( 'ap.ecommerceStorefrontLivewire.catalog.sorts', $defaults );
        $sorts    = [];

        foreach ( is_array( $filtered ) ? $filtered : $defaults as $key => $label ) {
            // Relevance needs a search term; the search page offers it.
            if ( is_string( $key ) && 'relevance' !== $key && in_array( $key, CatalogQuery::SORTS, true ) && is_string( $label ) && '' !== $label ) {
                $sorts[ $key ] = $label;
            }
        }

        return [] === $sorts ? $defaults : $sorts;
    }

    /**
     * The offered page sizes.
     *
     * @since 1.0.0
     *
     * @return array<int, int>
     */
    protected function perPageValues(): array
    {
        $values = array_values( array_unique( array_filter(
            array_map( 'intval', (array) config( 'artisanpack.ecommerce-storefront-livewire.catalog.per_page_values', [ 12, 24, 48 ] ) ),
            static fn ( int $value ): bool => $value > 0 && $value <= 100,
        ) ) );

        sort( $values );

        return [] === $values ? [ 24 ] : $values;
    }

    /**
     * The current page of products.
     *
     * @since 1.0.0
     *
     * @return LengthAwarePaginator<int, Product>
     */
    protected function products(): LengthAwarePaginator
    {
        $query = app( CatalogQuery::class )
            ->currency( $this->currency() )
            ->sort( $this->sort );

        if ( null !== $this->category && '' !== (string) $this->category ) {
            $query->inCategory( $this->category );
        }

        if ( null !== $this->tag && '' !== (string) $this->tag ) {
            $query->withTag( $this->tag );
        }

        if ( $this->featured ) {
            $query->featured();
        }

        if ( [] !== $this->ids ) {
            $query->ids( $this->ids );
        }

        return $query->builder()
            ->with( [ 'images' ] )
            ->paginate( $this->perPage );
    }

    /**
     * The shopper's display currency.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function currency(): string
    {
        return app( StorefrontCart::class )->currency();
    }

    /**
     * `$sort` when it is offered, else the configured default (else the
     * first offered sort).
     *
     * @since 1.0.0
     *
     * @param  string  $sort  The requested sort.
     *
     * @return string
     */
    protected function validSort( string $sort ): string
    {
        $sorts = $this->sorts();

        if ( array_key_exists( $sort, $sorts ) ) {
            return $sort;
        }

        $default = (string) config( 'artisanpack.ecommerce-storefront-livewire.catalog.default_sort', 'newest' );

        return array_key_exists( $default, $sorts ) ? $default : (string) array_key_first( $sorts );
    }

    /**
     * `$perPage` when it is offered, else the configured default (else the
     * smallest offered size).
     *
     * @since 1.0.0
     *
     * @param  int  $perPage  The requested page size.
     *
     * @return int
     */
    protected function validPerPage( int $perPage ): int
    {
        $values = $this->perPageValues();

        if ( in_array( $perPage, $values, true ) ) {
            return $perPage;
        }

        $default = (int) config( 'artisanpack.ecommerce-storefront-livewire.catalog.per_page', 24 );

        return in_array( $default, $values, true ) ? $default : $values[0];
    }
}
