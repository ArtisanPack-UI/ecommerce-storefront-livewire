<?php

/**
 * Address form component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\AddressFormats;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-address-form model="shipping" :country="$shipping['country_code'] ?? null" section="shipping" :legend="__( 'Shipping address' )" />`
 *
 * Renders the address fields bound to `{model}.{field}`, with the keys the
 * engine's `Address` value object and `customer_addresses` share. Every
 * field carries its `autocomplete` token (prefixed with `section` when two
 * address forms share a page), so browsers and password managers fill it.
 *
 * Pass the selected `country`: countries with a region list get a region
 * select bound to `region_code` (others type `region`), and the postcode
 * field is labelled and hinted for that country. The country select binds
 * live, so changing it re-renders those fields. Validation errors show under
 * each field from the same keys.
 *
 * The admin ships a component with the same alias and the same base props
 * (`model`, `legend`, `live`, `with-name`), so either copy works when both
 * packages are installed.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class AddressForm extends Component
{
    /**
     * The fields, in order.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const FIELDS = [
        'first_name',
        'last_name',
        'company',
        'phone',
        'country_code',
        'address1',
        'address2',
        'city',
        'region',
        'region_code',
        'postal_code',
    ];

    /**
     * @since 1.0.0
     *
     * @param  string       $model     The Livewire property path the fields bind under.
     * @param  string|null  $legend    The fieldset legend.
     * @param  bool         $live      Bind text fields with `wire:model.live.blur` instead of deferred.
     * @param  bool         $withName  Include the name, company, and phone fields.
     * @param  string|null  $country   The selected ISO 3166-1 alpha-2 country.
     * @param  string|null  $section   The autocomplete section (`shipping`, `billing`).
     */
    public function __construct(
        public string $model,
        public ?string $legend = null,
        public bool $live = false,
        public bool $withName = true,
        public ?string $country = null,
        public ?string $section = null,
    ) {
        $this->country = '' === trim( (string) $this->country ) ? null : strtoupper( trim( (string) $this->country ) );
    }

    /**
     * The text fields before the region, with their labels, autocomplete
     * tokens, and whether they are required.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, autocomplete: string, required: bool, wide: bool}>
     */
    public function fields(): array
    {
        $fields = [
            'first_name' => [ 'label' => __( 'First name' ), 'autocomplete' => 'given-name', 'required' => false, 'wide' => false ],
            'last_name'  => [ 'label' => __( 'Last name' ), 'autocomplete' => 'family-name', 'required' => false, 'wide' => false ],
            'company'    => [ 'label' => __( 'Company' ), 'autocomplete' => 'organization', 'required' => false, 'wide' => true ],
            'phone'      => [ 'label' => __( 'Phone' ), 'autocomplete' => 'tel', 'required' => false, 'wide' => true ],
            'address1'   => [ 'label' => __( 'Address' ), 'autocomplete' => 'address-line1', 'required' => true, 'wide' => true ],
            'address2'   => [ 'label' => __( 'Apartment, suite, etc.' ), 'autocomplete' => 'address-line2', 'required' => false, 'wide' => true ],
            'city'       => [ 'label' => __( 'City' ), 'autocomplete' => 'address-level2', 'required' => true, 'wide' => false ],
        ];

        if ( ! $this->withName ) {
            unset( $fields['first_name'], $fields['last_name'], $fields['company'], $fields['phone'] );
        }

        return $fields;
    }

    /**
     * The region options for the selected country (empty: type the region).
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function regions(): array
    {
        return AddressFormats::regions( $this->country );
    }

    /**
     * The region field's label.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function regionLabel(): string
    {
        return match ( $this->country ) {
            'US'    => __( 'State' ),
            'CA'    => __( 'Province or territory' ),
            'AU'    => __( 'State or territory' ),
            default => __( 'State / region' ),
        };
    }

    /**
     * The postcode field's label for the selected country.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function postcodeLabel(): string
    {
        return AddressFormats::postcodeLabel( $this->country );
    }

    /**
     * The postcode hint for the selected country.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function postcodeHint(): ?string
    {
        return AddressFormats::postcodeHint( $this->country );
    }

    /**
     * An autocomplete token in this form's section.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The token, e.g. `postal-code`.
     *
     * @return string
     */
    public function autocomplete( string $token ): string
    {
        return null === $this->section || '' === $this->section ? $token : $this->section . ' ' . $token;
    }

    /**
     * The country select options.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function countries(): array
    {
        return Countries::options();
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-storefront::components.address-form' );
    }
}
