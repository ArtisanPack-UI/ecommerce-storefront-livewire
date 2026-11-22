<?php

/**
 * The storefront's own stylesheet.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

/**
 * Reads `resources/css/storefront.css`, the token-only styles the design
 * system has no component for (the product card, prices, the account
 * navigation), so the layouts can inline it without a build step.
 *
 * The file is the package's own and never holds user input; a `</style>`
 * sequence is still escaped so the contents can't close the element early.
 * It is inlined into every page, so it is minified first (comments dropped,
 * whitespace collapsed, strings left alone) and the result kept for the
 * rest of the process.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */
final class StorefrontStyles
{

    /**
     * Matches a quoted CSS string, escapes included.
     *
     * @since 2.0.0
     *
     * @var string
     */
    private const STRING_PATTERN = '"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'';

    /**
     * The stylesheet, once read.
     *
     * @since 2.0.0
     *
     * @var string|null
     */
    private static ?string $css = null;

    /**
     * The stylesheet's path.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public static function path(): string
    {
        return dirname( __DIR__, 2 ) . '/resources/css/storefront.css';
    }

    /**
     * The stylesheet's contents, minified and safe to print inside a
     * `<style>` element.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public static function css(): string
    {
        if ( null === self::$css ) {
            $contents  = is_file( self::path() ) ? (string) file_get_contents( self::path() ) : '';
            self::$css = str_ireplace( '</style', '<\/style', self::minify( $contents ) );
        }

        return self::$css;
    }

    /**
     * Minifies a stylesheet: drops comments, collapses whitespace and the
     * spaces around `{`, `}`, `;` and `,`, and the last `;` in a block.
     * Quoted strings are kept as written. Spaces next to `:`, `>`, `+` and
     * `(` are kept (collapsed to one), since in a selector they can change
     * what it matches.
     *
     * @since 2.0.0
     *
     * @param  string  $css  The stylesheet.
     *
     * @return string
     */
    public static function minify( string $css ): string
    {
        $css = (string) preg_replace_callback(
            '~(' . self::STRING_PATTERN . ')|/\*.*?\*/~s',
            static fn ( array $match ): string => $match[1] ?? '',
            $css,
        );

        $parts = preg_split( '~(' . self::STRING_PATTERN . ')~s', $css, -1, PREG_SPLIT_DELIM_CAPTURE );
        $parts = false === $parts ? [ $css ] : $parts;

        foreach ( $parts as $index => $part ) {
            // Odd indexes are the quoted strings.
            if ( 1 === $index % 2 ) {
                continue;
            }

            $part            = (string) preg_replace( '/\s+/', ' ', $part );
            $part            = (string) preg_replace( '/\s*([{};,])\s*/', '$1', $part );
            $parts[ $index ] = str_replace( ';}', '}', $part );
        }

        return trim( implode( '', $parts ) );
    }
}
