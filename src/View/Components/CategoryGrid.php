<?php

/**
 * Category grid component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\Ecommerce\Catalog\CatalogQuery;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\GridColumns;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Throwable;

/**
 * `<x-artisanpack-ec-category-grid parent="clothing" :limit="6" :columns="3" />`
 *
 * Categories as linked tiles (spec §11.3, the Category Grid block): the
 * top-level categories, or a parent's sub-categories, in tree order, each
 * with its image and how many storefront products it holds (sub-categories
 * included, each product once). Counts and images can be turned off.
 *
 * The counts come from one grouped query, cached ({@see StorefrontCache})
 * and dropped whenever the catalog changes.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class CategoryGrid extends Component
{
    /**
     * The most categories a grid shows.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_LIMIT = 24;

    /**
     * The tiles.
     *
     * @since 1.0.0
     *
     * @var array<int, array{id: int, name: string, url: string|null, image: array{url: string, srcset: string|null, alt: string}|null, count: int}>
     */
    public array $tiles;

    /**
     * The heading's id, unique per grid.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $headingId;

    /**
     * The grid classes.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $gridClass;

    /**
     * @since 1.0.0
     *
     * @param  int|string|null  $parent      The parent category (id or slug); empty for top-level categories.
     * @param  int              $limit       How many categories (1–24).
     * @param  int              $columns     Columns from `lg` up (1–6).
     * @param  bool             $showCounts  Show product counts.
     * @param  bool             $showImages  Show category images.
     * @param  string|null      $heading     A heading above the grid.
     */
    public function __construct(
        public int|string|null $parent = null,
        public int $limit = 6,
        public int $columns = 3,
        public bool $showCounts = true,
        public bool $showImages = true,
        public ?string $heading = null,
    ) {
        $this->limit     = max( 1, min( self::MAX_LIMIT, $limit ) );
        $this->gridClass = GridColumns::classes( $columns );
        $this->heading   = null === $heading || '' === trim( $heading ) ? null : trim( $heading );
        $this->headingId = 'ec-category-grid-' . Str::lower( Str::random( 8 ) );
        $this->tiles     = $this->tiles();
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
        return view( 'ecommerce-storefront::components.category-grid' );
    }

    /**
     * The categories to show; none for an unknown parent.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: int, name: string, url: string|null, image: array{url: string, srcset: string|null, alt: string}|null, count: int}>
     */
    protected function tiles(): array
    {
        $paths  = app( CategoryPaths::class );
        $parent = null;

        if ( null !== $this->parent && '' !== trim( (string) $this->parent ) ) {
            $key    = trim( (string) $this->parent );
            $parent = ( ctype_digit( $key ) ? $paths->node( (int) $key ) : $paths->bySlug( $key ) )['id'] ?? null;

            if ( null === $parent ) {
                return [];
            }
        }

        $nodes  = array_slice( $paths->children( $parent ), 0, $this->limit );
        $counts = $this->showCounts ? $this->counts( $nodes ) : [];
        $tiles  = [];

        if ( $this->showImages ) {
            ProductImages::preload( array_column( $nodes, 'image_media_id' ) );
        }

        foreach ( $nodes as $node ) {
            $tiles[] = [
                'id'    => $node['id'],
                'name'  => $node['name'],
                'url'   => $paths->url( $node['id'] ),
                'image' => $this->showImages ? ProductImages::media( $node['image_media_id'], $node['name'] ) : null,
                'count' => $counts[ $node['id'] ] ?? 0,
            ];
        }

        return $tiles;
    }

    /**
     * How many storefront products each tile's category and its
     * sub-categories hold, each product once, keyed by tile category id;
     * none when the count fails.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{id: int}>  $nodes  The tiles' categories.
     *
     * @return array<int, int>
     */
    protected function counts( array $nodes ): array
    {
        if ( [] === $nodes ) {
            return [];
        }

        $paths   = app( CategoryPaths::class );
        $subtree = [];

        foreach ( $nodes as $node ) {
            $subtree[ $node['id'] ] = [ $node['id'], ...array_column( $paths->descendants( $node['id'] ), 'id' ) ];
        }

        try {
            return StorefrontCache::remember(
                StorefrontCache::key( 'category-counts', $subtree ),
                static fn (): array => self::countSubtrees( $subtree ),
            );
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->showCounts = false;

            return [];
        }
    }

    /**
     * One grouped query: distinct storefront products per subtree. The
     * tiles are siblings, so each category belongs to one subtree at most.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<int, int>>  $subtree  Category ids per tile category id.
     *
     * @return array<int, int>
     */
    private static function countSubtrees( array $subtree ): array
    {
        $category = new ProductCategory();
        $pivot    = $category->products()->getTable();
        $cases    = [];
        $bindings = [];

        foreach ( $subtree as $tile => $ids ) {
            $cases[]  = 'WHEN product_category_id IN (' . implode( ', ', array_fill( 0, count( $ids ), '?' ) ) . ') THEN ?';
            $bindings = [ ...$bindings, ...$ids, $tile ];
        }

        $visible = app( CatalogQuery::class )->builder( false )->select( ( new Product() )->qualifyColumn( 'id' ) );

        return $category->getConnection()->table( $pivot )
            ->whereIn( 'product_category_id', array_merge( ...array_values( $subtree ) ) )
            ->whereIn( 'product_id', $visible )
            ->selectRaw( 'CASE ' . implode( ' ', $cases ) . ' END as tile_id, COUNT(DISTINCT product_id) as products', $bindings )
            ->groupBy( 'tile_id' )
            ->pluck( 'products', 'tile_id' )
            ->mapWithKeys( static fn ( mixed $count, mixed $tile ): array => [ (int) $tile => (int) $count ] )
            ->all();
    }
}
