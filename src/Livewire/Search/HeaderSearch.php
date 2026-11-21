<?php

/**
 * Header search component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Search;

use ArtisanPackUI\Ecommerce\Exceptions\RateLimitExceededException;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use ArtisanPackUI\Ecommerce\RateLimiting\EcommerceRateLimiter;
use ArtisanPackUI\Ecommerce\Registries\SearchProviderRegistry;
use ArtisanPackUI\Ecommerce\Search\SearchQuery;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\ProductCard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-search-box />`
 *
 * The header search box (spec §7.7): as the shopper types (debounced), the
 * top five products (image, name with the match marked, category, price)
 * from the engine's active `SearchProvider` and up to five matching
 * categories open in a popover, then "See all N results".
 * The box is an ARIA combobox: arrow keys move through the results, Enter
 * opens the highlighted one (or the search page when none is), and Escape
 * closes the popover. Without JavaScript it is a plain form to the search
 * page.
 *
 * Lookups count against the engine's `ecommerce.catalog.read` limit; over
 * it, the popover asks the shopper to press Enter instead. Hosts embed it
 * with `<x-artisanpack-ec-search-box />` or the
 * `ecommerce-storefront::partials.header.search` partial.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class HeaderSearch extends Component
{
    /**
     * The shortest term that is looked up, in characters.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MIN_LENGTH = 2;

    /**
     * Products, and categories, shown at most.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const LIMIT = 5;

    /**
     * The typed term.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $q = '';

    /**
     * The input's id, so a page can hold more than one box.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $inputId = 'ecommerce-header-search';

    /**
     * Whether the shopper typed this request (the first render shows no
     * suggestions, even with a term from the query string).
     *
     * @since 1.0.0
     *
     * @var bool
     */
    protected bool $typed = false;

    /**
     * Starts from the search page's term, and keeps the id usable.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $term = request()->query( 'q' );

        $this->q       = is_string( $term ) ? mb_substr( Index::normalizeTerm( $term ), 0, Index::MAX_TERM_LENGTH ) : '';
        $this->inputId = 1 === preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $this->inputId ) ? $this->inputId : 'ecommerce-header-search';
    }

    /**
     * Cleans the term and checks its length.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedQ(): void
    {
        $this->q     = Index::normalizeTerm( $this->q );
        $this->typed = true;

        $this->resetErrorBag( 'q' );

        if ( mb_strlen( $this->q ) > Index::MAX_TERM_LENGTH ) {
            $this->addError( 'q', __( 'Search for at most :max characters.', [ 'max' => Index::MAX_TERM_LENGTH ] ) );
        }
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
        $searchUrl  = Route::has( 'artisanpack.ecommerce.storefront.search' ) ? route( 'artisanpack.ecommerce.storefront.search' ) : null;
        $lookup     = $this->typed && mb_strlen( $this->q ) >= self::MIN_LENGTH && mb_strlen( $this->q ) <= Index::MAX_TERM_LENGTH;
        $state      = 'idle';
        $products   = [];
        $categories = [];
        $total      = 0;

        if ( $lookup ) {
            try {
                [ $products, $categories, $total ] = app( EcommerceRateLimiter::class )->attempt(
                    'ecommerce.catalog.read',
                    app( StorefrontCart::class )->subject(),
                    function (): array {
                        [ $products, $total ] = $this->products();

                        return [ $products, $this->categories(), $total ];
                    },
                );

                $state = 'results';
            } catch ( RateLimitExceededException ) {
                $state = 'throttled';
            } catch ( Throwable $exception ) {
                report( $exception );

                $state = 'failed';
            }
        }

        return view( 'ecommerce-storefront::livewire.search.header-search', [
            'state'      => $state,
            'products'   => $products,
            'categories' => $categories,
            'searchUrl'  => $searchUrl,
            'allUrl'     => null === $searchUrl || 'results' !== $state || [] === [ ...$products, ...$categories ] ? null : $searchUrl . '?' . http_build_query( [ 'q' => $this->q ] ),
            'total'      => max( $total, count( $products ) ),
            'listboxId'  => $this->inputId . '-results',
        ] );
    }

    /**
     * Splits `$text` around the words of `$term` (case-insensitively), so
     * the view can mark each match.
     *
     * @since 2.0.0
     *
     * @param  string  $text  The text to split, such as a product name.
     * @param  string  $term  The typed term.
     *
     * @return array<int, array{text: string, match: bool}>
     */
    public static function highlight( string $text, string $term ): array
    {
        $words = array_values( array_filter( preg_split( '/\s+/u', trim( $term ) ) ?: [], static fn ( string $word ): bool => '' !== $word ) );

        if ( '' === $text || [] === $words ) {
            return [ [ 'text' => $text, 'match' => false ] ];
        }

        usort( $words, static fn ( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a ) );

        $pattern = '/(' . implode( '|', array_map( static fn ( string $word ): string => preg_quote( $word, '/' ), $words ) ) . ')/iu';
        $pieces  = preg_split( $pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE );

        if ( false === $pieces ) {
            return [ [ 'text' => $text, 'match' => false ] ];
        }

        $parts = [];

        foreach ( $pieces as $index => $piece ) {
            if ( '' !== $piece ) {
                $parts[] = [ 'text' => $piece, 'match' => 1 === $index % 2 ];
            }
        }

        return $parts;
    }

    /**
     * The top products from the active provider, with their image, name
     * split around the match, first category, price and link, and the
     * provider's total number of matches.
     *
     * @since 1.0.0
     * @since 2.0.0 Adds `parts` and `category`, and returns the total too.
     *
     * @return array{0: array<int, array{id: int, name: string, parts: array<int, array{text: string, match: bool}>, category: string|null, url: string|null, image: array{url: string, srcset: string|null, alt: string}|null, price: \ArtisanPackUI\Ecommerce\Pricing\DisplayPrice|null}>, 1: int}
     */
    protected function products(): array
    {
        $carts    = app( StorefrontCart::class );
        $currency = $carts->currency();
        $customer = $carts->customer();
        $prices   = app( PriceDisplayResolver::class );
        $hasRoute = Route::has( 'artisanpack.ecommerce.storefront.product' );

        $result = app( SearchProviderRegistry::class )->active()->search( new SearchQuery( $this->q, [], 'relevance', 1, self::LIMIT, $currency, ProductCard::relations() ) );

        // A provider's index may still hold products whose type package is
        // uninstalled; their pages 404.
        $products = $result->items
            ->filter( static fn ( mixed $product ): bool => $product instanceof Product && ! $product->typeIsMissing() )
            ->take( self::LIMIT );

        ProductImages::preloadCards( $products );

        $term = $this->q;

        return [
            $products
                ->map( static fn ( Product $product ): array => [
                    'id'       => (int) $product->id,
                    'name'     => (string) $product->name,
                    'parts'    => self::highlight( (string) $product->name, $term ),
                    'category' => $product->relationLoaded( 'categories' ) ? $product->categories->first()?->name : null,
                    'url'      => $hasRoute ? route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) : null,
                    'image'    => ProductImages::card( $product ),
                    'price'    => $prices->for( $product, $currency, null, $customer ),
                ] )
                ->values()
                ->all(),
            (int) $result->total,
        ];
    }

    /**
     * Up to {@see self::LIMIT} categories whose name holds the term.
     *
     * @since 1.0.0
     * @since 2.0.0 Adds `parts`.
     *
     * @return array<int, array{id: int, name: string, parts: array<int, array{text: string, match: bool}>, url: string|null}>
     */
    protected function categories(): array
    {
        $paths      = app( CategoryPaths::class );
        $categories = [];

        foreach ( $paths->nodes() as $node ) {
            if ( false !== mb_stripos( $node['name'], $this->q ) ) {
                $categories[] = [ 'id' => $node['id'], 'name' => $node['name'], 'parts' => self::highlight( $node['name'], $this->q ), 'url' => $paths->url( $node['id'] ) ];
            }

            if ( count( $categories ) >= self::LIMIT ) {
                break;
            }
        }

        return $categories;
    }
}
