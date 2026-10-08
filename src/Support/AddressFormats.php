<?php

/**
 * Per-country address formats.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

/**
 * Region lists and postcode hints for the address form (spec §8.1).
 *
 * Countries listed in {@see self::REGIONS} get a region select bound to
 * `region_code` (the ISO 3166-2 suffix tax and shipping zones match on);
 * every other country gets a free-text `region` field. Postcode labels and
 * example formats help shoppers type a code the carrier accepts. Both lists
 * run through filters so a store can add countries:
 * `ap.ecommerceStorefrontLivewire.address.regions` and
 * `ap.ecommerceStorefrontLivewire.address.postcodeExamples`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class AddressFormats
{
    /**
     * Subdivisions by country, as ISO 3166-2 suffix => name. Names are the
     * official ones and are not translated.
     *
     * @since 1.0.0
     *
     * @var array<string, array<string, string>>
     */
    public const REGIONS = [
        'US' => [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
            'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia',
            'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois',
            'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana',
            'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
            'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
            'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
            'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon',
            'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
            'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia',
            'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
            'AS' => 'American Samoa', 'GU' => 'Guam', 'MP' => 'Northern Mariana Islands', 'PR' => 'Puerto Rico',
            'VI' => 'U.S. Virgin Islands', 'AA' => 'Armed Forces Americas', 'AE' => 'Armed Forces Europe',
            'AP' => 'Armed Forces Pacific',
        ],
        'CA' => [
            'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick',
            'NL' => 'Newfoundland and Labrador', 'NT' => 'Northwest Territories', 'NS' => 'Nova Scotia',
            'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Quebec',
            'SK' => 'Saskatchewan', 'YT' => 'Yukon',
        ],
        'AU' => [
            'ACT' => 'Australian Capital Territory', 'NSW' => 'New South Wales', 'NT' => 'Northern Territory',
            'QLD' => 'Queensland', 'SA' => 'South Australia', 'TAS' => 'Tasmania', 'VIC' => 'Victoria',
            'WA'  => 'Western Australia',
        ],
    ];

    /**
     * Example postcodes by country.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const POSTCODE_EXAMPLES = [
        'AU' => '2000',
        'AT' => '1010',
        'BE' => '1000',
        'BR' => '01310-100',
        'CA' => 'K1A 0B1',
        'CH' => '8001',
        'DE' => '10115',
        'DK' => '1050',
        'ES' => '28001',
        'FI' => '00100',
        'FR' => '75001',
        'GB' => 'SW1A 1AA',
        'IE' => 'D02 X285',
        'IN' => '110001',
        'IT' => '00118',
        'JP' => '100-0001',
        'MX' => '06000',
        'NL' => '1012 AB',
        'NO' => '0150',
        'NZ' => '6011',
        'PL' => '00-001',
        'PT' => '1100-148',
        'SE' => '111 22',
        'US' => '12345',
    ];

    /**
     * Postcode formats (PCRE, case-insensitive) for the countries the
     * checkout validates, keyed by country code. Filterable with
     * `ap.ecommerceStorefrontLivewire.address.postcodePatterns`; a country
     * without a pattern takes any postcode, or none.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const POSTCODE_PATTERNS = [
        'AT' => '/^\d{4}$/',
        'AU' => '/^\d{4}$/',
        'BE' => '/^\d{4}$/',
        'BR' => '/^\d{5}-?\d{3}$/',
        'CA' => '/^[A-Z]\d[A-Z][ -]?\d[A-Z]\d$/i',
        'CH' => '/^\d{4}$/',
        'DE' => '/^\d{5}$/',
        'DK' => '/^\d{4}$/',
        'ES' => '/^\d{5}$/',
        'FI' => '/^\d{5}$/',
        'FR' => '/^\d{5}$/',
        'GB' => '/^[A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}$/i',
        'IE' => '/^[A-Z]\d[\dW] ?[A-Z\d]{4}$/i',
        'IN' => '/^\d{6}$/',
        'IT' => '/^\d{5}$/',
        'JP' => '/^\d{3}-?\d{4}$/',
        'MX' => '/^\d{5}$/',
        'NL' => '/^\d{4} ?[A-Z]{2}$/i',
        'NO' => '/^\d{4}$/',
        'NZ' => '/^\d{4}$/',
        'PL' => '/^\d{2}-\d{3}$/',
        'PT' => '/^\d{4}-\d{3}$/',
        'SE' => '/^\d{3} ?\d{2}$/',
        'US' => '/^\d{5}(-\d{4})?$/',
    ];

    /**
     * Countries that call it a ZIP code or a postcode rather than a postal
     * code.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    private const POSTCODE_TERMS = [
        'US' => 'zip',
        'PH' => 'zip',
        'AU' => 'postcode',
        'GB' => 'postcode',
        'IE' => 'postcode',
        'NZ' => 'postcode',
        'ZA' => 'postcode',
    ];

    /**
     * The region options for `$country`, or an empty list when the region
     * is typed freely.
     *
     * @since 1.0.0
     *
     * @param  string|null  $country  ISO 3166-1 alpha-2 code.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function regions( ?string $country ): array
    {
        $country = strtoupper( trim( (string) $country ) );

        if ( '' === $country ) {
            return [];
        }

        $all     = (array) applyFilters( 'ap.ecommerceStorefrontLivewire.address.regions', self::REGIONS );
        $regions = is_array( $all[ $country ] ?? null ) ? $all[ $country ] : [];
        $options = [];

        foreach ( $regions as $code => $name ) {
            if ( is_string( $name ) && '' !== $name ) {
                $options[] = [ 'id' => (string) $code, 'name' => $name ];
            }
        }

        return $options;
    }

    /**
     * The name of a region code, or the code itself when it is unknown.
     *
     * @since 1.0.0
     *
     * @param  string|null  $country  ISO 3166-1 alpha-2 code.
     * @param  string       $code     Region code.
     *
     * @return string
     */
    public static function regionName( ?string $country, string $code ): string
    {
        foreach ( self::regions( $country ) as $region ) {
            if ( strtoupper( $code ) === strtoupper( $region['id'] ) ) {
                return $region['name'];
            }
        }

        return $code;
    }

    /**
     * The label of the postcode field for `$country`.
     *
     * @since 1.0.0
     *
     * @param  string|null  $country  ISO 3166-1 alpha-2 code.
     *
     * @return string
     */
    public static function postcodeLabel( ?string $country ): string
    {
        return match ( self::POSTCODE_TERMS[ strtoupper( (string) $country ) ] ?? null ) {
            'zip'      => __( 'ZIP code' ),
            'postcode' => __( 'Postcode' ),
            default    => __( 'Postal code' ),
        };
    }

    /**
     * The hint under the postcode field, or null when there is no example.
     *
     * @since 1.0.0
     *
     * @param  string|null  $country  ISO 3166-1 alpha-2 code.
     *
     * @return string|null
     */
    public static function postcodeHint( ?string $country ): ?string
    {
        $examples = (array) applyFilters( 'ap.ecommerceStorefrontLivewire.address.postcodeExamples', self::POSTCODE_EXAMPLES );
        $example  = $examples[ strtoupper( (string) $country ) ] ?? null;

        return is_string( $example ) && '' !== $example ? __( 'For example: :example', [ 'example' => $example ] ) : null;
    }

    /**
     * The postcode format for a country, or null when any postcode (or
     * none) is accepted.
     *
     * @since 1.0.0
     *
     * @param  string|null  $country  ISO 3166-1 alpha-2 code.
     *
     * @return string|null A PCRE pattern.
     */
    public static function postcodePattern( ?string $country ): ?string
    {
        $patterns = (array) applyFilters( 'ap.ecommerceStorefrontLivewire.address.postcodePatterns', self::POSTCODE_PATTERNS );
        $pattern  = $patterns[ strtoupper( trim( (string) $country ) ) ] ?? null;

        return is_string( $pattern ) && '' !== $pattern && false !== @preg_match( $pattern, '' ) ? $pattern : null;
    }

    /**
     * Whether `$postcode` is in the country's format (always true for a
     * country without one).
     *
     * @since 1.0.0
     *
     * @param  string|null  $country   ISO 3166-1 alpha-2 code.
     * @param  string       $postcode  Postcode.
     *
     * @return bool
     */
    public static function postcodeIsValid( ?string $country, string $postcode ): bool
    {
        $pattern = self::postcodePattern( $country );

        return null === $pattern || 1 === preg_match( $pattern, trim( $postcode ) );
    }
}
