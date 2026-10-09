<?php

/**
 * Search page component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Search;

use ArtisanPackUI\Ecommerce\Catalog\CatalogQuery;
use ArtisanPackUI\Ecommerce\Registries\SearchProviderRegistry;
use ArtisanPackUI\Ecommerce\Search\SearchQuery;
use ArtisanPackUI\Ecommerce\Search\SearchResult;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Catalog\Index as CatalogIndex;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\ProductCard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-search />`
 *
 * The search page (spec §7.7): the term goes to the engine's active
 * `SearchProvider` (the database/Scout default, or a search satellite such
 * as Meilisearch), and the results fill the catalog grid with the
 * catalog's filter panel built from the provider's facets. Relevance is
 * the default sort; the catalog's sorts follow. When the provider returns
 * suggestions they show as "Did you mean …?" links.
 *
 * The term (`q`), filters, sort, and page live in the query string, so a
 * search can be shared. An empty term searches nothing (the page invites a
 * search instead); a term over 200 characters is a validation error. When
 * the provider fails, the page says search is unavailable rather than
 * erroring.
 *
 * Filter groups added through `ap.ecommerceStorefrontLivewire.catalog.filters`
 * with an `apply` callback are left out here: a provider only understands
 * `CatalogQuery`'s filter keys. Callbacks get `search` (the term) in their
 * context array.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Index extends CatalogIndex
{
    /**
     * The longest search term, in characters (matches the REST API).
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_TERM_LENGTH = 200;

    /**
     * The deepest result a shopper can page to; deep pages make a search
     * engine scan the whole catalog (matches the REST API).
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_RESULTS = 10_000;

    /**
     * The search term.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( as: 'q', history: true, except: '' )]
    public string $q = '';

    /**
     * The search for this request (memoised; null before it runs).
     *
     * @since 1.0.0
     *
     * @var SearchResult|null
     */
    protected ?SearchResult $result = null;

    /**
     * Whether the provider failed this request.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    protected bool $failed = false;

    /**
     * Cleans the term from the query string, then sets up the catalog.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->q = self::normalizeTerm( $this->q );

        parent::mount();
        $this->checkTerm();
    }

    /**
     * Cleans a changed term and starts again from page one.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedQ(): void
    {
        $this->q = self::normalizeTerm( $this->q );

        $this->checkTerm();
        $this->resetPage();
    }

    /**
     * Runs the typed search (the form's submit).
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function search(): void
    {
        $this->updatedQ();
    }

    /**
     * Searches for a "Did you mean" suggestion instead, keeping the filters.
     *
     * @since 1.0.0
     *
     * @param  string  $suggestion  The suggested term.
     *
     * @return void
     */
    public function useSuggestion( string $suggestion ): void
    {
        $this->q = $suggestion;

        $this->updatedQ();
    }

    /**
     * Trims a term and drops control characters.
     *
     * @since 1.0.0
     *
     * @param  string  $term  The term.
     *
     * @return string
     */
    public static function normalizeTerm( string $term ): string
    {
        return trim( (string) preg_replace( '/[\p{Cc}\s]+/u', ' ', $term ) );
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
        $data     = $this->viewData();
        $searched = $this->searchable() && ! $this->failed;
        $total    = $data['products']->total();

        return view( 'ecommerce-storefront::livewire.search.index', [
            ...$data,
            'term'         => $this->q,
            'searched'     => $searched,
            'suggestions'  => $this->suggestions(),
            'resultsLabel' => $searched
                ? trans_choice( ':count result for ":term"|:count results for ":term"', $total, [ 'count' => $total, 'term' => $this->q ] )
                : '',
            'emptyState'   => $this->emptyState( $data['filtered'] ),
        ] );
    }

    /**
     * Relevance first, then the catalog's sorts.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function sorts(): array
    {
        return [ 'relevance' => __( 'Relevance' ) ] + parent::sorts();
    }

    /**
     * `$sort` when it is offered, else relevance.
     *
     * @since 1.0.0
     *
     * @param  string  $sort  The requested sort.
     *
     * @return string
     */
    protected function validSort( string $sort ): string
    {
        return array_key_exists( $sort, $this->sorts() ) ? $sort : 'relevance';
    }

    /**
     * The current page of results from the provider.
     *
     * @since 1.0.0
     *
     * @return LengthAwarePaginatorContract<int, \ArtisanPackUI\Ecommerce\Models\Product>
     */
    protected function products(): LengthAwarePaginatorContract
    {
        $result = $this->result();

        return new LengthAwarePaginator(
            $result?->items ?? new Collection(),
            $result?->total ?? 0,
            $this->perPage,
            $this->currentPage(),
            [ 'path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page' ],
        );
    }

    /**
     * The catalog query with the term, for the counts the provider's facets
     * don't carry (on sale, rating).
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $except  Filters to leave out.
     *
     * @return CatalogQuery
     */
    protected function catalogQuery( array $except = [] ): CatalogQuery
    {
        return parent::catalogQuery( $except )->search( $this->q );
    }

    /**
     * Facets from the provider: the current search's own, or a search
     * without some filters (memoised per request).
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

        if ( isset( $this->facetCache[ $key ] ) ) {
            return $this->facetCache[ $key ];
        }

        if ( [] === $except ) {
            return $this->facetCache[ $key ] = $this->result()?->facets ?? [];
        }

        return $this->facetCache[ $key ] = $this->runSearch( $this->searchFilters( $except ), 1, 1 )->facets;
    }

    /**
     * No filters until there are results to filter.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function filterGroups(): array
    {
        if ( null === $this->result() ) {
            return [];
        }

        return parent::filterGroups();
    }

    /**
     * The in-stock toggle counts from the provider's stock facet; the other
     * toggles count through the catalog query.
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
        if ( 'in_stock' !== $key || isset( $definition['apply'] ) ) {
            return parent::toggleGroup( $key, $definition );
        }

        $count = (int) ( $this->facets( [ 'in_stock' ] )['stock']['in_stock'] ?? 0 );

        return [
            'key'      => $key,
            'type'     => 'toggle',
            'label'    => $definition['label'],
            'model'    => 'inStock',
            'count'    => $count,
            'selected' => $this->inStock,
            'disabled' => 0 === $count && ! $this->inStock,
        ];
    }

    /**
     * The catalog's filter context plus the search term.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function filterContext(): array
    {
        return [ ...parent::filterContext(), 'search' => $this->q ];
    }

    /**
     * Drops custom filter groups: providers only understand
     * `CatalogQuery`'s filter keys.
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
        return null;
    }

    /**
     * Whether there is a term to search for.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function searchable(): bool
    {
        return '' !== $this->q && mb_strlen( $this->q ) <= self::MAX_TERM_LENGTH;
    }

    /**
     * Flags a term that is too long.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function checkTerm(): void
    {
        $this->resetErrorBag( 'q' );

        if ( mb_strlen( $this->q ) > self::MAX_TERM_LENGTH ) {
            $this->addError( 'q', __( 'Search for at most :max characters.', [ 'max' => self::MAX_TERM_LENGTH ] ) );
        }
    }

    /**
     * The search for this request, or null when there is no term or the
     * provider failed.
     *
     * @since 1.0.0
     *
     * @return SearchResult|null
     */
    protected function result(): ?SearchResult
    {
        if ( null !== $this->result || $this->failed || ! $this->searchable() ) {
            return $this->result;
        }

        try {
            return $this->result = $this->runSearch( $this->searchFilters(), $this->currentPage(), $this->perPage, ProductCard::relations() );
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->failed = true;

            return null;
        }
    }

    /**
     * Asks the active provider.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $filters  `CatalogQuery::fromParameters()` filters.
     * @param  int                   $page     Page.
     * @param  int                   $perPage  Page size.
     * @param  array<int, string>    $with     Relations to eager load.
     *
     * @return SearchResult
     */
    protected function runSearch( array $filters, int $page, int $perPage, array $with = [] ): SearchResult
    {
        return app( SearchProviderRegistry::class )->active()->search( new SearchQuery(
            $this->q,
            $filters,
            $this->sort,
            $page,
            $perPage,
            $this->currency(),
            $with,
        ) );
    }

    /**
     * The shopper's filters as `CatalogQuery::fromParameters()` filters,
     * except the listed ones (`*` for none).
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $except  Filters to leave out.
     *
     * @return array<string, mixed>
     */
    protected function searchFilters( array $except = [] ): array
    {
        if ( in_array( '*', $except, true ) ) {
            return [];
        }

        $definitions = $this->filterDefinitions();
        $on          = static fn ( string $key ): bool => isset( $definitions[ $key ] ) && ! in_array( $key, $except, true );
        $filters     = [];

        if ( $on( 'category' ) && '' !== $this->categoryFilter ) {
            $filters['category'] = $this->categoryFilter;
        }

        if ( $on( 'tag' ) && '' !== $this->tagFilter ) {
            $filters['tag'] = $this->tagFilter;
        }

        if ( $on( 'price' ) ) {
            $filters += array_filter( [ 'price_min' => $this->priceMin, 'price_max' => $this->priceMax ], static fn ( ?int $amount ): bool => null !== $amount );
        }

        if ( isset( $definitions['attributes'] ) ) {
            foreach ( $this->attributeFilters as $key => $values ) {
                if ( ! in_array( 'attr.' . $key, $except, true ) && [] !== $values ) {
                    $filters['attributes'][ $key ] = $values;
                }
            }
        }

        if ( $on( 'in_stock' ) && $this->inStock ) {
            $filters['in_stock'] = '1';
        }

        if ( $on( 'on_sale' ) && $this->onSale ) {
            $filters['on_sale'] = '1';
        }

        if ( $on( 'rating' ) && $this->minRating > 0 ) {
            $filters['min_rating'] = $this->minRating;
        }

        return $filters;
    }

    /**
     * The page to show, capped at {@see self::MAX_RESULTS} deep.
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected function currentPage(): int
    {
        return max( 1, min( (int) $this->getPage(), intdiv( self::MAX_RESULTS, max( 1, $this->perPage ) ) ) );
    }

    /**
     * The provider's "did you mean" suggestions, without the term itself.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function suggestions(): array
    {
        $suggestions = [];

        foreach ( $this->result()?->suggestions ?? [] as $suggestion ) {
            $suggestion = is_string( $suggestion ) ? self::normalizeTerm( $suggestion ) : '';

            if ( '' !== $suggestion && mb_strlen( $suggestion ) <= self::MAX_TERM_LENGTH && 0 !== strcasecmp( $suggestion, $this->q ) ) {
                $suggestions[] = $suggestion;
            }
        }

        return array_slice( array_values( array_unique( $suggestions ) ), 0, 3 );
    }

    /**
     * What the grid shows when it is empty: an invitation to search, an
     * outage notice, or "no results" with tips.
     *
     * @since 1.0.0
     *
     * @param  bool  $filtered  Whether a filter is on.
     *
     * @return array{key: string, icon: string, title: string, description: string|null, tips: array<int, string>}
     */
    protected function emptyState( bool $filtered ): array
    {
        if ( $this->failed ) {
            return [
                'key'         => 'unavailable',
                'icon'        => 'o-exclamation-triangle',
                'title'       => __( 'Search isn\'t available right now' ),
                'description' => __( 'Please try again in a moment, or browse the shop instead.' ),
                'tips'        => [],
            ];
        }

        if ( ! $this->searchable() ) {
            return [
                'key'         => 'idle',
                'icon'        => 'o-magnifying-glass',
                'title'       => __( 'What are you looking for?' ),
                'description' => __( 'Search by product name, SKU, or description.' ),
                'tips'        => [],
            ];
        }

        return [
            'key'         => 'no-results',
            'icon'        => 'o-magnifying-glass',
            'title'       => __( 'No results for ":term"', [ 'term' => $this->q ] ),
            'description' => __( 'Try one of these:' ),
            'tips'        => array_values( array_filter( [
                __( 'Check the spelling.' ),
                __( 'Use fewer or more general words.' ),
                $filtered ? __( 'Remove a filter or two.' ) : null,
            ] ) ),
        ];
    }
}
