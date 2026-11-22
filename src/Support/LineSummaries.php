<?php

/**
 * Line configuration summaries.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\CartItem;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Support\LineOptionDescriber;
use Illuminate\Support\Str;
use Throwable;

/**
 * The short "Label: Value" texts that summarize a cart or order line's
 * configuration ("Size: 16 × 20 in", "Frame: Black oak").
 *
 * The rows come from the engine's `LineOptionDescriber` (engine 1.4), which
 * asks the product type (`DescribesLineOptions`, as configurable products
 * implement it) and reads an order line's rows from its snapshot, so a
 * placed order keeps the wording it was bought with. With an older engine,
 * a cart line falls back to the variant name and the headlined scalar
 * options, and an order line to its snapshot's variant name.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */
final class LineSummaries
{
    /**
     * Whether the engine can describe line options.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    public static function engineDescribes(): bool
    {
        return class_exists( LineOptionDescriber::class );
    }

    /**
     * A cart line's summary.
     *
     * @since 2.0.0
     *
     * @param  CartItem  $item  The line, with its product and variant loaded.
     *
     * @return array<int, string>
     */
    public static function forCartItem( CartItem $item ): array
    {
        if ( self::engineDescribes() ) {
            try {
                return self::texts( app( LineOptionDescriber::class )->forCartItem( $item ) );
            } catch ( Throwable $exception ) {
                report( $exception );
            }
        }

        $options = [];

        if ( null !== $item->variant && '' !== trim( (string) $item->variant->name ) ) {
            $options[] = (string) $item->variant->name;
        }

        foreach ( (array) ( $item->options ?? [] ) as $key => $value ) {
            if ( is_string( $key ) && ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) && '' !== trim( (string) $value ) ) {
                $options[] = __( ':option: :value', [ 'option' => Str::headline( $key ), 'value' => (string) $value ] );
            }
        }

        return $options;
    }

    /**
     * An order line's summary, from what was recorded when it was placed.
     *
     * @since 2.0.0
     *
     * @param  OrderItem  $item  The line.
     *
     * @return array<int, string>
     */
    public static function forOrderItem( OrderItem $item ): array
    {
        if ( self::engineDescribes() ) {
            try {
                return self::texts( app( LineOptionDescriber::class )->forOrderItem( $item ) );
            } catch ( Throwable $exception ) {
                report( $exception );
            }
        }

        $snapshot = (array) ( $item->product_snapshot ?? [] );
        $variant  = is_string( $snapshot['variant_name'] ?? null ) ? trim( $snapshot['variant_name'] ) : '';

        return '' === $variant ? [] : [ $variant ];
    }

    /**
     * The engine's rows as texts: "Label: Value", or the value alone when a
     * row has no label (a variant name). Rows without a value are dropped.
     *
     * @since 2.0.0
     *
     * @param  mixed  $rows  The engine's `label`/`value` rows.
     *
     * @return array<int, string>
     */
    private static function texts( mixed $rows ): array
    {
        $texts = [];

        foreach ( is_array( $rows ) ? $rows : [] as $row ) {
            $label = is_array( $row ) && is_scalar( $row['label'] ?? null ) ? trim( (string) $row['label'] ) : '';
            $value = is_array( $row ) && is_scalar( $row['value'] ?? null ) ? trim( (string) $row['value'] ) : '';

            if ( '' === $value ) {
                continue;
            }

            $texts[] = '' === $label ? $value : __( ':option: :value', [ 'option' => $label, 'value' => $value ] );
        }

        return $texts;
    }
}
