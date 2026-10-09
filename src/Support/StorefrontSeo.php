<?php

/**
 * Storefront SEO.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Inventory\StockStatus;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Pricing\DisplayPrice;
use ArtisanPackUI\Ecommerce\Pricing\PriceDisplayResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Throwable;

/**
 * The current storefront page's search-engine metadata (spec §12, S39):
 * title, description, canonical URL, robots directive, social image, and
 * JSON-LD.
 *
 * Bound per request (scoped). The page controller describes the page
 * ({@see self::product()}, {@see self::category()}, {@see self::tag()},
 * {@see self::catalog()}, {@see self::search()}, {@see self::noindex()})
 * and every page view pushes `ecommerce-storefront::partials.seo` onto the
 * layout's `head` stack, which renders the tags.
 *
 * - **Title and description** per page, through
 *   `ap.ecommerceStorefrontLivewire.seo.meta` (`title`, `description`,
 *   `canonical`, `robots`, `image`, `type`; plus the page and its product,
 *   category, or tag).
 * - **Canonical URLs** leave out filters and sort; a category filter and a
 *   page after the first stay, so each listing page is indexed once.
 * - **`noindex`** on the cart, checkout, confirmation, account, order
 *   lookup, and search pages.
 * - **JSON-LD**: `Product` with an `Offer` (or an `AggregateOffer` for a
 *   price range) carrying price, currency, and availability, plus
 *   `AggregateRating` once the product has reviews; `BreadcrumbList` on
 *   product and category pages. The list runs through
 *   `ap.ecommerceStorefrontLivewire.seo.schema`.
 *
 * When `artisanpack-ui/seo` is installed the JSON-LD is handed to its
 * schema collector, so the layout's `<x-seo:schema />` prints it in the
 * site's schema graph; the meta tags are still rendered here, because the
 * SEO package builds its tags from a model with SEO meta and listing pages
 * have none.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class StorefrontSeo
{
    /**
     * The SEO package's request-scoped schema collector.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SCHEMA_COLLECTOR = 'ArtisanPackUI\\SEO\\Support\\SchemaCollector';

    /**
     * The longest description, in characters.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const DESCRIPTION_LENGTH = 160;

    /**
     * The robots directive for pages that are never indexed.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const NOINDEX = 'noindex, nofollow';

    /**
     * The robots directive for pages that aren't indexed but whose links
     * are followed (search results).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const NOINDEX_FOLLOW = 'noindex, follow';

    /**
     * Query parameters a canonical listing URL keeps.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const CANONICAL_PARAMETERS = [ 'category', 'page' ];

    /**
     * The page (`product`, `category`, `catalog`, `cart`, ...).
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    protected ?string $page = null;

    /**
     * The product, category, or tag the page is about.
     *
     * @since 1.0.0
     *
     * @var Model|null
     */
    protected ?Model $subject = null;

    /**
     * The tags before the filter.
     *
     * @since 1.0.0
     *
     * @var array{title: string|null, description: string|null, canonical: string|null, robots: string|null, image: string|null, type: string}
     */
    protected array $meta = [
        'title'       => null,
        'description' => null,
        'canonical'   => null,
        'robots'      => null,
        'image'       => null,
        'type'        => 'website',
    ];

    /**
     * The JSON-LD entries before the filter.
     *
     * @since 1.0.0
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $schemas = [];

    /**
     * The filtered tags, once worked out.
     *
     * @since 1.0.0
     *
     * @var array{title: string|null, description: string|null, canonical: string|null, robots: string|null, image: string|null, type: string}|null
     */
    protected ?array $resolved = null;

    /**
     * Whether the JSON-LD went to the SEO package already.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    protected bool $collected = false;

    /**
     * Describes a product page: its name and short description, the
     * product URL as canonical, its image, and `Product` + `BreadcrumbList`
     * JSON-LD.
     *
     * @since 1.0.0
     *
     * @param  Product  $product   The product.
     * @param  string   $currency  The shopper's currency.
     *
     * @return static
     */
    public function product( Product $product, string $currency ): static
    {
        $url   = self::route( 'artisanpack.ecommerce.storefront.product', [ 'product' => $product->slug ] );
        $image = ProductImages::card( $product );

        $this->describe( 'product', $product, [
            'title'       => (string) $product->name,
            'description' => self::summary( (string) ( $product->short_description ?: $product->description ) ),
            'canonical'   => $url,
            'image'       => $image['url'] ?? null,
            'type'        => 'product',
        ] );

        $this->schemas[] = $this->productSchema( $product, $currency, $url, $image['url'] ?? null );

        $table    = ( new ProductCategory() )->getTable();
        $category = $product->categories()->orderBy( $table . '.position' )->orderBy( $table . '.id' )->first();
        $trail    = null === $category ? [] : $this->categoryTrail( (int) $category->id );

        $this->schemas[] = self::breadcrumbs( [ ...$trail, [ 'name' => (string) $product->name, 'url' => $url ] ] );

        return $this;
    }

    /**
     * Describes a category page: its name and description, the category
     * URL (plus the page number) as canonical, and `BreadcrumbList` JSON-LD.
     *
     * @since 1.0.0
     *
     * @param  ProductCategory  $category  The category.
     * @param  Request          $request   The request.
     *
     * @return static
     */
    public function category( ProductCategory $category, Request $request ): static
    {
        $path = app( CategoryPaths::class )->path( (int) $category->id );
        $url  = null === $path ? null : self::route( 'artisanpack.ecommerce.storefront.category', [ 'path' => $path ] );

        $this->describe( 'category', $category, [
            'title'       => self::paged( (string) $category->name, $request ),
            'description' => self::summary( (string) $category->description ) ?? __( 'Shop :name.', [ 'name' => $category->name ] ),
            'canonical'   => self::canonical( $url, $request, [ 'page' ] ),
        ] );

        $this->schemas[] = self::breadcrumbs( $this->categoryTrail( (int) $category->id ) );

        return $this;
    }

    /**
     * Describes a tag page.
     *
     * @since 1.0.0
     *
     * @param  ProductTag  $tag      The tag.
     * @param  Request     $request  The request.
     *
     * @return static
     */
    public function tag( ProductTag $tag, Request $request ): static
    {
        return $this->describe( 'tag', $tag, [
            'title'       => self::paged( (string) $tag->name, $request ),
            'description' => __( 'Shop products tagged :name.', [ 'name' => $tag->name ] ),
            'canonical'   => self::canonical( self::route( 'artisanpack.ecommerce.storefront.tag', [ 'tag' => $tag->slug ] ), $request, [ 'page' ] ),
        ] );
    }

    /**
     * Describes the catalog. Its canonical URL keeps a category filter and
     * the page number and drops every other filter and the sort.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The request.
     *
     * @return static
     */
    public function catalog( Request $request ): static
    {
        return $this->describe( 'catalog', null, [
            'title'       => self::paged( __( 'Shop' ), $request ),
            'description' => __( 'Browse every product at :store.', [ 'store' => (string) config( 'app.name' ) ] ),
            'canonical'   => self::canonical( self::route( 'artisanpack.ecommerce.storefront.catalog' ), $request, self::CANONICAL_PARAMETERS ),
        ] );
    }

    /**
     * Describes the search page: not indexed, links followed.
     *
     * @since 1.0.0
     *
     * @param  string  $term  The search term.
     *
     * @return static
     */
    public function search( string $term ): static
    {
        return $this->describe( 'search', null, [
            'title'  => '' === $term ? __( 'Search' ) : __( 'Search results for ":term"', [ 'term' => $term ] ),
            'robots' => self::NOINDEX_FOLLOW,
        ] );
    }

    /**
     * Describes a page that is never indexed (cart, checkout, confirmation,
     * account, order lookup).
     *
     * @since 1.0.0
     *
     * @param  string       $page   The page.
     * @param  string|null  $title  Its title.
     *
     * @return static
     */
    public function noindex( string $page, ?string $title = null ): static
    {
        return $this->describe( $page, null, [ 'title' => $title, 'robots' => self::NOINDEX ] );
    }

    /**
     * The page being described.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function page(): ?string
    {
        return $this->page;
    }

    /**
     * The filtered tags: `title`, `description`, `canonical`, `robots`,
     * `image`, and `type`.
     *
     * @since 1.0.0
     *
     * @return array{title: string|null, description: string|null, canonical: string|null, robots: string|null, image: string|null, type: string}
     */
    public function meta(): array
    {
        if ( null !== $this->resolved ) {
            return $this->resolved;
        }

        $filtered = applyFilters( 'ap.ecommerceStorefrontLivewire.seo.meta', $this->meta, $this->page, $this->subject );
        $filtered = is_array( $filtered ) ? $filtered : $this->meta;
        $string   = static fn ( mixed $value ): ?string => is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : null;

        $canonical = $string( $filtered['canonical'] ?? null );
        $image     = $string( $filtered['image'] ?? null );

        return $this->resolved = [
            'title'       => $string( $filtered['title'] ?? null ),
            'description' => null === ( $description = $string( $filtered['description'] ?? null ) ) ? null : Str::limit( $description, self::DESCRIPTION_LENGTH ),
            'canonical'   => null === $canonical ? null : ProductImages::safeUrl( $canonical ),
            'robots'      => $string( $filtered['robots'] ?? null ),
            'image'       => null === $image ? null : ProductImages::safeUrl( $image ),
            'type'        => $string( $filtered['type'] ?? null ) ?? 'website',
        ];
    }

    /**
     * The page title, or `$fallback`.
     *
     * @since 1.0.0
     *
     * @param  string  $fallback  The page view's own title.
     *
     * @return string
     */
    public function title( string $fallback ): string
    {
        return $this->meta()['title'] ?? $fallback;
    }

    /**
     * The filtered JSON-LD entries.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    public function schemas(): array
    {
        $filtered = applyFilters( 'ap.ecommerceStorefrontLivewire.seo.schema', $this->schemas, $this->page, $this->subject );

        return array_values( array_filter( is_array( $filtered ) ? $filtered : [], static fn ( mixed $schema ): bool => is_array( $schema ) && [] !== $schema ) );
    }

    /**
     * The JSON-LD the page renders itself: none when the SEO package is
     * installed (each entry goes to its schema collector, once), else
     * {@see self::schemas()}.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    public function inlineSchemas(): array
    {
        if ( ! self::seoPackageInstalled() ) {
            return $this->schemas();
        }

        if ( ! $this->collected ) {
            $this->collected = true;

            foreach ( $this->schemas() as $schema ) {
                app( self::SCHEMA_COLLECTOR )->add( $schema );
            }
        }

        return [];
    }

    /**
     * Whether `artisanpack-ui/seo` is installed (its schema collector is
     * bound).
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function seoPackageInstalled(): bool
    {
        return class_exists( self::SCHEMA_COLLECTOR ) && app()->bound( self::SCHEMA_COLLECTOR );
    }

    /**
     * JSON for a `<script type="application/ld+json">` block: tags and
     * ampersands escaped so it can't close the script.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $schema  A JSON-LD entry.
     *
     * @return string
     */
    public static function json( array $schema ): string
    {
        return (string) json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR );
    }

    /**
     * Records the page and its tags, dropping anything filtered earlier.
     *
     * @since 1.0.0
     *
     * @param  string                $page     The page.
     * @param  Model|null            $subject  The product, category, or tag.
     * @param  array<string, mixed>  $meta     Tags.
     *
     * @return static
     */
    protected function describe( string $page, ?Model $subject, array $meta ): static
    {
        $this->page     = $page;
        $this->subject  = $subject;
        $this->meta     = [ ...$this->meta, ...$meta ];
        $this->resolved = null;

        return $this;
    }

    /**
     * The `Product` entry: name, description, SKU, image, URL, an `Offer`
     * (or `AggregateOffer` for a price range), and `AggregateRating` once
     * there are reviews.
     *
     * @since 1.0.0
     *
     * @param  Product      $product   The product.
     * @param  string       $currency  The shopper's currency.
     * @param  string|null  $url       The product URL.
     * @param  string|null  $image     The image URL.
     *
     * @return array<string, mixed>
     */
    protected function productSchema( Product $product, string $currency, ?string $url, ?string $image ): array
    {
        $schema = array_filter( [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string) $product->name,
            'description' => self::summary( (string) ( $product->description ?: $product->short_description ), 5000 ),
            'sku'         => '' === (string) $product->sku ? null : (string) $product->sku,
            'image'       => $image,
            'url'         => $url,
        ], static fn ( mixed $value ): bool => null !== $value );

        try {
            $price = app( PriceDisplayResolver::class )->for( $product, $currency );
            $stock = StockStatus::for( $product );
        } catch ( Throwable $exception ) {
            report( $exception );

            $price = null;
            $stock = null;
        }

        if ( null !== $price ) {
            $schema['offers'] = self::offer( $price, $stock, $url );
        }

        if ( (int) $product->reviews_count > 0 && (float) $product->avg_rating > 0 ) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => round( (float) $product->avg_rating, 2 ),
                'reviewCount' => (int) $product->reviews_count,
                'bestRating'  => 5,
                'worstRating' => 1,
            ];
        }

        return $schema;
    }

    /**
     * An `Offer` (or `AggregateOffer` for a price range).
     *
     * @since 1.0.0
     *
     * @param  DisplayPrice      $price  The display price.
     * @param  StockStatus|null  $stock  The availability.
     * @param  string|null       $url    The product URL.
     *
     * @return array<string, mixed>
     */
    protected static function offer( DisplayPrice $price, ?StockStatus $stock, ?string $url ): array
    {
        $availability = match ( $stock?->status ) {
            StockStatus::IN_STOCK, StockStatus::LOW_STOCK => 'https://schema.org/InStock',
            StockStatus::BACKORDER                        => 'https://schema.org/BackOrder',
            StockStatus::OUT_OF_STOCK                     => 'https://schema.org/OutOfStock',
            default                                       => null,
        };

        $offer = $price->isRange() && null !== $price->minPrice && null !== $price->maxPrice
            ? [ '@type' => 'AggregateOffer', 'lowPrice' => self::decimal( $price->minPrice ), 'highPrice' => self::decimal( $price->maxPrice ) ]
            : [ '@type' => 'Offer', 'price' => self::decimal( $price->price ) ];

        return array_filter( [
            ...$offer,
            'priceCurrency' => $price->price->getCurrency()->getCode(),
            'availability'  => $availability,
            'url'           => $url,
        ], static fn ( mixed $value ): bool => null !== $value );
    }

    /**
     * Shop → the category's ancestors → the category, as breadcrumb items.
     *
     * @since 1.0.0
     *
     * @param  int  $categoryId  The category.
     *
     * @return array<int, array{name: string, url: string|null}>
     */
    protected function categoryTrail( int $categoryId ): array
    {
        $paths = app( CategoryPaths::class );
        $trail = [ [ 'name' => __( 'Shop' ), 'url' => self::route( 'artisanpack.ecommerce.storefront.catalog' ) ] ];

        foreach ( $paths->ancestry( $categoryId ) as $node ) {
            $trail[] = [ 'name' => $node['name'], 'url' => $paths->url( $node['id'] ) ];
        }

        return $trail;
    }

    /**
     * A `BreadcrumbList` entry.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{name: string, url: string|null}>  $items  Crumbs, outermost first.
     *
     * @return array<string, mixed>
     */
    protected static function breadcrumbs( array $items ): array
    {
        $elements = [];

        foreach ( array_values( $items ) as $index => $item ) {
            $elements[] = array_filter( [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ], static fn ( mixed $value ): bool => null !== $value );
        }

        return [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $elements ];
    }

    /**
     * `$url` with only `$keep` of the request's query parameters (a page
     * number only after the first page).
     *
     * @since 1.0.0
     *
     * @param  string|null         $url      The page URL.
     * @param  Request             $request  The request.
     * @param  array<int, string>  $keep     Parameters to keep.
     *
     * @return string|null
     */
    protected static function canonical( ?string $url, Request $request, array $keep ): ?string
    {
        if ( null === $url ) {
            return null;
        }

        $query = [];

        foreach ( $keep as $key ) {
            $value = $request->query( $key );

            if ( ! is_string( $value ) || '' === $value || ( 'page' === $key && ( ! ctype_digit( $value ) || (int) $value <= 1 ) ) ) {
                continue;
            }

            $query[ $key ] = 'page' === $key ? (int) $value : $value;
        }

        return [] === $query ? $url : $url . '?' . http_build_query( $query );
    }

    /**
     * `$title`, with the page number after the first page.
     *
     * @since 1.0.0
     *
     * @param  string   $title    The title.
     * @param  Request  $request  The request.
     *
     * @return string
     */
    protected static function paged( string $title, Request $request ): string
    {
        $page = $request->query( 'page' );

        return is_string( $page ) && ctype_digit( $page ) && (int) $page > 1
            ? __( ':title – Page :page', [ 'title' => $title, 'page' => (int) $page ] )
            : $title;
    }

    /**
     * Plain text from HTML, on one line, at most `$limit` characters, or
     * null when empty.
     *
     * @since 1.0.0
     *
     * @param  string  $html   The text or HTML.
     * @param  int     $limit  The longest result.
     *
     * @return string|null
     */
    protected static function summary( string $html, int $limit = self::DESCRIPTION_LENGTH ): ?string
    {
        $text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5 ) ) );

        return '' === $text ? null : Str::limit( $text, $limit );
    }

    /**
     * A money amount as a decimal string (`19.00`).
     *
     * @since 1.0.0
     *
     * @param  Money  $money  The amount.
     *
     * @return string
     */
    protected static function decimal( Money $money ): string
    {
        return ( new DecimalMoneyFormatter( new ISOCurrencies() ) )->format( $money );
    }

    /**
     * A named route's URL, or null when it isn't registered.
     *
     * @since 1.0.0
     *
     * @param  string                $name        Route name.
     * @param  array<string, mixed>  $parameters  Parameters.
     *
     * @return string|null
     */
    protected static function route( string $name, array $parameters = [] ): ?string
    {
        return Route::has( $name ) ? route( $name, $parameters ) : null;
    }
}
