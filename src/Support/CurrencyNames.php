<?php

/**
 * Currency names.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use NumberFormatter;
use ResourceBundle;
use Throwable;

/**
 * Localized currency names and symbols from ICU, for the currency
 * switcher.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class CurrencyNames
{
    /**
     * The currency's localized name ("Euro"), or the code when ICU doesn't
     * know it.
     *
     * @since 1.0.0
     *
     * @param  string       $code    ISO 4217 code.
     * @param  string|null  $locale  Display locale; defaults to the app locale.
     *
     * @return string
     */
    public static function name( string $code, ?string $locale = null ): string
    {
        $code = strtoupper( trim( $code ) );

        try {
            $name = ResourceBundle::create( $locale ?? app()->getLocale(), 'ICUDATA-curr' )?->get( 'Currencies' )?->get( $code )?->get( 1 );
        } catch ( Throwable ) {
            $name = null;
        }

        return is_string( $name ) && '' !== $name ? $name : $code;
    }

    /**
     * The currency's symbol in `$locale` ("€"), or null when it is just the
     * code.
     *
     * @since 1.0.0
     *
     * @param  string       $code    ISO 4217 code.
     * @param  string|null  $locale  Display locale; defaults to the app locale.
     *
     * @return string|null
     */
    public static function symbol( string $code, ?string $locale = null ): ?string
    {
        $code = strtoupper( trim( $code ) );

        try {
            $symbol = ( new NumberFormatter( ( $locale ?? app()->getLocale() ) . '@currency=' . $code, NumberFormatter::CURRENCY ) )->getSymbol( NumberFormatter::CURRENCY_SYMBOL );
        } catch ( Throwable ) {
            return null;
        }

        return is_string( $symbol ) && '' !== $symbol && strtoupper( $symbol ) !== $code ? $symbol : null;
    }

    /**
     * The switcher label: "Euro (EUR, €)", or "Swiss Franc (CHF)" when the
     * symbol is the code.
     *
     * @since 1.0.0
     *
     * @param  string       $code    ISO 4217 code.
     * @param  string|null  $locale  Display locale; defaults to the app locale.
     *
     * @return string
     */
    public static function label( string $code, ?string $locale = null ): string
    {
        $code   = strtoupper( trim( $code ) );
        $name   = self::name( $code, $locale );
        $symbol = self::symbol( $code, $locale );

        if ( $name === $code ) {
            return null === $symbol ? $code : __( ':code (:symbol)', [ 'code' => $code, 'symbol' => $symbol ] );
        }

        return null === $symbol
            ? __( ':name (:code)', [ 'name' => $name, 'code' => $code ] )
            : __( ':name (:code, :symbol)', [ 'name' => $name, 'code' => $code, 'symbol' => $symbol ] );
    }
}
