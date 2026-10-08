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
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\GridColumns;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
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
 * included). Counts and images can be turned off.
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

        $counts = $this->showCounts ? $this->counts() : [];
        $tiles  = [];

        foreach ( array_slice( $paths->children( $parent ), 0, $this->limit ) as $node ) {
            $count = 0;

            foreach ( [ $node, ...$paths->descendants( $node['id'] ) ] as $member ) {
                $count += (int) ( $counts[ $member['id'] ] ?? 0 );
            }

            $tiles[] = [
                'id'    => $node['id'],
                'name'  => $node['name'],
                'url'   => $paths->url( $node['id'] ),
                'image' => $this->showImages ? ProductImages::media( $node['image_media_id'], $node['name'] ) : null,
                'count' => $count,
            ];
        }

        return $tiles;
    }

    /**
     * Storefront products per category id, or none when the engine can't
     * count (a product filed under a category and its child counts twice).
     *
     * @since 1.0.0
     *
     * @return array<int, int>
     */
    protected function counts(): array
    {
        try {
            return (array) ( app( CatalogQuery::class )->facets()['categories'] ?? [] );
        } catch ( Throwable $exception ) {
            report( $exception );

            $this->showCounts = false;

            return [];
        }
    }
}
