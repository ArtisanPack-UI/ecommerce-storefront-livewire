<?php

/**
 * Storefront block base.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Blocks;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * The base for the commerce blocks registered with visual-editor
 * (spec §11.1).
 *
 * A block describes itself (title, icon, attributes with `apControl`
 * inspector hints) and renders a storefront component; visual-editor
 * builds the editor UI from the metadata, so there is no JS build. Every
 * block:
 *
 * - **clamps its attributes** in {@see self::validateAttrs()} against its
 *   schema: booleans, integers within `apControl.min`/`max`, enums, and
 *   strings of at most 200 characters; unknown attributes are dropped;
 * - **escapes its output**: it renders Blade views, which escape every
 *   attribute;
 * - **lets editors preview it**: {@see self::authorize()} allows any signed-in
 *   user (the preview API already requires one; products are always
 *   storefront-visible ones);
 * - **never breaks the page**: when the engine throws, the error is
 *   reported and the block renders nothing (a notice in the editor).
 *
 * Product blocks find their product with {@see self::product()}: the
 * `productId` attribute, else the page's product from
 * {@see StorefrontContext}, else (in an editor preview) the resource being
 * edited or the newest product, labelled as a sample.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
abstract class StorefrontBlock
{
    /**
     * The block name namespace.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const NAMESPACE = 'artisanpack-commerce';

    /**
     * The inserter category.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CATEGORY = 'widgets';

    /**
     * The visual-editor resource key products are mapped under.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PRODUCT_RESOURCE = 'products';

    /**
     * The visual-editor block preview route.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PREVIEW_ROUTE = 'visual-editor.api.blocks.preview';

    /**
     * The longest string attribute, in characters.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_STRING_LENGTH = 200;

    /**
     * Whether the last render fell back to a sample product.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    protected bool $sample = false;

    /**
     * The block's name after the namespace (`product-grid`).
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract public function slug(): string;

    /**
     * The inserter title.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract public function title(): string;

    /**
     * The inserter description.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract public function description(): string;

    /**
     * The block.json `attributes`, with `apControl` inspector hints.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    abstract public function attributes(): array;

    /**
     * The full block name (`artisanpack-commerce/product-grid`).
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAMESPACE . '/' . $this->slug();
    }

    /**
     * Whether the block should be registered (e.g. its satellite is
     * installed).
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function available(): bool
    {
        return true;
    }

    /**
     * The block.json-shaped metadata visual-editor registers.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return [
            'title'       => $this->title(),
            'description' => $this->description(),
            'category'    => self::CATEGORY,
            'icon'        => $this->icon(),
            'keywords'    => [ __( 'commerce' ), __( 'shop' ), __( 'store' ), ...$this->keywords() ],
            'attributes'  => $this->attributes(),
            'supports'    => [ 'html' => false ],
        ];
    }

    /**
     * Clamps the attributes against {@see self::attributes()}; anything
     * unknown is dropped and anything invalid falls back to its default.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $attrs  Attributes from the editor or saved content.
     *
     * @return array<string, mixed>
     */
    public function validateAttrs( array $attrs ): array
    {
        $clean = [];

        foreach ( $this->attributes() as $key => $schema ) {
            $default = $schema['default'] ?? null;
            $value   = array_key_exists( $key, $attrs ) ? $attrs[ $key ] : $default;

            $clean[ $key ] = match ( $schema['type'] ?? 'string' ) {
                'boolean'           => self::booleanValue( $value, (bool) $default ),
                'number', 'integer' => self::integerValue( $value, (int) $default, $schema['apControl']['min'] ?? null, $schema['apControl']['max'] ?? null ),
                default             => self::stringValue( $value, (string) $default, $schema['enum'] ?? null ),
            };
        }

        return $clean;
    }

    /**
     * Whether `$user` may preview the block in the editor: any signed-in
     * user.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user   The user.
     * @param  array<string, mixed>  $attrs  Clamped attributes.
     *
     * @return bool
     */
    public function authorize( ?Authenticatable $user, array $attrs ): bool
    {
        return null !== $user;
    }

    /**
     * Renders the block: its HTML in a wrapper (with a "sample product"
     * label in previews that fell back to one), a notice in an editor
     * preview when there is nothing to show, or nothing on the site.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $attrs  Clamped attributes.
     *
     * @return string
     */
    public function render( array $attrs ): string
    {
        $this->sample = false;

        try {
            $html = $this->html( $attrs );
        } catch ( Throwable $exception ) {
            report( $exception );

            return self::previewing() ? $this->notice( __( 'This block couldn\'t be shown. Check the logs for the error.' ) ) : '';
        }

        if ( null === $html ) {
            return self::previewing() ? $this->notice( $this->emptyMessage() ) : '';
        }

        return view( 'ecommerce-storefront::blocks.block', [
            'slug'   => $this->slug(),
            'html'   => $html,
            'sample' => $this->sample,
        ] )->render();
    }

    /**
     * Whether this request is a visual-editor block preview.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function previewing(): bool
    {
        return request()->routeIs( self::PREVIEW_ROUTE );
    }

    /**
     * The block's HTML for clamped attributes, or null when there is
     * nothing to show.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $attrs  Clamped attributes.
     *
     * @return string|null
     */
    abstract protected function html( array $attrs ): ?string;

    /**
     * The inserter icon (a dashicon name).
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function icon(): string
    {
        return 'cart';
    }

    /**
     * Extra inserter keywords.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function keywords(): array
    {
        return [];
    }

    /**
     * What an editor preview says when there is nothing to show.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function emptyMessage(): string
    {
        return __( 'Nothing to show yet. Check the block settings.' );
    }

    /**
     * The product a product block shows: the `productId` attribute's (when
     * it is storefront-visible), else the page's, else in an editor preview
     * the product being edited or the newest one as a labelled sample.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $attrs  Clamped attributes.
     *
     * @return Product|null
     */
    protected function product( array $attrs ): ?Product
    {
        $id = (int) ( $attrs['productId'] ?? 0 );

        if ( $id > 0 && null !== ( $chosen = self::visibleProduct( $id ) ) ) {
            return $chosen;
        }

        $context = app( StorefrontContext::class )->product();

        if ( null !== $context ) {
            return $context;
        }

        if ( ! self::previewing() ) {
            return null;
        }

        $edited = self::previewedProduct();

        if ( null !== $edited ) {
            return $edited;
        }

        $sample = Product::query()->storefrontVisible()->orderByDesc( 'created_at' )->orderByDesc( 'id' )->limit( 10 )->get()
            ->first( static fn ( Product $product ): bool => ! $product->typeIsMissing() );

        $this->sample = null !== $sample;

        return $sample;
    }

    /**
     * The `productId` attribute's schema.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected static function productIdAttribute(): array
    {
        return [
            'type'      => 'number',
            'default'   => 0,
            'apControl' => [
                'control' => 'number',
                'label'   => __( 'Product ID' ),
                'help'    => __( 'Leave at 0 to show the product of the page this block is on.' ),
                'min'     => 0,
            ],
        ];
    }

    /**
     * Select options for an enum attribute.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $labels  Value => label.
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected static function options( array $labels ): array
    {
        return array_map( static fn ( string $value, string $label ): array => [ 'label' => $label, 'value' => $value ], array_keys( $labels ), $labels );
    }

    /**
     * A storefront-visible product of a type that is still installed.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Product id.
     *
     * @return Product|null
     */
    protected static function visibleProduct( int $id ): ?Product
    {
        $product = Product::query()->storefrontVisible()->whereKey( $id )->first();

        return null === $product || $product->typeIsMissing() ? null : $product;
    }

    /**
     * The product a preview request is about (`context.resource` is the
     * products resource and `context.id` its id), when it is visible.
     *
     * @since 1.0.0
     *
     * @return Product|null
     */
    protected static function previewedProduct(): ?Product
    {
        $resource = request()->input( 'context.resource' );
        $id       = request()->input( 'context.id' );
        $mapped   = config( 'artisanpack.visual-editor.resources.' . self::PRODUCT_RESOURCE, Product::class );

        if ( self::PRODUCT_RESOURCE !== $resource || ! is_numeric( $id ) || ! is_string( $mapped ) || ! is_a( $mapped, Product::class, true ) ) {
            return null;
        }

        return self::visibleProduct( (int) $id );
    }

    /**
     * A notice for editor previews.
     *
     * @since 1.0.0
     *
     * @param  string  $message  The message.
     *
     * @return string
     */
    protected function notice( string $message ): string
    {
        return view( 'ecommerce-storefront::blocks.notice', [ 'slug' => $this->slug(), 'title' => $this->title(), 'message' => $message ] )->render();
    }

    /**
     * A boolean attribute value.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value    The value.
     * @param  bool   $default  The default.
     *
     * @return bool
     */
    protected static function booleanValue( mixed $value, bool $default ): bool
    {
        if ( is_bool( $value ) ) {
            return $value;
        }

        return is_scalar( $value ) ? ( filter_var( $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ?? $default ) : $default;
    }

    /**
     * An integer attribute value, within bounds.
     *
     * @since 1.0.0
     *
     * @param  mixed     $value    The value.
     * @param  int       $default  The default.
     * @param  int|null  $min      The lowest value.
     * @param  int|null  $max      The highest value.
     *
     * @return int
     */
    protected static function integerValue( mixed $value, int $default, ?int $min, ?int $max ): int
    {
        $number = is_int( $value ) || ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/', trim( $value ) ) ) || ( is_float( $value ) && floor( $value ) === $value )
            ? (int) $value
            : $default;

        $number = null === $min ? $number : max( $min, $number );

        return null === $max ? $number : min( $max, $number );
    }

    /**
     * A string attribute value: one of `$enum` when given, else trimmed,
     * without control characters, at most {@see self::MAX_STRING_LENGTH}
     * characters.
     *
     * @since 1.0.0
     *
     * @param  mixed                    $value    The value.
     * @param  string                   $default  The default.
     * @param  array<int, string>|null  $enum     Allowed values.
     *
     * @return string
     */
    protected static function stringValue( mixed $value, string $default, ?array $enum ): string
    {
        if ( ! is_scalar( $value ) ) {
            return $default;
        }

        $string = trim( (string) preg_replace( '/\p{Cc}+/u', ' ', (string) $value ) );

        if ( null !== $enum ) {
            return in_array( $string, $enum, true ) ? $string : $default;
        }

        return mb_substr( $string, 0, self::MAX_STRING_LENGTH );
    }
}
