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
 * top five products (image, name, price) from the engine's active
 * `SearchProvider` and up to five matching categories open in a popover.
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

        if ( $lookup ) {
            try {
                [ $products, $categories ] = app( EcommerceRateLimiter::class )->attempt(
                    'ecommerce.catalog.read',
                    app( StorefrontCart::class )->subject(),
                    fn (): array => [ $this->products(), $this->categories() ],
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
            'listboxId'  => $this->inputId . '-results',
        ] );
    }

    /**
     * The top products from the active provider, with their image, price,
     * and link.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: int, name: string, url: string|null, image: array{url: string, srcset: string|null, alt: string}|null, price: \ArtisanPackUI\Ecommerce\Pricing\DisplayPrice|null}>
     */
    protected function products(): array
    {
        $carts    = app( StorefrontCart::class );
        $currency = $carts->currency();
        $prices   = app( PriceDisplayResolver::class );
        $hasRoute = Route::has( 'artisanpack.ecommerce.storefront.product' );

        $result = app( SearchProviderRegistry::class )->active()->search( new SearchQuery( $this->q, [], 'relevance', 1, self::LIMIT, $currency, ProductCard::relations() ) );

        return $result->items
            ->filter( static fn ( mixed $product ): bool => $product instanceof Product )
            ->take( self::LIMIT )
            ->map( static fn ( Product $product ): array => [
                'id'    => (int) $product->id,
                'name'  => (string) $product->name,
                'url'   => $hasRoute ? route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] ) : null,
                'image' => ProductImages::card( $product ),
                'price' => $prices->for( $product, $currency ),
            ] )
            ->values()
            ->all();
    }

    /**
     * Up to {@see self::LIMIT} categories whose name holds the term.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: int, name: string, url: string|null}>
     */
    protected function categories(): array
    {
        $paths      = app( CategoryPaths::class );
        $categories = [];

        foreach ( $paths->nodes() as $node ) {
            if ( false !== mb_stripos( $node['name'], $this->q ) ) {
                $categories[] = [ 'id' => $node['id'], 'name' => $node['name'], 'url' => $paths->url( $node['id'] ) ];
            }

            if ( count( $categories ) >= self::LIMIT ) {
                break;
            }
        }

        return $categories;
    }
}
