<?php

/**
 * Product grid component.
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
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\AddsToCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\InteractsWithStorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CategoryPaths;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\GridColumns;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\ProductCard;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-grid source="featured" :limit="8" />`
 *
 * A fixed set of products in a grid, without filters or paging: the
 * newest, featured, or on-sale products, a category's or tag's products, or
 * hand-picked ones (in the order picked). The Product Grid block renders
 * it (spec §11.3); pages can embed it too.
 *
 * Cards can hide the price and rating, and offer quick "Add to cart" for
 * simple products. When the engine can't list products the grid is empty
 * (the error is reported) rather than breaking the page.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 * @since      2.0.0 The grid is laid out by `data-columns`; the view reads `columns`, and `gridClass` is gone.
 */
class ProductGrid extends Component
{
    use AddsToCart;
    use InteractsWithStorefrontCart;
    use RateLimitsStorefront;
    use SendsToasts;

    /**
     * Where the products come from.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const SOURCES = [ 'newest', 'featured', 'on_sale', 'category', 'tag', 'hand_picked' ];

    /**
     * The offered sorts (the engine's, without relevance and position).
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const SORTS = [ 'newest', 'price', '-price', 'popularity', 'rating', 'name' ];

    /**
     * The most products a grid shows.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_LIMIT = 24;

    /**
     * Where the products come from ({@see self::SOURCES}).
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $source = 'newest';

    /**
     * The category (slug or id) for the `category` source; its
     * sub-categories are included.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $category = '';

    /**
     * The tag (slug or id) for the `tag` source.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $tag = '';

    /**
     * The products for the `hand_picked` source, in order.
     *
     * @since 1.0.0
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $ids = [];

    /**
     * The sort ({@see self::SORTS}). The `newest` source always lists
     * newest first, and hand-picked products keep their order.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $sort = 'newest';

    /**
     * How many products (1–24).
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $limit = 8;

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
     * Show prices on the cards.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $showPrice = true;

    /**
     * Show ratings on the cards.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $showRating = true;

    /**
     * Offer "Add to cart" on cards for simple products.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $showAddToCart = true;

    /**
     * A heading above the grid; null shows none.
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
        $this->source   = in_array( $this->source, self::SOURCES, true ) ? $this->source : 'newest';
        $this->sort     = in_array( $this->sort, self::SORTS, true ) ? $this->sort : 'newest';
        $this->limit    = max( 1, min( self::MAX_LIMIT, $this->limit ) );
        $this->columns  = GridColumns::clamp( $this->columns );
        $this->category = trim( $this->category );
        $this->tag      = trim( $this->tag );
        $this->ids      = array_slice( array_values( array_unique( array_filter( array_map( 'intval', $this->ids ), static fn ( int $id ): bool => $id > 0 ) ) ), 0, self::MAX_LIMIT );
        $this->heading  = null === $this->heading || '' === trim( $this->heading ) ? null : trim( $this->heading );
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
        return view( 'ecommerce-storefront::livewire.catalog.product-grid', [
            'products'  => $this->products(),
            'currency'  => app( StorefrontCart::class )->currency(),
            'headingId' => 'ec-product-grid-' . $this->getId(),
        ] );
    }

    /**
     * The products to show.
     *
     * @since 1.0.0
     *
     * @return Collection<int, Product>
     */
    protected function products(): Collection
    {
        try {
            $query = $this->catalogQuery();

            if ( null === $query ) {
                return new Collection();
            }

            $products = $query->builder()->with( ProductCard::relations() )->limit( $this->limit )->get();
        } catch ( Throwable $exception ) {
            report( $exception );

            return new Collection();
        }

        ProductImages::preloadCards( $products );

        if ( 'hand_picked' !== $this->source ) {
            return $products;
        }

        $order = array_flip( $this->ids );

        return $products->sortBy( static fn ( Product $product ): int => $order[ (int) $product->id ] ?? PHP_INT_MAX )->values();
    }

    /**
     * The category's id: by slug first (an all-digit slug such as `2024`
     * is a slug), then by id; 0 (matches nothing) when neither exists.
     *
     * @since 2.0.0
     *
     * @return int
     */
    protected function categoryId(): int
    {
        $paths = app( CategoryPaths::class );
        $node  = $paths->bySlug( $this->category ) ?? ( ctype_digit( $this->category ) ? $paths->node( (int) $this->category ) : null );

        return (int) ( $node['id'] ?? 0 );
    }

    /**
     * The tag's id: by slug first, then by id; 0 (matches nothing) when
     * neither exists.
     *
     * @since 2.0.0
     *
     * @return int
     */
    protected function tagId(): int
    {
        $id = ProductTag::query()->where( 'slug', $this->tag )->value( 'id' );

        if ( null === $id && ctype_digit( $this->tag ) ) {
            $id = ProductTag::query()->whereKey( (int) $this->tag )->value( 'id' );
        }

        return (int) ( $id ?? 0 );
    }

    /**
     * The catalog query for the source, or null when the source has
     * nothing to list (no category, tag, or picks).
     *
     * @since 1.0.0
     *
     * @return CatalogQuery|null
     */
    protected function catalogQuery(): ?CatalogQuery
    {
        $query = app( CatalogQuery::class )->currency( app( StorefrontCart::class )->currency() )->sort( $this->sort );

        return match ( $this->source ) {
            'featured'    => $query->featured(),
            'on_sale'     => $query->onSale(),
            'category'    => '' === $this->category ? null : $query->inCategory( $this->categoryId() ),
            'tag'         => '' === $this->tag ? null : $query->withTag( $this->tagId() ),
            // The first picks in the editor's order, not the first by the sort.
            'hand_picked' => [] === $this->ids ? null : $query->ids( array_slice( $this->ids, 0, $this->limit ) ),
            // "Newest" is a sort of its own; the block's "Sort by" says so.
            default       => $query->sort( 'newest' ),
        };
    }
}
