<?php

/**
 * Storefront block registrar.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\Ecommerce\Models\Product;
use Illuminate\Contracts\Foundation\Application;

/**
 * Registers the commerce blocks with visual-editor (spec §11.1, D8).
 *
 * visual-editor is a soft dependency: nothing happens unless it is
 * installed (its `VisualEditor` service is bound) and
 * `visual_editor.blocks` is on. Then, once every provider has booted (so
 * satellites such as recently-viewed have registered), each block in
 * {@see self::BLOCKS} (through `ap.ecommerceStorefrontLivewire.blocks`) that is
 * available is registered with `VisualEditor::registerServerBlock()`: no
 * JS build, the editor builds the inspector from the attributes and
 * previews through the server. Their names are added to visual-editor's
 * `enabled_blocks` allow-list when the host uses one, and `products` is
 * mapped in visual-editor's resources (`ap.visualEditor.resources`) so
 * preview requests can name the product being edited. The default
 * templates and patterns are offered too ({@see StorefrontTemplates}).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class StorefrontBlocks
{
    /**
     * visual-editor's main service.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const EDITOR = 'ArtisanPackUI\\VisualEditor\\VisualEditor';

    /**
     * The core blocks, in inserter order.
     *
     * @since 1.0.0
     *
     * @var array<int, class-string<StorefrontBlock>>
     */
    public const BLOCKS = [
        ProductGridBlock::class,
        CategoryGridBlock::class,
        RelatedProductsBlock::class,
        RecentlyViewedBlock::class,
        SingleProductBlock::class,
        ProductGalleryBlock::class,
        ProductPriceBlock::class,
        AddToCartBlock::class,
        ProductReviewsBlock::class,
        ProductCatalogBlock::class,
        CartContentsBlock::class,
        CheckoutStepsBlock::class,
    ];

    /**
     * Whether blocks can be registered: visual-editor is installed and
     * `visual_editor.blocks` is on.
     *
     * @since 1.0.0
     *
     * @param  Application  $app  The application.
     *
     * @return bool
     */
    public static function available( Application $app ): bool
    {
        return (bool) config( 'artisanpack.ecommerce-storefront-livewire.visual_editor.blocks', true )
            && class_exists( self::EDITOR )
            && $app->bound( self::EDITOR );
    }

    /**
     * Wires the blocks when visual-editor is available: the resource map
     * now (visual-editor reads it once everything has booted), the blocks
     * once everything has booted.
     *
     * @since 1.0.0
     *
     * @param  Application  $app  The application.
     *
     * @return void
     */
    public static function boot( Application $app ): void
    {
        if ( ! self::available( $app ) ) {
            return;
        }

        addFilter( 'ap.visualEditor.resources', [ self::class, 'mapResources' ] );

        StorefrontTemplates::boot( $app );

        $app->booted( static function () use ( $app ): void {
            self::register( $app );
        } );
    }

    /**
     * Registers the available blocks and allows them in the editor.
     *
     * @since 1.0.0
     *
     * @param  Application  $app  The application.
     *
     * @return array<int, string> The registered block names.
     */
    public static function register( Application $app ): array
    {
        $editor = $app->make( self::EDITOR );
        $names  = [];

        foreach ( self::blocks( $app ) as $block ) {
            if ( ! $block->available() ) {
                continue;
            }

            $editor->registerServerBlock( $block->name(), $block->metadata(), [ $block, 'render' ], [
                'validateAttrs' => [ $block, 'validateAttrs' ],
                'authorize'     => [ $block, 'authorize' ],
            ] );

            $names[] = $block->name();
        }

        self::allow( $names );

        return $names;
    }

    /**
     * The block instances, through `ap.ecommerceStorefrontLivewire.blocks`
     * (a list of `StorefrontBlock` class names; add one, or remove a core
     * block).
     *
     * @since 1.0.0
     *
     * @param  Application  $app  The application.
     *
     * @return array<int, StorefrontBlock>
     */
    public static function blocks( Application $app ): array
    {
        $classes = applyFilters( 'ap.ecommerceStorefrontLivewire.blocks', self::BLOCKS );
        $blocks  = [];

        foreach ( is_array( $classes ) ? $classes : self::BLOCKS as $class ) {
            if ( is_string( $class ) && is_subclass_of( $class, StorefrontBlock::class ) ) {
                $blocks[] = $app->make( $class );
            }
        }

        return $blocks;
    }

    /**
     * Adds `$names` to visual-editor's `enabled_blocks` allow-list when the
     * host uses one (an empty list allows every block already).
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $names  Block names.
     *
     * @return void
     */
    public static function allow( array $names ): void
    {
        $enabled = config( 'artisanpack.visual-editor.enabled_blocks' );

        if ( ! is_array( $enabled ) || [] === $enabled || [] === $names ) {
            return;
        }

        config( [ 'artisanpack.visual-editor.enabled_blocks' => array_values( array_unique( [ ...$enabled, ...$names ] ) ) ] );
    }

    /**
     * Maps `products` to the engine's product model in visual-editor's
     * resources, unless the host mapped the key itself.
     *
     * @since 1.0.0
     *
     * @param  mixed  $resources  Slug => model class.
     *
     * @return array<string, string>
     */
    public static function mapResources( mixed $resources ): array
    {
        $resources = is_array( $resources ) ? $resources : [];

        $resources[ StorefrontBlock::PRODUCT_RESOURCE ] ??= Product::class;

        return $resources;
    }
}
