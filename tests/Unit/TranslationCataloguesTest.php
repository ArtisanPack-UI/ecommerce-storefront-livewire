<?php

/**
 * Translation catalogue guards.
 *
 * The engine's translation lint only checks en, es, fr and de, so these
 * tests keep every shipped catalogue in step with `lang/en.json`: the same
 * keys, the same placeholders, the right number of plural forms, and no
 * keys the package no longer uses.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

/**
 * Keys kept in every catalogue even though no shipped view uses them.
 *
 * The 1.x checkout step views used these, so a host that published those
 * views keeps its translations until it replaces them (see docs/upgrading.md).
 */
const LEGACY_TRANSLATION_KEYS = [
    'I accept the terms and conditions',
    'Order note (optional)',
    'Read the terms and conditions',
    'Sign in for faster checkout',
    'We\'ll send your order confirmation here.',
    'You\'ll pay :total.',
];

/**
 * Plural forms each locale's catalogue must give for a `one|other` key.
 */
const PLURAL_FORMS = [
    'ar'    => 6,
    'ja'    => 1,
    'zh_CN' => 1,
];

/**
 * Reads a catalogue from lang/.
 *
 * @return array<string, string>
 */
function translationCatalogue( string $locale ): array
{
    return json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/lang/' . $locale . '.json' ), true, 512, JSON_THROW_ON_ERROR );
}

/**
 * The locales shipped in lang/, other than en.
 *
 * @return array<int, string>
 */
function translatedLocales(): array
{
    $locales = array_map(
        static fn ( string $path ): string => basename( $path, '.json' ),
        glob( dirname( __DIR__, 2 ) . '/lang/*.json' ) ?: [],
    );

    return array_values( array_diff( $locales, [ 'en' ] ) );
}

/**
 * The `:placeholder` names in a string, sorted and unique.
 *
 * @return array<int, string>
 */
function translationPlaceholders( string $value ): array
{
    preg_match_all( '/:([A-Za-z_]+)/', $value, $matches );

    $names = array_values( array_unique( $matches[1] ) );
    sort( $names );

    return $names;
}

/**
 * Every literal key passed to __(), trans_choice() or @lang in src/ and
 * resources/views, skipping docblock lines.
 *
 * @return array<string, true>
 */
function referencedTranslationKeys(): array
{
    $root  = dirname( __DIR__, 2 );
    $keys  = [];
    $files = [];

    foreach ( [ $root . '/src', $root . '/resources/views' ] as $directory ) {
        foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
            if ( str_ends_with( $file->getPathname(), '.php' ) ) {
                $files[] = $file->getPathname();
            }
        }
    }

    foreach ( $files as $path ) {
        $lines = array_filter(
            file( $path ) ?: [],
            static fn ( string $line ): bool => 1 !== preg_match( '/^\s*(\*|\/\*\*)/', $line ),
        );
        $code  = implode( '', $lines );

        preg_match_all( "/(?:__|trans_choice|@lang)\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $code, $single );
        preg_match_all( '/(?:__|trans_choice|@lang)\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $code, $double );

        foreach ( $single[1] as $key ) {
            $keys[ str_replace( [ '\\\'', '\\\\' ], [ '\'', '\\' ], $key ) ] = true;
        }

        foreach ( $double[1] as $key ) {
            $keys[ stripcslashes( $key ) ] = true;
        }
    }

    return $keys;
}

it( 'ships the same keys in every catalogue', function ( string $locale ): void {
    $english    = array_keys( translationCatalogue( 'en' ) );
    $translated = array_keys( translationCatalogue( $locale ) );

    expect( array_values( array_diff( $english, $translated ) ) )->toBe( [], "Missing from {$locale}.json" )
        ->and( array_values( array_diff( $translated, $english ) ) )->toBe( [], "Not in en.json but in {$locale}.json" );
} )->with( translatedLocales() );

it( 'keeps every placeholder in every translation', function ( string $locale ): void {
    $mismatches = [];

    foreach ( translationCatalogue( $locale ) as $key => $value ) {
        $expected = translationPlaceholders( $key );
        $actual   = translationPlaceholders( (string) $value );

        // A plural form may leave :count out ("one item"), so plural keys
        // only must not invent placeholders.
        $wrong = str_contains( $key, '|' )
            ? [] !== array_diff( $actual, $expected )
            : $expected !== $actual;

        if ( $wrong ) {
            $mismatches[] = $key;
        }
    }

    expect( $mismatches )->toBe( [] );
} )->with( translatedLocales() );

it( 'gives each plural key the locale\'s number of plural forms', function ( string $locale ): void {
    $forms      = PLURAL_FORMS[ $locale ] ?? 2;
    $mismatches = [];

    foreach ( translationCatalogue( $locale ) as $key => $value ) {
        if ( str_contains( $key, '|' ) && count( explode( '|', (string) $value ) ) !== $forms ) {
            $mismatches[] = $key;
        }
    }

    expect( $mismatches )->toBe( [] );
} )->with( translatedLocales() );

it( 'only ships keys the package uses', function (): void {
    $referenced = referencedTranslationKeys();
    $unused     = array_filter(
        array_keys( translationCatalogue( 'en' ) ),
        static fn ( string $key ): bool => ! isset( $referenced[ $key ] ) && ! in_array( $key, LEGACY_TRANSLATION_KEYS, true ),
    );

    expect( array_values( $unused ) )->toBe( [] );
} );

it( 'has an English entry for every key the package uses', function (): void {
    $english = translationCatalogue( 'en' );
    $missing = array_filter(
        array_keys( referencedTranslationKeys() ),
        static fn ( string $key ): bool => ! array_key_exists( $key, $english ),
    );

    expect( array_values( $missing ) )->toBe( [] );
} );
