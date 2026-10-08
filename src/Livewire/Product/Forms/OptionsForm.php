<?php

/**
 * Generic purchase form.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms;

use ArtisanPackUI\Ecommerce\Contracts\ProvidesStorefrontOptions;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-form-options :product="$product" />`
 *
 * The purchase form for a product type with no registered form whose engine
 * type implements `ProvidesStorefrontOptions` (spec §8.2). It renders the
 * type's fields — `text`, `textarea`, `number`, `quantity`, `select`,
 * `radio`, `checkbox`, and read-only `info` — validates them with each
 * field's `required` and string `rules`, and adds the product with the
 * values as cart line options (keyed by each field's `name`, dot notation).
 *
 * Fields with `meta.add_separately` (and `meta.product_id`, optionally
 * `meta.variant_id`) are quantities of other products, each added as its
 * own line, as for a grouped product.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class OptionsForm extends PurchaseForm
{
    /**
     * The field values, nested by field name.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    public array $options = [];

    /**
     * The type's fields, for this request.
     *
     * @since 1.0.0
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $fieldCache = null;

    /**
     * Fills each field's default.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        parent::mount();

        foreach ( $this->fields() as $field ) {
            if ( 'info' !== $field['type'] ) {
                data_set( $this->options, $field['name'], $field['default'] ?? ( 'checkbox' === $field['type'] ? false : null ) );
            }
        }
    }

    /**
     * Validates the fields and adds the product (or the separately added
     * products).
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addToCart(): void
    {
        $this->announcement = '';

        $fields     = $this->fields();
        $separately = array_values( array_filter( $fields, static fn ( array $field ): bool => (bool) ( $field['meta']['add_separately'] ?? false ) ) );

        if ( [] === $separately ) {
            $this->validateQuantity();
        }

        $this->validate( ...$this->validation( $fields ) );

        if ( [] === $separately ) {
            $this->addLines( [ [
                'product_id' => (int) $this->product->id,
                'variant_id' => null,
                'quantity'   => (int) $this->quantity,
                'options'    => $this->lineOptions(),
                'field'      => 'quantity',
            ] ] );

            return;
        }

        $lines = [];

        foreach ( $separately as $field ) {
            $quantity = (int) data_get( $this->options, $field['name'] );

            if ( $quantity > 0 && is_numeric( $field['meta']['product_id'] ?? null ) ) {
                $lines[] = [
                    'product_id' => (int) $field['meta']['product_id'],
                    'variant_id' => is_numeric( $field['meta']['variant_id'] ?? null ) ? (int) $field['meta']['variant_id'] : null,
                    'quantity'   => $quantity,
                    'options'    => [],
                    'field'      => 'options.' . $field['name'],
                ];
            }
        }

        if ( [] === $lines ) {
            $this->addError( 'options', __( 'Choose a quantity for at least one product.' ) );

            return;
        }

        if ( [] !== $this->addLines( $lines ) ) {
            foreach ( $separately as $field ) {
                data_set( $this->options, $field['name'], 0 );
            }
        }
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
        $fields        = $this->fields();
        $blockedReason = $this->blockedReason();

        return view( 'ecommerce-storefront::livewire.product.forms.options', [
            'fields'        => $fields,
            'separately'    => [] !== array_filter( $fields, static fn ( array $field ): bool => (bool) ( $field['meta']['add_separately'] ?? false ) ),
            'canAdd'        => null === $blockedReason,
            'blockedReason' => $blockedReason,
        ] );
    }

    /**
     * Products added separately have their own stock, so only the product's
     * price and stock block a whole-product form.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function blockedReason(): ?string
    {
        $separately = array_filter( $this->fields(), static fn ( array $field ): bool => (bool) ( $field['meta']['add_separately'] ?? false ) );

        return [] === $separately ? parent::blockedReason() : null;
    }

    /**
     * The field values to put on the cart line.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function lineOptions(): array
    {
        $values = [];

        foreach ( $this->fields() as $field ) {
            if ( 'info' === $field['type'] ) {
                continue;
            }

            $value = data_get( $this->options, $field['name'] );

            if ( null !== $value && '' !== $value ) {
                data_set( $values, $field['name'], $value );
            }
        }

        return $values;
    }

    /**
     * The rules, messages, and attribute names for the fields.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $fields  Fields.
     *
     * @return array{0: array<string, array<int, mixed>>, 1: array<string, string>, 2: array<string, string>}
     */
    protected function validation( array $fields ): array
    {
        $rules      = [];
        $attributes = [];

        foreach ( $fields as $field ) {
            if ( 'info' === $field['type'] ) {
                continue;
            }

            $key   = 'options.' . $field['name'];
            $own   = is_string( $field['rules'] ?? null ) ? explode( '|', $field['rules'] ) : (array) ( $field['rules'] ?? [] );
            $own   = array_values( array_filter( $own, static fn ( mixed $rule ): bool => is_string( $rule ) && '' !== $rule ) );
            $types = match ( $field['type'] ) {
                'number'          => [ 'numeric' ],
                'quantity'        => [ 'integer', 'min:0', 'max:' . StorefrontCartService::MAX_LINE_QUANTITY ],
                'checkbox'        => [ 'boolean' ],
                'select', 'radio' => [ Rule::in( array_column( $field['options'], 'value' ) ) ],
                default           => [ 'string', 'max:1000' ],
            };

            $rules[ $key ]      = [ ( $field['required'] ?? false ) ? ( 'checkbox' === $field['type'] ? 'accepted' : 'required' ) : 'nullable', ...$types, ...$own ];
            $attributes[ $key ] = $field['label'];
        }

        return [ $rules, [], $attributes ];
    }

    /**
     * The type's fields, normalised: a known `type`, a dot-notation `name`,
     * a `label`, and `options` as `value`/`label` pairs.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fields(): array
    {
        if ( null !== $this->fieldCache ) {
            return $this->fieldCache;
        }

        $type   = $this->product->typeIsMissing() ? null : $this->product->productType();
        $schema = $type instanceof ProvidesStorefrontOptions ? $type->storefrontOptions( $this->product ) : [];
        $fields = [];

        foreach ( $schema as $field ) {
            if ( ! is_array( $field ) || ! in_array( $field['type'] ?? null, [ 'text', 'textarea', 'number', 'quantity', 'select', 'radio', 'checkbox', 'info' ], true ) ) {
                continue;
            }

            $name = str_replace( [ '[', ']' ], [ '.', '' ], (string) ( $field['name'] ?? '' ) );

            if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/', $name ) ) {
                continue;
            }

            $options = [];

            foreach ( (array) ( $field['options'] ?? [] ) as $option ) {
                if ( is_array( $option ) && is_scalar( $option['value'] ?? null ) ) {
                    $options[] = [ 'value' => (string) $option['value'], 'label' => (string) ( $option['label'] ?? $option['value'] ) ];
                }
            }

            $fields[] = [
                'type'     => $field['type'],
                'name'     => $name,
                'id'       => 'ec-product-' . $this->product->id . '-option-' . str_replace( '.', '-', $name ),
                'label'    => (string) ( $field['label'] ?? $name ),
                'options'  => $options,
                'rules'    => $field['rules'] ?? [],
                'required' => (bool) ( $field['required'] ?? false ),
                'default'  => $field['default'] ?? null,
                'help'     => is_string( $field['help'] ?? null ) ? $field['help'] : null,
                'meta'     => is_array( $field['meta'] ?? null ) ? $field['meta'] : [],
            ];
        }

        return $this->fieldCache = $fields;
    }
}
