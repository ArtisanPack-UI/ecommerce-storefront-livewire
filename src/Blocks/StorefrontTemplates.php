<?php

/**
 * Storefront templates and patterns.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * The storefront's default visual-editor templates and block patterns
 * (spec §11.4, S38).
 *
 * **Templates** — `single-product`, `product-archive`, `product-category`,
 * `product-tag`, `cart`, `checkout`, and `search-results`, built from the
 * commerce blocks so each renders like the page without a template. They
 * are offered to the site editor through `ap.visualEditor.templates`
 * (after `ap.ecommerceStorefrontLivewire.templates`) when
 * `visual_editor.templates` is on.
 *
 * A page renders through a template only when one along its chain has been
 * saved (customised in the site editor, or a theme file): the most specific
 * wins, so `single-product-{slug}` → `single-product-{type}` →
 * `single-product`, and `product-category-{slug}` → `product-category` →
 * `product-archive` (tags follow the category chain). With nothing saved the
 * page renders as it does without templates, which is what the default
 * templates show. Templates are rendered by visual-editor's
 * `<x-ve-template>` and looked up through cms-framework's template
 * resolver, so both must be installed. Each template is a group of its
 * blocks, spaced by the editor; the archive, cart, checkout and search
 * templates start with their page heading (`ec-template__title`, the page
 * header's heading-xl type, #128).
 *
 * **Patterns** — "Featured products", "Shop by category", and "Sale banner +
 * grid", through `ap.visualEditor.patterns` (after
 * `ap.ecommerceStorefrontLivewire.patterns`) whenever the blocks are
 * registered. "Featured products" (#125) is a heading row (the heading-xl
 * title, and a standalone "Shop all" Link at the row's end) over a
 * four-column grid of featured products; "Shop by category" (#126) is the
 * heading-xl title over three columns of top-level categories (two on small
 * screens). "Sale banner + grid" (#127) is a banner on the accent soft
 * fill (a "Sale" Badge, the heading, a line of text and a "Shop the sale"
 * Button, beside an image the editor picks) over four products on sale.
 * Their blocks are spaced by the editor, and their layout comes from block
 * attributes; their classes (`ec-pattern__*`, `ec-sale-banner*`) only
 * carry tokens.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class StorefrontTemplates
{
    /**
     * cms-framework's template resolver (DB rows and theme files).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const RESOLVER = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplateResolver';

    /**
     * cms-framework's theme manager, for the active theme's slug.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const THEME_MANAGER = 'ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager';

    /**
     * visual-editor's template component (`<x-ve-template>`).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const COMPONENT = 've-template';

    /**
     * The theme slug template entries carry when no theme is active.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const FALLBACK_THEME = 'storefront';

    /**
     * The pattern category.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PATTERN_CATEGORY = 'commerce';

    /**
     * The class on a pattern's outer group.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const PATTERN_CLASS = 'ec-pattern';

    /**
     * The class on a pattern's heading row (a flex group: the heading at
     * the start, a link at the end).
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const PATTERN_HEADER_CLASS = 'ec-pattern__header';

    /**
     * The class on a pattern's heading: the design system's heading-xl
     * type (ec-pattern__title in resources/css/storefront.css).
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const PATTERN_TITLE_CLASS = 'ec-pattern__title';

    /**
     * The class on a pattern's standalone link paragraph.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const PATTERN_LINK_CLASS = 'ec-pattern__link';

    /**
     * The class on the sale banner's group; its parts are `__copy`,
     * `__badge`, `__title`, `__text`, `__action` and `__media`.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const SALE_BANNER_CLASS = 'ec-sale-banner';

    /**
     * The class on a default template's group.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const TEMPLATE_CLASS = 'ec-template';

    /**
     * The class on a default template's page heading: the page header's
     * heading-xl type (ec-template__title in resources/css/storefront.css).
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const TEMPLATE_TITLE_CLASS = 'ec-template__title';

    /**
     * The default template slugs.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const TEMPLATES = [ 'single-product', 'product-archive', 'product-category', 'product-tag', 'cart', 'checkout', 'search-results' ];

    /**
     * The pages that can render through a template, and the template they
     * fall back to.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const PAGES = [
        'product'  => 'single-product',
        'catalog'  => 'product-archive',
        'category' => 'product-category',
        'tag'      => 'product-tag',
        'cart'     => 'cart',
        'checkout' => 'checkout',
        'search'   => 'search-results',
    ];

    /**
     * Wires the patterns (and, with `visual_editor.templates` on, the
     * templates) into visual-editor's site editor. visual-editor reads the
     * filters once everything has booted.
     *
     * @since 1.0.0
     *
     * @param  Application  $app  The application.
     *
     * @return void
     */
    public static function boot( Application $app ): void
    {
        addFilter( 'ap.visualEditor.patterns', [ self::class, 'contributePatterns' ] );

        if ( self::templatesOn() ) {
            addFilter( 'ap.visualEditor.templates', [ self::class, 'contributeTemplates' ] );
        }
    }

    /**
     * Whether `visual_editor.templates` is on.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function templatesOn(): bool
    {
        return (bool) config( 'artisanpack.ecommerce-storefront-livewire.visual_editor.templates', false );
    }

    /**
     * Whether pages can render through templates: `visual_editor.templates`
     * is on, the blocks are registered, and visual-editor's
     * `<x-ve-template>` and cms-framework's resolver are installed.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function enabled(): bool
    {
        return self::templatesOn()
            && StorefrontBlocks::available( app() )
            && class_exists( self::RESOLVER )
            && array_key_exists( self::COMPONENT, Blade::getClassComponentAliases() );
    }

    /**
     * The template a page renders through: the most specific saved
     * template along its chain, or null to render the page as usual
     * (templates off, nothing saved, or the lookup failed).
     *
     * @since 1.0.0
     *
     * @param  string      $page     A key of {@see self::PAGES}.
     * @param  Model|null  $subject  The product, category, or tag.
     *
     * @return string|null
     */
    public static function for( string $page, ?Model $subject = null ): ?string
    {
        if ( ! self::enabled() ) {
            return null;
        }

        try {
            $resolver = app( self::RESOLVER );

            foreach ( self::chain( $page, $subject ) as $slug ) {
                $entity = $resolver->resolve( $slug );

                if ( null !== $entity && is_array( $entity->blocks ?? null ) ) {
                    return $slug;
                }
            }
        } catch ( Throwable $exception ) {
            report( $exception );
        }

        return null;
    }

    /**
     * The template slugs a page looks for, most specific first.
     *
     * @since 1.0.0
     *
     * @param  string      $page     A key of {@see self::PAGES}.
     * @param  Model|null  $subject  The product, category, or tag.
     *
     * @return array<int, string>
     */
    public static function chain( string $page, ?Model $subject = null ): array
    {
        $slug = static fn ( mixed $value ): ?string => is_string( $value ) && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $value ) ? strtolower( $value ) : null;

        $chain = match ( true ) {
            'product' === $page && $subject instanceof Product          => [ 'single-product-' . $slug( $subject->slug ), 'single-product-' . $slug( $subject->type ), 'single-product' ],
            'category' === $page && $subject instanceof ProductCategory => [ 'product-category-' . $slug( $subject->slug ), 'product-category', 'product-archive' ],
            'tag' === $page && $subject instanceof ProductTag           => [ 'product-tag-' . $slug( $subject->slug ), 'product-tag', 'product-archive' ],
            default                                                     => array_filter( [ self::PAGES[ $page ] ?? null ] ),
        };

        // A slug that isn't a safe template slug drops its entry.
        return array_values( array_unique( array_filter( $chain, static fn ( string $entry ): bool => ! str_ends_with( $entry, '-' ) ) ) );
    }

    /**
     * The default templates, through
     * `ap.ecommerceStorefrontLivewire.templates`: slug => `title`,
     * `description`, and `content` (serialized block markup).
     *
     * @since 1.0.0
     *
     * @return array<string, array{title: string, description: string, content: string}>
     */
    public static function templates(): array
    {
        $catalog = self::block( 'product-catalog' );

        $defaults = [
            'single-product'   => [
                'title'       => __( 'Single Product' ),
                'description' => __( 'Every product page. Add single-product-{slug} or single-product-{type} for one product or product type.' ),
                'content'     => self::block( 'single-product' ),
            ],
            'product-archive'  => [
                'title'       => __( 'Product Catalog' ),
                'description' => __( 'The shop page, and category and tag pages without their own template.' ),
                'content'     => self::template( self::heading( __( 'Shop' ), 1, self::TEMPLATE_TITLE_CLASS ), $catalog ),
            ],
            'product-category' => [
                'title'       => __( 'Product Category' ),
                'description' => __( 'Every category page. Add product-category-{slug} for one category.' ),
                'content'     => self::template( $catalog ),
            ],
            'product-tag'      => [
                'title'       => __( 'Product Tag' ),
                'description' => __( 'Every tag page. Add product-tag-{slug} for one tag.' ),
                'content'     => self::template( $catalog ),
            ],
            'cart'             => [
                'title'       => __( 'Cart' ),
                'description' => __( 'The cart page.' ),
                'content'     => self::template( self::heading( __( 'Cart' ), 1, self::TEMPLATE_TITLE_CLASS ), self::block( 'cart-contents' ) ),
            ],
            'checkout'         => [
                'title'       => __( 'Checkout' ),
                'description' => __( 'The checkout page.' ),
                'content'     => self::template( self::heading( __( 'Checkout' ), 1, self::TEMPLATE_TITLE_CLASS ), self::block( 'checkout-steps' ) ),
            ],
            'search-results'   => [
                'title'       => __( 'Product Search Results' ),
                'description' => __( 'The product search page.' ),
                'content'     => self::template( self::heading( __( 'Search' ), 1, self::TEMPLATE_TITLE_CLASS ), $catalog ),
            ],
        ];

        return self::entries( applyFilters( 'ap.ecommerceStorefrontLivewire.templates', $defaults ), [ 'title', 'description', 'content' ] );
    }

    /**
     * The block patterns, through `ap.ecommerceStorefrontLivewire.patterns`:
     * slug => `title`, `description`, and `content`.
     *
     * @since 1.0.0
     *
     * @return array<string, array{title: string, description: string, content: string}>
     */
    public static function patterns(): array
    {
        $defaults = [
            StorefrontBlock::NAMESPACE . '/featured-products' => [
                'title'       => __( 'Featured products' ),
                'description' => __( 'A heading with a "Shop all" link and a grid of featured products.' ),
                'content'     => self::section(
                    self::row(
                        self::heading( __( 'Featured products' ), 2, self::PATTERN_TITLE_CLASS ),
                        ...array_filter( [ self::shopAllLink() ] ),
                    ),
                    self::block( 'product-grid', [ 'source' => 'featured', 'limit' => 4, 'columns' => 4 ] ),
                ),
            ],
            StorefrontBlock::NAMESPACE . '/shop-by-category' => [
                'title'       => __( 'Shop by category' ),
                'description' => __( 'A heading and the top-level categories.' ),
                'content'     => self::section(
                    self::heading( __( 'Shop by category' ), 2, self::PATTERN_TITLE_CLASS ),
                    self::block( 'category-grid', [ 'limit' => 6, 'columns' => 3 ] ),
                ),
            ],
            StorefrontBlock::NAMESPACE . '/sale-banner-grid' => [
                'title'       => __( 'Sale banner + grid' ),
                'description' => __( 'A sale banner above a grid of products on sale.' ),
                'content'     => self::section(
                    self::saleBanner(),
                    self::block( 'product-grid', [ 'source' => 'on_sale', 'limit' => 4, 'columns' => 4 ] ),
                ),
            ],
        ];

        return self::entries( applyFilters( 'ap.ecommerceStorefrontLivewire.patterns', $defaults ), [ 'title', 'description', 'content' ] );
    }

    /**
     * Adds the default templates to visual-editor's
     * (`ap.visualEditor.templates`), as theme templates of the active
     * theme. A template the host or theme already provides is kept.
     *
     * @since 1.0.0
     *
     * @param  mixed  $templates  Slug => template entry.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function contributeTemplates( mixed $templates ): array
    {
        $templates = is_array( $templates ) ? $templates : [];
        $theme     = self::activeTheme();

        foreach ( self::templates() as $slug => $template ) {
            $templates[ $slug ] ??= [
                'slug'           => $slug,
                'theme'          => $theme,
                'title'          => $template['title'],
                'description'    => $template['description'],
                'status'         => 'publish',
                'source'         => 'theme',
                'raw_content'    => $template['content'],
                'blocks'         => [],
                'has_theme_file' => true,
                'is_custom'      => false,
            ];
        }

        return $templates;
    }

    /**
     * Adds the patterns to visual-editor's (`ap.visualEditor.patterns`). A
     * pattern the host already provides is kept.
     *
     * @since 1.0.0
     *
     * @param  mixed  $patterns  Slug => pattern entry.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function contributePatterns( mixed $patterns ): array
    {
        $patterns = is_array( $patterns ) ? $patterns : [];

        foreach ( self::patterns() as $slug => $pattern ) {
            $patterns[ $slug ] ??= [
                'slug'        => $slug,
                'title'       => $pattern['title'],
                'description' => $pattern['description'],
                'source'      => 'theme',
                'synced'      => false,
                'categories'  => [ self::PATTERN_CATEGORY ],
                'blocks'      => [],
                'raw_content' => $pattern['content'],
            ];
        }

        return $patterns;
    }

    /**
     * A commerce block's markup.
     *
     * @since 1.0.0
     *
     * @param  string                $slug   The block's name after the namespace.
     * @param  array<string, mixed>  $attrs  Attributes.
     *
     * @return string
     */
    public static function block( string $slug, array $attrs = [] ): string
    {
        $json = [] === $attrs ? '' : ' ' . json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR );

        return '<!-- wp:' . StorefrontBlock::NAMESPACE . '/' . $slug . $json . ' /-->';
    }

    /**
     * The entries of a filtered list that are complete: slug => the
     * `$fields` as strings.
     *
     * @since 1.0.0
     *
     * @param  mixed               $entries  The filter's result.
     * @param  array<int, string>  $fields   Required string fields.
     *
     * @return array<string, array<string, string>>
     */
    private static function entries( mixed $entries, array $fields ): array
    {
        $clean = [];

        foreach ( is_array( $entries ) ? $entries : [] as $slug => $entry ) {
            if ( ! is_string( $slug ) || 1 !== preg_match( '#^[a-z0-9][a-z0-9_/-]*$#', $slug ) || ! is_array( $entry ) ) {
                continue;
            }

            $values = [];

            foreach ( $fields as $field ) {
                $values[ $field ] = is_string( $entry[ $field ] ?? null ) ? $entry[ $field ] : '';
            }

            if ( '' !== $values['title'] && '' !== $values['content'] ) {
                $clean[ $slug ] = $values;
            }
        }

        return $clean;
    }

    /**
     * The active theme's slug, or {@see self::FALLBACK_THEME}.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private static function activeTheme(): string
    {
        try {
            if ( class_exists( self::THEME_MANAGER ) && app()->bound( self::THEME_MANAGER ) ) {
                $theme = app( self::THEME_MANAGER )->getActiveTheme();

                if ( is_array( $theme ) && is_string( $theme['slug'] ?? null ) && '' !== $theme['slug'] ) {
                    return $theme['slug'];
                }
            }
        } catch ( Throwable $exception ) {
            report( $exception );
        }

        return self::FALLBACK_THEME;
    }

    /**
     * A template's blocks in a group, spaced by the editor's block spacing
     * (the group's default flow layout).
     *
     * @since 2.0.0
     *
     * @param  string  ...$blocks  Block markup.
     *
     * @return string
     */
    private static function template( string ...$blocks ): string
    {
        return self::group( self::TEMPLATE_CLASS, ...$blocks );
    }

    /**
     * A core group block.
     *
     * @since 1.0.0
     *
     * @param  string  $class      Extra classes.
     * @param  string  ...$blocks  Block markup.
     *
     * @return string
     */
    private static function group( string $class, string ...$blocks ): string
    {
        return self::groupWith( [ 'className' => $class ], ...$blocks );
    }

    /**
     * A core group block with the given attributes (`className` is
     * required).
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $attrs      The group's attributes.
     * @param  string                ...$blocks  Block markup.
     *
     * @return string
     */
    private static function groupWith( array $attrs, string ...$blocks ): string
    {
        return '<!-- wp:group ' . json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . " -->\n"
            . '<div class="wp-block-group ' . e( $attrs['className'] ) . "\">\n" . implode( "\n\n", $blocks ) . "\n</div>\n"
            . '<!-- /wp:group -->';
    }

    /**
     * A pattern's outer group. Its blocks are spaced by the editor's block
     * spacing (the group's default flow layout), so the pattern adds no
     * spacing of its own.
     *
     * @since 2.0.0
     *
     * @param  string  ...$blocks  Block markup.
     *
     * @return string
     */
    private static function section( string ...$blocks ): string
    {
        return self::group( self::PATTERN_CLASS, ...$blocks );
    }

    /**
     * A pattern's heading row: a flex group with the heading at the start
     * and anything after it at the end (`space-between`, mirrored in
     * right-to-left layouts), wrapping when the row is too narrow. The
     * editor's flex layout lays it out, on the canvas and the site alike.
     *
     * @since 2.0.0
     *
     * @param  string  ...$blocks  Block markup.
     *
     * @return string
     */
    private static function row( string ...$blocks ): string
    {
        return self::groupWith(
            [ 'className' => self::PATTERN_HEADER_CLASS, 'layout' => [ 'type' => 'flex', 'justifyContent' => 'space-between', 'verticalAlignment' => 'center' ] ],
            ...$blocks,
        );
    }

    /**
     * The "Shop all" link to the catalog: a paragraph holding a standalone
     * design-system Link with a forward arrow (mirrored in right-to-left
     * layouts). The href is root-relative, since an inserted pattern is
     * saved into the page and must survive a change of domain. Null when the
     * storefront's catalog route isn't registered.
     *
     * @since 2.0.0
     *
     * @return string|null
     */
    private static function shopAllLink(): ?string
    {
        if ( ! Route::has( 'artisanpack.ecommerce.storefront.catalog' ) ) {
            return null;
        }

        $link = self::component(
            '<x-artisanpack-link :href="$href" standalone trailing-icon="arrow-right" :navigate="false">{{ $label }}</x-artisanpack-link>',
            [ 'href' => route( 'artisanpack.ecommerce.storefront.catalog', [], false ), 'label' => __( 'Shop all' ) ],
        );

        return self::paragraph( $link, self::PATTERN_LINK_CLASS, false );
    }

    /**
     * The sale banner (#127): a flex group on the accent soft fill (the one
     * place a page uses the accent), the copy on the start half and an image
     * on the end half, stacking below 640px. The copy is a solid accent
     * "Sale" Badge, the heading, a line of text and, with the storefront
     * routes, a large solid neutral "Shop the sale" Button to the catalog's
     * on-sale products. The image block starts empty for the editor to
     * fill; the banner's copy spans it until it has an image.
     *
     * The group's flex layout and zero block gap are block attributes, so
     * the editor and the renderer lay the banner out; `ec-sale-banner*`
     * (resources/css/storefront.css) only adds the fill, radius, padding,
     * type and column sizes.
     *
     * @since 2.0.0
     *
     * @return string
     */
    private static function saleBanner(): string
    {
        $copy = [
            self::paragraph(
                self::component( '<x-artisanpack-badge variant="solid" color="accent" leading-icon="tag">{{ $label }}</x-artisanpack-badge>', [ 'label' => __( 'Sale' ) ] ),
                self::SALE_BANNER_CLASS . '__badge',
                false,
            ),
            self::heading( __( 'On sale now' ), 2, self::SALE_BANNER_CLASS . '__title' ),
            self::paragraph( __( 'Save on these favourites while they last.' ), self::SALE_BANNER_CLASS . '__text' ),
        ];

        if ( Route::has( 'artisanpack.ecommerce.storefront.catalog' ) ) {
            $copy[] = self::paragraph(
                self::component(
                    '<x-artisanpack-button :href="$href" color="neutral" size="lg">{{ $label }}</x-artisanpack-button>',
                    [ 'href' => route( 'artisanpack.ecommerce.storefront.catalog', [ 'on_sale' => 1 ], false ), 'label' => __( 'Shop the sale' ) ],
                ),
                self::SALE_BANNER_CLASS . '__action',
                false,
            );
        }

        return self::groupWith(
            [
                'className' => self::SALE_BANNER_CLASS,
                'layout'    => [ 'type' => 'flex', 'flexWrap' => 'wrap', 'verticalAlignment' => 'stretch' ],
                'style'     => [ 'spacing' => [ 'blockGap' => '0' ] ],
            ],
            self::group( self::SALE_BANNER_CLASS . '__copy', ...$copy ),
            self::image( self::SALE_BANNER_CLASS . '__media' ),
        );
    }

    /**
     * An empty core image block for the editor to fill: full width, cropped
     * to 4:3, with no bottom margin.
     *
     * @since 2.0.0
     *
     * @param  string  $class  The block's class.
     *
     * @return string
     */
    private static function image( string $class ): string
    {
        $attrs = [ 'width' => '100%', 'aspectRatio' => '4/3', 'scale' => 'cover', 'className' => $class, 'style' => [ 'spacing' => [ 'margin' => [ 'bottom' => '0' ] ] ] ];

        return '<!-- wp:image ' . json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . " -->\n"
            . '<figure class="wp-block-image ' . e( $class ) . "\" style=\"margin-bottom:0\"><img alt=\"\"/></figure>\n"
            . '<!-- /wp:image -->';
    }

    /**
     * A design-system component rendered to one line of HTML, for a
     * paragraph's content.
     *
     * @since 2.0.0
     *
     * @param  string                $template  Blade.
     * @param  array<string, mixed>  $data      Its data (escaped where it is echoed).
     *
     * @return string
     */
    private static function component( string $template, array $data ): string
    {
        return trim( (string) preg_replace( '/\s*\n\s*/', ' ', Blade::render( $template, $data ) ) );
    }

    /**
     * A core heading block.
     *
     * @since 1.0.0
     * @since 2.0.0 Takes the heading's class (no more Tailwind default).
     *
     * @param  string  $text   The heading.
     * @param  int     $level  1–6.
     * @param  string  $class  The heading's class.
     *
     * @return string
     */
    private static function heading( string $text, int $level, string $class ): string
    {
        return '<!-- wp:heading ' . json_encode( [ 'level' => $level, 'className' => $class ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . " -->\n"
            . '<h' . $level . ' class="wp-block-heading ' . e( $class ) . '">' . e( $text ) . '</h' . $level . ">\n"
            . '<!-- /wp:heading -->';
    }

    /**
     * A core paragraph block.
     *
     * @since 1.0.0
     * @since 2.0.0 Takes a class, and already-escaped HTML.
     *
     * @param  string       $text    The text (or HTML when `$escape` is false).
     * @param  string|null  $class   The paragraph's class.
     * @param  bool         $escape  Whether to escape `$text`.
     *
     * @return string
     */
    private static function paragraph( string $text, ?string $class = null, bool $escape = true ): string
    {
        $content = $escape ? e( $text ) : $text;

        if ( null === $class ) {
            return "<!-- wp:paragraph -->\n<p>" . $content . "</p>\n<!-- /wp:paragraph -->";
        }

        return '<!-- wp:paragraph ' . json_encode( [ 'className' => $class ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . " -->\n"
            . '<p class="' . e( $class ) . '">' . $content . "</p>\n<!-- /wp:paragraph -->";
    }
}
