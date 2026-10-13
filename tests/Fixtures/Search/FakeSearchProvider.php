<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Search;

use ArtisanPackUI\Ecommerce\Contracts\SearchProvider;
use ArtisanPackUI\Ecommerce\Search\DatabaseSearchProvider;
use ArtisanPackUI\Ecommerce\Search\SearchQuery;
use ArtisanPackUI\Ecommerce\Search\SearchResult;
use RuntimeException;

/**
 * A search provider that answers like the database provider, plus the
 * suggestions it was built with, records every query, and can fail.
 */
class FakeSearchProvider implements SearchProvider
{
    /**
     * Every query asked.
     *
     * @var array<int, SearchQuery>
     */
    public array $queries = [];

    /**
     * @param  array<int, string>  $suggestions  "Did you mean" suggestions.
     * @param  bool                $fails        Throw instead of answering.
     * @param  array<string, string>  $aliases   Term => term to search instead (a typo-tolerant engine).
     */
    public function __construct(
        public array $suggestions = [],
        public bool $fails = false,
        public array $aliases = [],
    ) {
    }

    public function key(): string
    {
        return 'fake-search';
    }

    public function label(): string
    {
        return 'Fake search';
    }

    public function search( SearchQuery $query ): SearchResult
    {
        $this->queries[] = $query;

        if ( $this->fails ) {
            throw new RuntimeException( 'The search engine is down.' );
        }

        $term   = $this->aliases[ $query->term ] ?? $query->term;
        $result = ( new DatabaseSearchProvider() )->search( new SearchQuery( $term, $query->filters, $query->sort, $query->page, $query->perPage, $query->currency, $query->with ) );

        return new SearchResult( $result->items, $result->total, $result->facets, $this->suggestions, $result->page, $result->perPage );
    }
}
