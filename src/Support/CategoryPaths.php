<?php

/**
 * Category path helpers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Catalog\CategoryTree;
use Illuminate\Support\Facades\Route;

/**
 * Reads the engine's cached category tree for the storefront (spec §7.1):
 * canonical slug chains (`clothing/shirts`), breadcrumbs, sub-categories,
 * and a scope's sub-tree for the category filter.
 *
 * Bound per request (`scoped`), so the tree is flattened once. The engine
 * caches the tree itself (and drops it whenever a category changes, a
 * reorder included), so this class keeps no cache of its own.
 *
 * ```php
 * $paths = app( CategoryPaths::class );
 * $paths->path( $shirts->id );   // 'clothing/shirts'
 * $paths->url( $shirts->id );    // https://shop.test/shop/category/clothing/shirts
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class CategoryPaths
{
    /**
     * Every category, keyed by id, in display order, with its `depth`.
     *
     * @since 1.0.0
     *
     * @var array<int, array{id: int, parent_id: int|null, name: string, slug: string, description: string|null, image_media_id: int|null, depth: int}>|null
     */
    protected ?array $nodes = null;

    /**
     * Child ids per parent id (0 for the roots), in display order.
     *
     * @since 1.0.0
     *
     * @var array<int, array<int, int>>|null
     */
    protected ?array $childIds = null;

    /**
     * @since 1.0.0
     *
     * @param  CategoryTree  $tree  The engine's category tree.
     */
    public function __construct( protected CategoryTree $tree )
    {
    }

    /**
     * Every category as a flat node, keyed by id, in tree order (each
     * parent before its children).
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: int, parent_id: int|null, name: string, slug: string, description: string|null, image_media_id: int|null, depth: int}>
     */
    public function nodes(): array
    {
        if ( null !== $this->nodes ) {
            return $this->nodes;
        }

        $nodes = [];
        $walk  = static function ( array $level, int $depth ) use ( &$walk, &$nodes ): void {
            foreach ( $level as $node ) {
                $id = (int) ( $node['id'] ?? 0 );

                // A cycle in bad data can't loop forever.
                if ( $id <= 0 || isset( $nodes[ $id ] ) ) {
                    continue;
                }

                $nodes[ $id ] = [
                    'id'             => $id,
                    'parent_id'      => null === ( $node['parent_id'] ?? null ) ? null : (int) $node['parent_id'],
                    'name'           => (string) ( $node['name'] ?? '' ),
                    'slug'           => (string) ( $node['slug'] ?? '' ),
                    'description'    => isset( $node['description'] ) ? (string) $node['description'] : null,
                    'image_media_id' => null === ( $node['image_media_id'] ?? null ) ? null : (int) $node['image_media_id'],
                    'depth'          => $depth,
                ];

                $walk( (array) ( $node['children'] ?? [] ), $depth + 1 );
            }
        };

        $walk( $this->tree->tree(), 0 );

        return $this->nodes = $nodes;
    }

    /**
     * One category's node.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Category id.
     *
     * @return array{id: int, parent_id: int|null, name: string, slug: string, description: string|null, image_media_id: int|null, depth: int}|null
     */
    public function node( int $id ): ?array
    {
        return $this->nodes()[ $id ] ?? null;
    }

    /**
     * The category with this slug.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Slug.
     *
     * @return array{id: int, parent_id: int|null, name: string, slug: string, description: string|null, image_media_id: int|null, depth: int}|null
     */
    public function bySlug( string $slug ): ?array
    {
        foreach ( $this->nodes() as $node ) {
            if ( $slug === $node['slug'] ) {
                return $node;
            }
        }

        return null;
    }

    /**
     * The category and its ancestors, root first.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Category id.
     *
     * @return array<int, array{id: int, parent_id: int|null, name: string, slug: string, description: string|null, image_media_id: int|null, depth: int}>
     */
    public function ancestry( int $id ): array
    {
        $chain = [];
        $node  = $this->node( $id );

        while ( null !== $node && ! isset( $chain[ $node['id'] ] ) ) {
            $chain[ $node['id'] ] = $node;
            $node                 = null === $node['parent_id'] ? null : $this->node( $node['parent_id'] );
        }

        return array_reverse( array_values( $chain ) );
    }

    /**
     * The canonical slug chain of a category (`clothing/shirts`).
     *
     * @since 1.0.0
     *
     * @param  int  $id  Category id.
     *
     * @return string|null
     */
    public function path( int $id ): ?string
    {
        $chain = $this->ancestry( $id );

        return [] === $chain ? null : implode( '/', array_column( $chain, 'slug' ) );
    }

    /**
     * The category page URL, when the storefront routes are on.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Category id.
     *
     * @return string|null
     */
    public function url( int $id ): ?string
    {
        $path = $this->path( $id );

        return null === $path || ! Route::has( 'artisanpack.ecommerce.storefront.category' )
            ? null
            : route( 'artisanpack.ecommerce.storefront.category', [ 'path' => $path ] );
    }

    /**
     * The direct sub-categories of a category (or the roots for null).
     *
     * @since 1.0.0
     *
     * @param  int|null  $id  Category id.
     *
     * @return array<int, array{id: int, parent_id: int|null, name: string, slug: string, description: string|null, image_media_id: int|null, depth: int}>
     */
    public function children( ?int $id ): array
    {
        $nodes = $this->nodes();

        return array_map( static fn ( int $child ): array => $nodes[ $child ], $this->childIds()[ $id ?? 0 ] ?? [] );
    }

    /**
     * Every category under a category (or every category for null), in
     * tree order, with `depth` relative to it (its children are 0).
     *
     * @since 1.0.0
     *
     * @param  int|null  $id  Category id.
     *
     * @return array<int, array{id: int, parent_id: int|null, name: string, slug: string, description: string|null, image_media_id: int|null, depth: int}>
     */
    public function descendants( ?int $id ): array
    {
        if ( null === $id ) {
            return array_values( $this->nodes() );
        }

        $root = $this->node( $id );

        if ( null === $root ) {
            return [];
        }

        $all      = $this->nodes();
        $children = $this->childIds();
        $nodes    = [];
        $seen     = [ $id => true ];

        // Depth-first, children in display order: the same tree order as
        // nodes(). The seen list stops a cycle in bad data.
        $walk = static function ( int $parent ) use ( &$walk, &$nodes, &$seen, $all, $children, $root ): void {
            foreach ( $children[ $parent ] ?? [] as $child ) {
                if ( isset( $seen[ $child ] ) ) {
                    continue;
                }

                $seen[ $child ] = true;
                $nodes[]        = [ 'depth' => $all[ $child ]['depth'] - $root['depth'] - 1 ] + $all[ $child ];

                $walk( $child );
            }
        };

        $walk( $id );

        return $nodes;
    }

    /**
     * Each category's count plus the counts of every category under it,
     * in one pass over the tree, from per-category counts (as in
     * `CatalogQuery::facets()['categories']`). A product filed under a
     * category and its child is counted under the parent twice.
     *
     * @since 1.0.0
     *
     * @param  array<int, int>  $counts  Count per category id.
     *
     * @return array<int, int> Subtree count per category id.
     */
    public function subtreeTotals( array $counts ): array
    {
        $nodes  = $this->nodes();
        $totals = [];

        foreach ( $nodes as $id => $node ) {
            $totals[ $id ] = (int) ( $counts[ $id ] ?? 0 );
        }

        // Children come after their parent in tree order, so walking it
        // backwards adds each finished subtree to its parent.
        foreach ( array_reverse( $nodes, true ) as $id => $node ) {
            if ( null !== $node['parent_id'] && isset( $totals[ $node['parent_id'] ] ) ) {
                $totals[ $node['parent_id'] ] += $totals[ $id ];
            }
        }

        return $totals;
    }

    /**
     * Child ids per parent id (0 for the roots), in display order.
     *
     * @since 1.0.0
     *
     * @return array<int, array<int, int>>
     */
    protected function childIds(): array
    {
        if ( null !== $this->childIds ) {
            return $this->childIds;
        }

        $children = [];

        foreach ( $this->nodes() as $id => $node ) {
            $children[ $node['parent_id'] ?? 0 ][] = $id;
        }

        return $this->childIds = $children;
    }
}
