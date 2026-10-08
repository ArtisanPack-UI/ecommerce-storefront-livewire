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
use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductAttributeValue;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\AddsToCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

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
 * **Filters** (spec §7.1, sidebar on desktop, a drawer on mobile): category
 * tree, tag, price range, attribute values, in stock, on sale, and minimum
 * rating, each with facet counts from `CatalogQuery::facets()`. A group's
 * own counts ignore its own selection, so picking "Red" still shows how
 * many products are blue; options with no results are disabled, not hidden,
 * unless they are selected. Every filter lives in the query string.
 *
 * The filter groups run through `ap.ecommerceStorefrontLivewire.catalog.filters`
 * (key => definition, plus a context array with the `category`, `tag`, and
 * `featured` scope). Unset a key to remove a group (`category`, `tag`,
 * `price`, `attributes`, `in_stock`, `on_sale`, `rating`); restrict the
 * attribute groups with `attributes.only` (attribute keys). Add a group with
 * a new key:
 *
 * ```php
 * addFilter( 'ap.ecommerceStorefrontLivewire.catalog.filters', function ( array $filters ): array {
 *     $filters['material'] = [
 *         'type'    => 'options',                          // or 'toggle'
 *         'label'   => __( 'Material' ),
 *         'options' => [ 'wool' => __( 'Wool' ), 'linen' => __( 'Linen' ) ],
 *         'apply'   => fn ( CatalogQuery $query, array $values ) => $query->withAttribute( 'material', $values ),
 *     ];
 *
 *     return $filters;
 * } );
 * ```
 *
 * A custom group's value is in the query string under `f[{key}]`; `apply`
 * gets the chosen option values (or true for a toggle).
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
     * Show the filter sidebar and drawer.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $filterable = true;

    /**
     * The chosen category's slug (with its sub-categories).
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( as: 'category', history: true )]
    public string $categoryFilter = '';

    /**
     * The chosen tag's slug.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( as: 'tag', history: true )]
    public string $tagFilter = '';

    /**
     * The lowest price, in minor units of the shopper's currency.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Url( as: 'price_min', history: true )]
    public ?int $priceMin = null;

    /**
     * The highest price, in minor units of the shopper's currency.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Url( as: 'price_max', history: true )]
    public ?int $priceMax = null;

    /**
     * The chosen attribute values, attribute key => values.
     *
     * @since 1.0.0
     *
     * @var array<string, array<int, string>>
     */
    #[Url( as: 'attr', history: true )]
    public array $attributeFilters = [];

    /**
     * Only products that can be bought now.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Url( as: 'in_stock', history: true )]
    public bool $inStock = false;

    /**
     * Only products on sale.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Url( as: 'on_sale', history: true )]
    public bool $onSale = false;

    /**
     * The minimum average rating (0 for any).
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Url( as: 'rating', history: true )]
    public int $minRating = 0;

    /**
     * The values of filter groups added through the `catalog.filters` hook,
     * group key => option values (or '1' for a toggle).
     *
     * @since 1.0.0
     *
     * @var array<string, array<int, string>|string>
     */
    #[Url( as: 'f', history: true )]
    public array $extraFilters = [];

    /**
     * The filter group definitions after the hook, for this request.
     *
     * @since 1.0.0
     *
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $definitions = null;

    /**
     * Facets per excluded filter, for this request.
     *
     * @since 1.0.0
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $facetCache = [];

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

        $this->sanitizeFilters();
    }

    /**
     * Cleans a changed filter and starts again from page one.
     *
     * @since 1.0.0
     *
     * @param  string  $property  The changed property (dotted for arrays).
     *
     * @return void
     */
    public function updated( string $property ): void
    {
        $root = explode( '.', $property, 2 )[0];

        if ( in_array( $root, [ 'categoryFilter', 'tagFilter', 'priceMin', 'priceMax', 'attributeFilters', 'inStock', 'onSale', 'minRating', 'extraFilters' ], true ) ) {
            $this->sanitizeFilters();
            $this->resetPage();
        }
    }

    /**
     * Removes one active filter (an option value, or the whole group).
     *
     * @since 1.0.0
     *
     * @param  string       $group  The group key (`attr.{key}` for an attribute).
     * @param  string|null  $value  The option value, for multi-value groups.
     *
     * @return void
     */
    public function removeFilter( string $group, ?string $value = null ): void
    {
        if ( str_starts_with( $group, 'attr.' ) ) {
            $key = substr( $group, 5 );

            $this->attributeFilters[ $key ] = null === $value
                ? []
                : array_values( array_diff( (array) ( $this->attributeFilters[ $key ] ?? [] ), [ $value ] ) );
        } else {
            match ( $group ) {
                'category' => $this->categoryFilter                = '',
                'tag'      => $this->tagFilter                     = '',
                'price'    => [ $this->priceMin, $this->priceMax ] = [ null, null ],
                'in_stock' => $this->inStock                       = false,
                'on_sale'  => $this->onSale                        = false,
                'rating'   => $this->minRating                     = 0,
                default    => $this->removeExtraFilter( $group, $value ),
            };
        }

        $this->sanitizeFilters();
        $this->resetPage();
    }

    /**
     * Removes every filter.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function clearFilters(): void
    {
        $this->categoryFilter   = '';
        $this->tagFilter        = '';
        $this->priceMin         = null;
        $this->priceMax         = null;
        $this->attributeFilters = [];
        $this->inStock          = false;
        $this->onSale           = false;
        $this->minRating        = 0;
        $this->extraFilters     = [];

        $this->resetPage();
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
        $groups = $this->filterable ? $this->filterGroups() : [];

        return view( 'ecommerce-storefront::livewire.catalog.index', [
            'products'      => $this->products(),
            'sorts'         => $this->sorts(),
            'perPageValues' => $this->perPageValues(),
            'currency'      => $this->currency(),
            'groups'        => $groups,
            'activeFilters' => $this->filterable ? $this->activeFilters( $groups ) : [],
            'filtered'      => $this->filterable && $this->hasFilters(),
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
        return $this->catalogQuery()
            ->sort( $this->sort )
            ->builder()
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

    /**
     * The catalog query: the component's scope (category, tag, featured,
     * ids) and the shopper's filters, except the listed filter keys
     * (`category`, `tag`, `price`, `attr.{key}`, `in_stock`, `on_sale`,
     * `rating`, a custom key, or `*` for every filter).
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $except  Filters to leave out.
     *
     * @return CatalogQuery
     */
    protected function catalogQuery( array $except = [] ): CatalogQuery
    {
        $query = app( CatalogQuery::class )->currency( $this->currency() );

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

        if ( ! $this->filterable || in_array( '*', $except, true ) ) {
            return $query;
        }

        $definitions = $this->filterDefinitions();
        $on          = static fn ( string $key ): bool => isset( $definitions[ $key ] ) && ! in_array( $key, $except, true );

        // The chosen category is always inside the scope (sanitizeFilters()),
        // so it narrows the scope rather than widening it.
        if ( $on( 'category' ) && '' !== $this->categoryFilter ) {
            $query->inCategory( $this->categoryFilter );
        }

        if ( $on( 'tag' ) && '' !== $this->tagFilter ) {
            $query->withTag( $this->tagFilter );
        }

        if ( $on( 'price' ) && ( null !== $this->priceMin || null !== $this->priceMax ) ) {
            $query->priceBetween( $this->priceMin, $this->priceMax );
        }

        if ( isset( $definitions['attributes'] ) ) {
            foreach ( $this->attributeFilters as $key => $values ) {
                if ( ! in_array( 'attr.' . $key, $except, true ) && [] !== $values ) {
                    $query->withAttribute( (string) $key, $values );
                }
            }
        }

        if ( $on( 'in_stock' ) && $this->inStock ) {
            $query->inStock();
        }

        if ( $on( 'on_sale' ) && $this->onSale ) {
            $query->onSale();
        }

        if ( $on( 'rating' ) && $this->minRating > 0 ) {
            $query->minRating( (float) $this->minRating );
        }

        foreach ( $definitions as $key => $definition ) {
            if ( ! isset( $definition['apply'] ) || ! $on( $key ) || ! array_key_exists( $key, $this->extraFilters ) ) {
                continue;
            }

            ( $definition['apply'] )( $query, 'toggle' === $definition['type'] ? true : (array) $this->extraFilters[ $key ] );
        }

        return $query;
    }

    /**
     * The filter group definitions, through `catalog.filters`.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    protected function filterDefinitions(): array
    {
        if ( null !== $this->definitions ) {
            return $this->definitions;
        }

        $only     = config( 'artisanpack.ecommerce-storefront-livewire.catalog.filter_attributes' );
        $defaults = [
            'category'   => [ 'type' => 'category', 'label' => __( 'Category' ) ],
            'tag'        => [ 'type' => 'tag', 'label' => __( 'Tag' ) ],
            'price'      => [ 'type' => 'price', 'label' => __( 'Price' ) ],
            'attributes' => [ 'type' => 'attributes', 'only' => is_array( $only ) ? array_values( array_map( 'strval', $only ) ) : null ],
            'in_stock'   => [ 'type' => 'toggle', 'label' => __( 'In stock only' ) ],
            'on_sale'    => [ 'type' => 'toggle', 'label' => __( 'On sale' ) ],
            'rating'     => [ 'type' => 'rating', 'label' => __( 'Customer rating' ) ],
        ];

        // A tag page lists one tag already.
        if ( null !== $this->tag && '' !== (string) $this->tag ) {
            unset( $defaults['tag'] );
        }

        $filtered = applyFilters( 'ap.ecommerceStorefrontLivewire.catalog.filters', $defaults, [
            'category' => $this->category,
            'tag'      => $this->tag,
            'featured' => $this->featured,
        ] );

        $definitions = [];

        foreach ( is_array( $filtered ) ? $filtered : $defaults as $key => $definition ) {
            if ( ! is_string( $key ) || ! is_array( $definition ) ) {
                continue;
            }

            if ( isset( $defaults[ $key ] ) ) {
                // Core groups keep their type; only the label and `only` change.
                $definitions[ $key ] = [
                    'type'  => $defaults[ $key ]['type'],
                    'label' => is_string( $definition['label'] ?? null ) && '' !== $definition['label'] ? $definition['label'] : ( $defaults[ $key ]['label'] ?? '' ),
                ] + ( 'attributes' === $key ? [ 'only' => is_array( $definition['only'] ?? null ) ? array_values( array_map( 'strval', $definition['only'] ) ) : null ] : [] );

                continue;
            }

            $custom = $this->customDefinition( $key, $definition );

            if ( null !== $custom ) {
                $definitions[ $key ] = $custom;
            }
        }

        return $this->definitions = $definitions;
    }

    /**
     * A custom filter group from the hook, or null when it is malformed.
     *
     * @since 1.0.0
     *
     * @param  string                $key         Group key.
     * @param  array<string, mixed>  $definition  Definition.
     *
     * @return array<string, mixed>|null
     */
    protected function customDefinition( string $key, array $definition ): ?array
    {
        $type = $definition['type'] ?? null;

        if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{1,60}$/', $key ) || ! in_array( $type, [ 'options', 'toggle' ], true ) || ! is_callable( $definition['apply'] ?? null ) || ! is_string( $definition['label'] ?? null ) ) {
            return null;
        }

        $options = [];

        foreach ( (array) ( $definition['options'] ?? [] ) as $value => $option ) {
            $label = is_array( $option ) ? ( $option['label'] ?? null ) : $option;

            if ( ( is_string( $value ) || is_int( $value ) ) && is_string( $label ) && '' !== $label ) {
                $options[ (string) $value ] = [ 'label' => $label, 'count' => is_array( $option ) && is_int( $option['count'] ?? null ) ? $option['count'] : null ];
            }
        }

        if ( 'options' === $type && [] === $options ) {
            return null;
        }

        return [ 'type' => $type, 'label' => $definition['label'], 'options' => $options, 'apply' => $definition['apply'] ];
    }

    /**
     * Drops filter values that can't apply: a category outside the scope,
     * an unknown tag, a negative or reversed price range, malformed
     * attribute values, a rating outside 1–4, and custom values the group
     * doesn't offer.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function sanitizeFilters(): void
    {
        $definitions = $this->filterDefinitions();

        if ( '' !== $this->categoryFilter ) {
            $allowed = array_column( app( CategoryPaths::class )->descendants( $this->scopeCategoryId() ), 'slug' );

            if ( ! isset( $definitions['category'] ) || ! in_array( $this->categoryFilter, $allowed, true ) ) {
                $this->categoryFilter = '';
            }
        }

        if ( '' !== $this->tagFilter && ( ! isset( $definitions['tag'] ) || ! ProductTag::query()->where( 'slug', $this->tagFilter )->exists() ) ) {
            $this->tagFilter = '';
        }

        $this->priceMin = null === $this->priceMin || $this->priceMin < 0 ? null : $this->priceMin;
        $this->priceMax = null === $this->priceMax || $this->priceMax < 0 ? null : $this->priceMax;

        if ( null !== $this->priceMin && null !== $this->priceMax && $this->priceMin > $this->priceMax ) {
            [ $this->priceMin, $this->priceMax ] = [ $this->priceMax, $this->priceMin ];
        }

        $only       = $definitions['attributes']['only'] ?? null;
        $attributes = [];

        foreach ( isset( $definitions['attributes'] ) ? $this->attributeFilters : [] as $key => $values ) {
            if ( ! is_string( $key ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{1,100}$/', $key ) || ( is_array( $only ) && ! in_array( $key, $only, true ) ) ) {
                continue;
            }

            $values = array_values( array_unique( array_filter(
                array_map( static fn ( mixed $value ): string => is_scalar( $value ) ? trim( (string) $value ) : '', (array) $values ),
                static fn ( string $value ): bool => '' !== $value && mb_strlen( $value ) <= 255,
            ) ) );

            if ( [] !== $values ) {
                $attributes[ $key ] = array_slice( $values, 0, 50 );
            }
        }

        $this->attributeFilters = $attributes;
        $this->minRating        = $this->minRating >= 1 && $this->minRating <= 4 ? $this->minRating : 0;

        $extra = [];

        foreach ( $this->extraFilters as $key => $value ) {
            $definition = is_string( $key ) ? ( $definitions[ $key ] ?? null ) : null;

            if ( ! isset( $definition['apply'] ) ) {
                continue;
            }

            if ( 'toggle' === $definition['type'] ) {
                if ( in_array( $value, [ '1', 1, true ], true ) ) {
                    $extra[ $key ] = '1';
                }

                continue;
            }

            $values = array_values( array_intersect( array_map( 'strval', array_filter( (array) $value, 'is_scalar' ) ), array_map( 'strval', array_keys( $definition['options'] ) ) ) );

            if ( [] !== $values ) {
                $extra[ $key ] = array_values( array_unique( $values ) );
            }
        }

        $this->extraFilters = $extra;
    }

    /**
     * Removes a custom group's value (or one of its values).
     *
     * @since 1.0.0
     *
     * @param  string       $group  Group key.
     * @param  string|null  $value  Option value.
     *
     * @return void
     */
    protected function removeExtraFilter( string $group, ?string $value ): void
    {
        if ( null === $value || ! is_array( $this->extraFilters[ $group ] ?? null ) ) {
            unset( $this->extraFilters[ $group ] );

            return;
        }

        $this->extraFilters[ $group ] = array_values( array_diff( $this->extraFilters[ $group ], [ $value ] ) );
    }

    /**
     * Facets over the listing without some filters (memoised per request).
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $except  Filters to leave out.
     *
     * @return array<string, mixed>
     */
    protected function facets( array $except = [] ): array
    {
        $key = implode( '|', $except );

        return $this->facetCache[ $key ] ??= $this->catalogQuery( $except )->facets();
    }

    /**
     * Whether any shopper filter is on.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function hasFilters(): bool
    {
        return '' !== $this->categoryFilter
            || '' !== $this->tagFilter
            || null !== $this->priceMin
            || null !== $this->priceMax
            || [] !== $this->attributeFilters
            || $this->inStock
            || $this->onSale
            || $this->minRating > 0
            || [] !== $this->extraFilters;
    }

    /**
     * The id of the category the component is scoped to.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    protected function scopeCategoryId(): ?int
    {
        if ( null === $this->category || '' === (string) $this->category ) {
            return null;
        }

        $paths = app( CategoryPaths::class );
        $node  = is_int( $this->category ) || ctype_digit( (string) $this->category )
            ? $paths->node( (int) $this->category )
            : $paths->bySlug( (string) $this->category );

        // An unknown scope lists nothing, so it offers no sub-categories.
        return $node['id'] ?? -1;
    }

    /**
     * The filter groups to render, with their options and counts.
     *
     * Option universes come from the scope alone (no shopper filters), so
     * an option that the other filters rule out is shown disabled rather
     * than disappearing. Counts come from the listing with every filter
     * except the group's own.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function filterGroups(): array
    {
        // Filters are an aid: when the engine can't count, list without them.
        try {
            return $this->buildFilterGroups();
        } catch ( Throwable $exception ) {
            report( $exception );

            return [];
        }
    }

    /**
     * Builds the filter groups (see {@see self::filterGroups()}).
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildFilterGroups(): array
    {
        $groups = [];
        $scope  = $this->facets( [ '*' ] );

        $counts = fn ( string $own ): array => ( $this->hasFilters() ? $this->facets( [ $own ] ) : $scope );

        foreach ( $this->filterDefinitions() as $key => $definition ) {
            $group = match ( $definition['type'] ) {
                'category'   => $this->categoryGroup( $definition, $counts( 'category' )['categories'] ?? [] ),
                'tag'        => $this->tagGroup( $definition, array_keys( $scope['tags'] ?? [] ), $counts( 'tag' )['tags'] ?? [] ),
                'price'      => $this->priceGroup( $definition, $scope['price'] ?? [] ),
                'attributes' => $this->attributeGroups( $definition, $scope['attributes'] ?? [] ),
                'toggle'     => $this->toggleGroup( $key, $definition ),
                'rating'     => $this->ratingGroup( $definition ),
                default      => $this->customGroup( $key, $definition ),
            };

            if ( null === $group ) {
                continue;
            }

            // Attribute definitions expand into one group per attribute.
            foreach ( isset( $group['key'] ) ? [ $group ] : $group as $one ) {
                $groups[] = $one;
            }
        }

        return $groups;
    }

    /**
     * The category tree group.
     *
     * Each option counts its own products plus its sub-categories'; a
     * product filed under both a category and its child is counted twice,
     * which only affects the number shown.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $definition  Definition.
     * @param  array<int, int>       $counts      Products per category id.
     *
     * @return array<string, mixed>|null
     */
    protected function categoryGroup( array $definition, array $counts ): ?array
    {
        $paths = app( CategoryPaths::class );
        $nodes = $paths->descendants( $this->scopeCategoryId() );

        if ( [] === $nodes ) {
            return null;
        }

        $options = [];

        foreach ( $nodes as $node ) {
            $count = 0;

            foreach ( [ $node, ...$paths->descendants( $node['id'] ) ] as $member ) {
                $count += (int) ( $counts[ $member['id'] ] ?? 0 );
            }

            $selected  = $node['slug'] === $this->categoryFilter;
            $options[] = [
                'value'    => $node['slug'],
                'label'    => $node['name'],
                'count'    => $count,
                'depth'    => $node['depth'],
                'selected' => $selected,
                'disabled' => 0 === $count && ! $selected,
            ];
        }

        return [ 'key' => 'category', 'type' => 'category', 'label' => $definition['label'], 'options' => $options ];
    }

    /**
     * The tag group: tags the scope's products carry.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $definition  Definition.
     * @param  array<int, int>       $tagIds      Tags in the scope.
     * @param  array<int, int>       $counts      Products per tag id.
     *
     * @return array<string, mixed>|null
     */
    protected function tagGroup( array $definition, array $tagIds, array $counts ): ?array
    {
        $tags = ProductTag::query()
            ->where( static fn ( $query ) => $query->whereKey( $tagIds ?: [ 0 ] ) )
            ->when( '' !== $this->tagFilter, fn ( $query ) => $query->orWhere( 'slug', $this->tagFilter ) )
            ->orderBy( 'name' )
            ->get( [ 'id', 'name', 'slug' ] );

        if ( $tags->isEmpty() ) {
            return null;
        }

        $options = $tags->map( function ( ProductTag $tag ) use ( $counts ): array {
            $count    = (int) ( $counts[ $tag->id ] ?? 0 );
            $selected = $tag->slug === $this->tagFilter;

            return [ 'value' => (string) $tag->slug, 'label' => (string) $tag->name, 'count' => $count, 'selected' => $selected, 'disabled' => 0 === $count && ! $selected ];
        } )->all();

        return [ 'key' => 'tag', 'type' => 'tag', 'label' => $definition['label'], 'options' => $options ];
    }

    /**
     * The price range group, bounded by the scope's cheapest and dearest
     * products.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $definition  Definition.
     * @param  array<string, mixed>  $price       The scope's price facet.
     *
     * @return array<string, mixed>|null
     */
    protected function priceGroup( array $definition, array $price ): ?array
    {
        $min = $price['min'] ?? null;
        $max = $price['max'] ?? null;

        if ( null === $min || null === $max || ( $min === $max && null === $this->priceMin && null === $this->priceMax ) ) {
            return null;
        }

        // Whole currency units for 2-decimal currencies.
        $step = 100;

        return [
            'key'      => 'price',
            'type'     => 'price',
            'label'    => $definition['label'],
            'min'      => intdiv( (int) $min, $step ) * $step,
            'max'      => (int) ( ceil( (int) $max / $step ) * $step ),
            'step'     => $step,
            'currency' => (string) ( $price['currency'] ?? $this->currency() ),
            'from'     => $this->priceMin,
            'to'       => $this->priceMax,
        ];
    }

    /**
     * One group per attribute the scope's products have, with swatches
     * where the values have them.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>                                                    $definition  Definition.
     * @param  array<string, array<int, array{value: string, label: string, count: int}>>  $universe    The scope's attribute facet.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function attributeGroups( array $definition, array $universe ): array
    {
        $only = $definition['only'] ?? null;
        $keys = array_values( array_filter( array_map( 'strval', array_keys( $universe ) ), static fn ( string $key ): bool => ! is_array( $only ) || in_array( $key, $only, true ) ) );

        if ( [] === $keys ) {
            return [];
        }

        $attributes = ( new ProductAttribute() )->getTable();
        $values     = ( new ProductAttributeValue() )->getTable();
        $labels     = [];
        $swatches   = [];

        foreach ( DB::table( $attributes )->whereIn( "{$attributes}.key", $keys )->groupBy( "{$attributes}.key" )->selectRaw( "{$attributes}.key as attribute_key, MIN({$attributes}.label) as label" )->get() as $row ) {
            $labels[ (string) $row->attribute_key ] = (string) $row->label;
        }

        DB::table( $values )
            ->join( $attributes, "{$attributes}.id", '=', "{$values}.product_attribute_id" )
            ->whereIn( "{$attributes}.key", $keys )
            ->whereNotNull( "{$values}.swatch" )
            ->groupBy( "{$attributes}.key", "{$values}.value" )
            ->selectRaw( "{$attributes}.key as attribute_key, {$values}.value as value, MIN({$values}.swatch) as swatch" )
            ->get()
            ->each( static function ( object $row ) use ( &$swatches ): void {
                $swatches[ (string) $row->attribute_key ][ (string) $row->value ] = (string) $row->swatch;
            } );

        $groups = [];

        foreach ( $keys as $key ) {
            $selected = $this->attributeFilters[ $key ] ?? [];
            $counts   = [];

            foreach ( ( $this->hasFilters() ? $this->facets( [ 'attr.' . $key ] ) : [ 'attributes' => $universe ] )['attributes'][ $key ] ?? [] as $row ) {
                $counts[ $row['value'] ] = (int) $row['count'];
            }

            $options = [];

            foreach ( $universe[ $key ] as $row ) {
                $count     = $counts[ $row['value'] ] ?? 0;
                $isChosen  = in_array( $row['value'], $selected, true );
                $options[] = [
                    'value'    => $row['value'],
                    'label'    => '' !== $row['label'] ? $row['label'] : $row['value'],
                    'count'    => $count,
                    'swatch'   => $swatches[ $key ][ $row['value'] ] ?? null,
                    'selected' => $isChosen,
                    'disabled' => 0 === $count && ! $isChosen,
                ];
            }

            $groups[] = [
                'key'       => 'attr.' . $key,
                'type'      => 'attribute',
                'attribute' => $key,
                'label'     => $labels[ $key ] ?? $key,
                'options'   => $options,
            ];
        }

        return $groups;
    }

    /**
     * The in-stock or on-sale toggle, with how many products it leaves.
     *
     * @since 1.0.0
     *
     * @param  string                $key         `in_stock` or `on_sale`.
     * @param  array<string, mixed>  $definition  Definition.
     *
     * @return array<string, mixed>|null
     */
    protected function toggleGroup( string $key, array $definition ): ?array
    {
        if ( isset( $definition['apply'] ) ) {
            return $this->customGroup( $key, $definition );
        }

        $query = $this->catalogQuery( [ $key ] );
        'in_stock' === $key ? $query->inStock() : $query->onSale();

        $count    = $query->builder( false )->count();
        $selected = 'in_stock' === $key ? $this->inStock : $this->onSale;

        return [
            'key'      => $key,
            'type'     => 'toggle',
            'label'    => $definition['label'],
            'model'    => 'in_stock' === $key ? 'inStock' : 'onSale',
            'count'    => $count,
            'selected' => $selected,
            'disabled' => 0 === $count && ! $selected,
        ];
    }

    /**
     * The minimum-rating group (4 stars and up … 1 star and up).
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $definition  Definition.
     *
     * @return array<string, mixed>
     */
    protected function ratingGroup( array $definition ): array
    {
        $column = ( new Product() )->qualifyColumn( 'avg_rating' );
        $row    = $this->catalogQuery( [ 'rating' ] )->builder( false )->toBase()
            ->selectRaw( "SUM(CASE WHEN {$column} >= 4 THEN 1 ELSE 0 END) as r4, SUM(CASE WHEN {$column} >= 3 THEN 1 ELSE 0 END) as r3, SUM(CASE WHEN {$column} >= 2 THEN 1 ELSE 0 END) as r2, SUM(CASE WHEN {$column} >= 1 THEN 1 ELSE 0 END) as r1" )
            ->first();

        $options = [];

        foreach ( [ 4, 3, 2, 1 ] as $stars ) {
            $count     = (int) ( $row->{'r' . $stars} ?? 0 );
            $selected  = $stars === $this->minRating;
            $options[] = [
                'value'    => (string) $stars,
                'label'    => trans_choice( ':count star & up|:count stars & up', $stars, [ 'count' => $stars ] ),
                'count'    => $count,
                'selected' => $selected,
                'disabled' => 0 === $count && ! $selected,
            ];
        }

        return [ 'key' => 'rating', 'type' => 'rating', 'label' => $definition['label'], 'options' => $options ];
    }

    /**
     * A group added through the hook.
     *
     * @since 1.0.0
     *
     * @param  string                $key         Group key.
     * @param  array<string, mixed>  $definition  Definition.
     *
     * @return array<string, mixed>
     */
    protected function customGroup( string $key, array $definition ): array
    {
        $value = $this->extraFilters[ $key ] ?? null;

        if ( 'toggle' === $definition['type'] ) {
            return [ 'key' => $key, 'type' => 'custom-toggle', 'label' => $definition['label'], 'count' => null, 'selected' => '1' === $value, 'disabled' => false ];
        }

        $options = [];

        foreach ( $definition['options'] as $optionValue => $option ) {
            $selected  = in_array( (string) $optionValue, (array) $value, true );
            $options[] = [
                'value'    => (string) $optionValue,
                'label'    => $option['label'],
                'count'    => $option['count'],
                'swatch'   => null,
                'selected' => $selected,
                'disabled' => 0 === $option['count'] && ! $selected,
            ];
        }

        return [ 'key' => $key, 'type' => 'custom-options', 'label' => $definition['label'], 'options' => $options ];
    }

    /**
     * The active filters as removable badges.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $groups  The rendered groups.
     *
     * @return array<int, array{group: string, value: string|null, label: string}>
     */
    protected function activeFilters( array $groups ): array
    {
        $active = [];

        foreach ( $groups as $group ) {
            switch ( $group['type'] ) {
                case 'price':
                    if ( null === $group['from'] && null === $group['to'] ) {
                        break;
                    }

                    $format = static fn ( int $amount ): string => MoneyFormatter::format( $amount, $group['currency'] );

                    $active[] = [ 'group' => 'price', 'value' => null, 'label' => match ( true ) {
                        null === $group['to']   => __( 'From :min', [ 'min' => $format( $group['from'] ) ] ),
                        null === $group['from'] => __( 'Up to :max', [ 'max' => $format( $group['to'] ) ] ),
                        default                 => __( ':min to :max', [ 'min' => $format( $group['from'] ), 'max' => $format( $group['to'] ) ] ),
                    } ];

                    break;

                case 'toggle':
                case 'custom-toggle':
                    if ( $group['selected'] ) {
                        $active[] = [ 'group' => $group['key'], 'value' => null, 'label' => $group['label'] ];
                    }

                    break;

                default:
                    $multiple = in_array( $group['type'], [ 'attribute', 'custom-options' ], true );

                    foreach ( $group['options'] as $option ) {
                        if ( $option['selected'] ) {
                            $active[] = [
                                'group' => $group['key'],
                                'value' => $multiple ? $option['value'] : null,
                                'label' => in_array( $group['type'], [ 'attribute', 'custom-options' ], true )
                                    ? __( ':group: :value', [ 'group' => $group['label'], 'value' => $option['label'] ] )
                                    : $option['label'],
                            ];
                        }
                    }
            }
        }

        return $active;
    }
}
