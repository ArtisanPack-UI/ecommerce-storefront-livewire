<?php

/**
 * Address editing concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\ValueObjects\Address;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\AddressFormats;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\Countries;
use ArtisanPackUI\EcommerceStorefrontLivewire\View\Components\AddressForm;

/**
 * Validates and converts the fields of an `<x-artisanpack-ec-address-form>`
 * (spec §8.1), so the checkout and the address book accept the same
 * addresses: a known country, a name, a street and city, the country's
 * region when it has a list, and a postcode in the country's format.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
trait EditsAddresses
{
    /**
     * The address errors for a form, keyed by field (`shipping.city`).
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $address  The form's fields.
     * @param  string                $model    The property the form binds under (`shipping`, `billing`).
     *
     * @return array<string, string>
     */
    protected function addressErrors( array $address, string $model ): array
    {
        $value   = static fn ( string $field ): string => trim( (string) ( $address[ $field ] ?? '' ) );
        $country = strtoupper( $value( 'country_code' ) );
        $regions = AddressFormats::regions( $country );
        $errors  = [];

        if ( ! in_array( $country, Countries::CODES, true ) ) {
            $errors[ $model . '.country_code' ] = __( 'Choose a country.' );
        }

        foreach ( [ 'first_name' => __( 'Enter the first name.' ), 'last_name' => __( 'Enter the last name.' ), 'address1' => __( 'Enter the street address.' ), 'city' => __( 'Enter the city.' ) ] as $field => $message ) {
            if ( '' === $value( $field ) ) {
                $errors[ $model . '.' . $field ] = $message;
            }
        }

        foreach ( AddressForm::FIELDS as $field ) {
            if ( mb_strlen( $value( $field ) ) > 255 ) {
                $errors[ $model . '.' . $field ] = __( 'Keep this under 255 characters.' );
            }
        }

        if ( [] !== $regions && ! in_array( $value( 'region_code' ), array_column( $regions, 'id' ), true ) ) {
            $errors[ $model . '.region_code' ] = __( 'Choose a state or region.' );
        }

        $postcode = $value( 'postal_code' );
        $label    = AddressFormats::postcodeLabel( $country );

        if ( null !== AddressFormats::postcodePattern( $country ) ) {
            if ( '' === $postcode ) {
                $errors[ $model . '.postal_code' ] = __( 'Enter the :label.', [ 'label' => $label ] );
            } elseif ( ! AddressFormats::postcodeIsValid( $country, $postcode ) ) {
                $errors[ $model . '.postal_code' ] = (string) ( AddressFormats::postcodeHint( $country ) ?? __( 'Check the :label.', [ 'label' => $label ] ) );
            }
        }

        return $errors;
    }

    /**
     * A form's fields as the engine's address.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $address  The form's fields.
     *
     * @return Address
     */
    protected function toAddress( array $address ): Address
    {
        $fields  = [];
        $country = strtoupper( trim( (string) ( $address['country_code'] ?? '' ) ) );

        foreach ( AddressForm::FIELDS as $field ) {
            $value            = trim( (string) ( $address[ $field ] ?? '' ) );
            $fields[ $field ] = '' === $value ? null : $value;
        }

        $fields['country_code'] = $country;

        if ( [] !== AddressFormats::regions( $country ) && null !== $fields['region_code'] ) {
            $fields['region'] = AddressFormats::regionName( $country, $fields['region_code'] );
        } elseif ( [] === AddressFormats::regions( $country ) ) {
            $fields['region_code'] = null;
        }

        return Address::fromArray( $fields );
    }

    /**
     * An empty form, in the store's country.
     *
     * @since 1.0.0
     *
     * @return array<string, string|null>
     */
    protected function blankAddress(): array
    {
        $country = strtoupper( (string) config( 'artisanpack.ecommerce.store.country', 'US' ) );

        return array_merge( array_fill_keys( AddressForm::FIELDS, '' ), [ 'country_code' => in_array( $country, Countries::CODES, true ) ? $country : '' ] );
    }

    /**
     * A stored address as the form's fields.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $address  Stored address.
     *
     * @return array<string, string|null>
     */
    protected function formAddress( array $address ): array
    {
        $form = [];

        foreach ( AddressForm::FIELDS as $field ) {
            $form[ $field ] = is_scalar( $address[ $field ] ?? null ) ? (string) $address[ $field ] : '';
        }

        return $form;
    }

    /**
     * After an edit: a new country clears the region.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $address  The form's fields.
     * @param  string|null           $field    The changed field.
     *
     * @return array<string, mixed>
     */
    protected function addressChanged( array $address, ?string $field ): array
    {
        if ( 'country_code' === $field ) {
            $address['region_code'] = '';
            $address['region']      = '';
        }

        return $address;
    }
}
