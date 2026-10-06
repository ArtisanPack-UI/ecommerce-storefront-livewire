<?php

declare( strict_types=1 );

/**
 * The package's decoded catalogue for `$locale`.
 *
 * @return array<string, string>
 */
function storefrontCatalogue( string $locale ): array
{
    return json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . "/lang/{$locale}.json" ), true, flags: JSON_THROW_ON_ERROR );
}

/**
 * The sorted, unique `:placeholders` in `$value`.
 *
 * @return array<int, string>
 */
function storefrontPlaceholders( string $value ): array
{
    preg_match_all( '/:[A-Za-z_]+/', $value, $matches );

    return collect( $matches[0] )->unique()->sort()->values()->all();
}

it( 'has a catalogue entry in every locale for every key in src/ and resources/', function (): void {
    $root = dirname( __DIR__, 3 );

    $this->artisan( 'ecommerce:lint:translations', [
        '--no-engine' => true,
        '--path'      => [ $root . '/src', $root . '/resources' ],
        '--lang'      => $root . '/lang',
    ] )->expectsOutputToContain( 'Translation lint passed.' )->assertExitCode( 0 );
} );

it( 'ships identical key sets in every locale', function (): void {
    $en = array_keys( storefrontCatalogue( 'en' ) );
    sort( $en );

    foreach ( [ 'es', 'fr', 'de' ] as $locale ) {
        $keys = array_keys( storefrontCatalogue( $locale ) );
        sort( $keys );

        expect( $keys )->toBe( $en, $locale );
    }
} );

it( 'keeps English as its own translation', function (): void {
    foreach ( storefrontCatalogue( 'en' ) as $key => $value ) {
        expect( $value )->toBe( $key );
    }
} );

it( 'preserves placeholders and plural segments in every translation', function ( string $locale ): void {
    foreach ( storefrontCatalogue( $locale ) as $key => $value ) {
        expect( trim( $value ) )->not->toBe( '', "{$locale}: {$key}" )
            ->and( storefrontPlaceholders( $value ) )->toBe( storefrontPlaceholders( $key ), "{$locale}: {$key}" )
            ->and( substr_count( $value, '|' ) )->toBe( substr_count( $key, '|' ), "{$locale}: {$key}" );
    }
} )->with( [ 'es', 'fr', 'de' ] );

it( 'pluralizes for 0, 1, and many in each locale', function ( string $locale, array $expected ): void {
    app()->setLocale( $locale );

    $line = ':count product|:count products';

    expect( [
        trans_choice( $line, 0, [ 'count' => 0 ] ),
        trans_choice( $line, 1, [ 'count' => 1 ] ),
        trans_choice( $line, 5, [ 'count' => 5 ] ),
    ] )->toBe( $expected );
} )->with( [
    'en' => [ 'en', [ '0 products', '1 product', '5 products' ] ],
    'es' => [ 'es', [ '0 productos', '1 producto', '5 productos' ] ],
    'fr' => [ 'fr', [ '0 produit', '1 produit', '5 produits' ] ],
    'de' => [ 'de', [ '0 Produkte', '1 Produkt', '5 Produkte' ] ],
] );

it( 'agrees with the engine and the admin on every key they share', function ( string $locale ): void {
    // Every package's JSON catalogue loads into one namespace, so a shared key
    // must read the same everywhere or the winner depends on load order.
    $engine = dirname( ( new ReflectionClass( ArtisanPackUI\Ecommerce\Providers\EcommerceServiceProvider::class ) )->getFileName(), 3 ) . "/lang/{$locale}.json";
    $theirs = json_decode( (string) file_get_contents( $engine ), true, flags: JSON_THROW_ON_ERROR );

    foreach ( storefrontCatalogue( $locale ) as $key => $value ) {
        if ( array_key_exists( $key, $theirs ) ) {
            expect( $value )->toBe( $theirs[ $key ], "{$locale}: {$key}" );
        }
    }
} )->with( [ 'es', 'fr', 'de' ] );
