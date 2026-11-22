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
use ArtisanPackUI\Ecommerce\Services\PriceQuoteService;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\Ecommerce\ValueObjects\PriceQuote;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Money\Money;
use Throwable;

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
 * Since 2.0.0 a text field with a `max:N` rule shows a live "12 of 20
 * characters" count under it.
 *
 * Fields with `meta.add_separately` (and `meta.product_id`, optionally
 * `meta.variant_id`) are quantities of other products, each added as its
 * own line, as for a grouped product.
 *
 * Since 2.0.0 it renders the richer engine schema (engine 1.4): a `swatch`
 * field (color or image swatches), card-style radios (`meta.display =
 * cards`), option `image`, `swatch`, `hint`, `price_delta` ("+$5.00") and
 * `disabled`, `meta.disables` (choosing a value disables conflicting
 * choices in other fields), and `visible_when` (a field shows only while
 * other fields hold given values; hidden fields aren't validated or added).
 * A form that uses any of these re-renders when a choice changes. With the
 * engine's `PriceQuoteService`, the price of the chosen options is quoted
 * live, shown as "$40.00 including options" and on the "Add to cart"
 * button. The engine stays authoritative: it validates and prices the line
 * again when it is added.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class OptionsForm extends PurchaseForm
{
    /**
     * The longest value any text or textarea field accepts.
     *
     * @since 2.0.0
     *
     * @var int
     */
    public const TEXT_MAX_LENGTH = 1000;

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
     * The quote for the chosen options, memoized for the current request.
     *
     * @since 2.0.0
     *
     * @var PriceQuote|null
     */
    protected ?PriceQuote $quoteCache = null;

    /**
     * Whether {@see self::$quoteCache} has been resolved this request.
     *
     * @since 2.0.0
     *
     * @var bool
     */
    protected bool $quoteResolved = false;

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
     * Clears a choice that another choice has just made unavailable
     * (`meta.disables`), so the form never holds a conflicting value.
     *
     * @since 2.0.0
     *
     * @return void
     */
    public function updatedOptions(): void
    {
        foreach ( $this->visibleFields() as $field ) {
            $value = data_get( $this->options, $field['name'] );

            if ( null === $value || '' === $value || [] === $field['options'] ) {
                continue;
            }

            $option = collect( $field['options'] )->firstWhere( 'value', (string) $value );

            if ( null !== $option && $option['disabled'] ) {
                data_set( $this->options, $field['name'], null );
            }
        }

        $this->quoteResolved     = false;
        $this->unitPriceResolved = false;
    }

    /**
     * Validates the fields and adds the product (or the separately added
     * products).
     *
     * @since 1.0.0
     * @since 2.0.0 Hidden fields (`visible_when`) are skipped.
     *
     * @return void
     */
    public function addToCart(): void
    {
        $this->startAdding();

        $fields     = $this->visibleFields();
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
            // Keep the quantities the engine refused, next to their errors.
            foreach ( $separately as $field ) {
                if ( ! $this->getErrorBag()->has( 'options.' . $field['name'] ) ) {
                    data_set( $this->options, $field['name'], 0 );
                }
            }
        }
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     * @since 2.0.0 Passes only the visible fields, whether choices re-render
     *              the form (`reactive`), and the live price (`showsQuote`,
     *              `quotedPrice`).
     *
     * @return View
     */
    public function render(): View
    {
        $fields        = $this->visibleFields();
        $blockedReason = $this->blockedReason();
        $canAdd        = null === $blockedReason;
        $quote         = $canAdd ? $this->quote() : null;

        return view( 'ecommerce-storefront::livewire.product.forms.options', [
            'fields'        => $fields,
            'separately'    => [] !== array_filter( $fields, static fn ( array $field ): bool => (bool) ( $field['meta']['add_separately'] ?? false ) ),
            'canAdd'        => $canAdd,
            'blockedReason' => $blockedReason,
            'reactive'      => $this->reactive(),
            'showsQuote'    => $canAdd && $this->quotesPrices() && $this->hasPricedChoices(),
            'quotedPrice'   => null === $quote ? null : MoneyFormatter::formatMoney( $quote->unitPrice() ),
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
     * The quoted unit price of the chosen options, so "Add to cart" shows
     * the line price. Null without the engine's quotes, or while the
     * choices aren't complete and valid.
     *
     * @since 2.0.0
     *
     * @return Money|null
     */
    protected function unitPrice(): ?Money
    {
        return $this->quote()?->unitPrice();
    }

    /**
     * The engine's quote for one unit with the chosen options, once per
     * request; null when quotes aren't available, the product is added as
     * separate products, or the choices don't validate yet.
     *
     * @since 2.0.0
     *
     * @return PriceQuote|null
     */
    protected function quote(): ?PriceQuote
    {
        if ( $this->quoteResolved ) {
            return $this->quoteCache;
        }

        $this->quoteResolved = true;
        $this->quoteCache    = null;

        if ( ! $this->quotesPrices() ) {
            return null;
        }

        try {
            $quote = app( PriceQuoteService::class )->quote( $this->product, $this->lineOptions(), 1, app( StorefrontCart::class )->currency() );
        } catch ( Throwable $exception ) {
            report( $exception );

            return null;
        }

        return $this->quoteCache = $quote->isValid() ? $quote : null;
    }

    /**
     * Whether the engine can quote this form's product: it has the quote
     * service and the product is added as one line.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    protected function quotesPrices(): bool
    {
        return class_exists( PriceQuoteService::class )
            && [] === array_filter( $this->fields(), static fn ( array $field ): bool => (bool) ( $field['meta']['add_separately'] ?? false ) );
    }

    /**
     * Whether any choice carries a price difference, so the form shows the
     * quoted "$40.00 including options".
     *
     * @since 2.0.0
     *
     * @return bool
     */
    protected function hasPricedChoices(): bool
    {
        foreach ( $this->fields() as $field ) {
            foreach ( $field['options'] as $option ) {
                if ( null !== $option['delta'] ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a choice changes the form (shows or hides a field, disables
     * other choices, or changes the quoted price), so the form re-renders
     * when one changes.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    protected function reactive(): bool
    {
        if ( $this->quotesPrices() ) {
            return true;
        }

        foreach ( $this->fields() as $field ) {
            if ( [] !== $field['visibleWhen'] || [] !== $field['disables'] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The field values to put on the cart line: the visible fields only.
     *
     * @since 1.0.0
     * @since 2.0.0 Hidden fields (`visible_when`) are left out.
     *
     * @return array<string, mixed>
     */
    protected function lineOptions(): array
    {
        $values = [];

        foreach ( $this->visibleFields() as $field ) {
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
     * @since 2.0.0 A `swatch` field is a choice; disabled choices are refused.
     *
     * @param  array<int, array<string, mixed>>  $fields  Fields.
     *
     * @return array{0: array<string, array<int, mixed>>, 1: array<string, string>, 2: array<string, string>}
     */
    protected function validation( array $fields ): array
    {
        $rules      = [];
        $messages   = [];
        $attributes = [];

        foreach ( $fields as $field ) {
            if ( 'info' === $field['type'] ) {
                continue;
            }

            $key     = 'options.' . $field['name'];
            $own     = is_string( $field['rules'] ?? null ) ? explode( '|', $field['rules'] ) : (array) ( $field['rules'] ?? [] );
            $own     = array_values( array_filter( $own, static fn ( mixed $rule ): bool => is_string( $rule ) && '' !== $rule ) );
            $enabled = array_column( array_filter( $field['options'], static fn ( array $option ): bool => ! $option['disabled'] ), 'value' );
            $types   = match ( $field['type'] ) {
                'number'                    => [ 'numeric' ],
                'quantity'                  => [ 'integer', 'min:0', 'max:' . StorefrontCartService::MAX_LINE_QUANTITY ],
                'checkbox'                  => [ 'boolean' ],
                'select', 'radio', 'swatch' => [ Rule::in( $enabled ) ],
                default                     => [ 'string', 'max:' . self::TEXT_MAX_LENGTH ],
            };

            $rules[ $key ]      = [ ( $field['required'] ?? false ) ? ( 'checkbox' === $field['type'] ? 'accepted' : 'required' ) : 'nullable', ...$types, ...$own ];
            $attributes[ $key ] = $field['label'];

            if ( in_array( $field['type'], [ 'select', 'radio', 'swatch' ], true ) ) {
                $messages[ $key . '.in' ] = __( 'Choose an available :attribute.', [ 'attribute' => $field['label'] ] );
            }
        }

        return [ $rules, $messages, $attributes ];
    }

    /**
     * The fields shown for the current values: those without a
     * `visible_when`, or whose conditions all hold. Each choice another
     * field's value disables (`meta.disables`) is marked `disabled`, with
     * a `reason`.
     *
     * @since 2.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function visibleFields(): array
    {
        $fields  = $this->fields();
        $visible = array_values( array_filter( $fields, fn ( array $field ): bool => $this->conditionsHold( $field ) ) );
        $blocked = [];

        foreach ( $visible as $field ) {
            $value = self::conditionValue( data_get( $this->options, $field['name'] ) );

            if ( null === $value || ! isset( $field['disables'][ $value ] ) ) {
                continue;
            }

            $chosen = collect( $field['options'] )->firstWhere( 'value', $value )['label'] ?? ( 'checkbox' === $field['type'] ? $field['label'] : $value );

            foreach ( $field['disables'][ $value ] as $target => $values ) {
                foreach ( $values as $disabled ) {
                    $blocked[ $target ][ $disabled ] ??= __( 'Not available with :choice', [ 'choice' => $chosen ] );
                }
            }
        }

        foreach ( $visible as $index => $field ) {
            foreach ( $field['options'] as $optionIndex => $option ) {
                $reason = $blocked[ $field['name'] ][ $option['value'] ] ?? null;

                if ( null !== $reason && ! $option['disabled'] ) {
                    $visible[ $index ]['options'][ $optionIndex ]['disabled'] = true;
                    $visible[ $index ]['options'][ $optionIndex ]['reason']   = $reason;
                }
            }
        }

        return $visible;
    }

    /**
     * Whether every `visible_when` condition of a field holds for the
     * current values (a checkbox's value compares as `1` / `0`, also
     * matching `true` / `false`).
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $field  Field.
     *
     * @return bool
     */
    protected function conditionsHold( array $field ): bool
    {
        foreach ( $field['visibleWhen'] as $condition ) {
            $value = self::conditionValue( data_get( $this->options, $condition['field'] ) );
            $value = match ( $value ) {
                null    => [],
                '1'     => [ '1', 'true' ],
                '0'     => [ '0', 'false' ],
                default => [ $value ],
            };

            if ( [] === array_intersect( $value, $condition['in'] ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * A field's value as the string the schema keys conditions and
     * `meta.disables` by: a checkbox is `1` / `0`, other scalars their
     * string. Null for no answer (null, an empty string, or a list).
     *
     * @since 2.0.0
     *
     * @param  mixed  $value  The value.
     *
     * @return string|null
     */
    protected static function conditionValue( mixed $value ): ?string
    {
        return match ( true ) {
            true === $value                               => '1',
            false === $value                              => '0',
            is_scalar( $value ) && '' !== (string) $value => (string) $value,
            default                                       => null,
        };
    }

    /**
     * The type's fields, normalised: a known `type`, a dot-notation `name`,
     * a `label`, and `options` (`value`, `label`, `hint`, `delta` — the
     * formatted price difference, `image`, `swatch`, `disabled`,
     * `reason`), plus `visibleWhen`, `disables` and `cards`. Malformed
     * fields, and the parts of a field that are malformed, are dropped.
     *
     * @since 1.0.0
     * @since 2.0.0 Reads the richer schema: `swatch`, option metadata,
     *              `visible_when`, `meta.disables` and `meta.display`.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fields(): array
    {
        if ( null !== $this->fieldCache ) {
            return $this->fieldCache;
        }

        $type     = $this->product->typeIsMissing() ? null : $this->product->productType();
        $schema   = $type instanceof ProvidesStorefrontOptions ? $type->storefrontOptions( $this->product ) : [];
        $currency = app( StorefrontCart::class )->currency();
        $fields   = [];

        foreach ( $schema as $field ) {
            if ( ! is_array( $field ) || ! in_array( $field['type'] ?? null, [ 'text', 'textarea', 'number', 'quantity', 'select', 'radio', 'swatch', 'checkbox', 'info' ], true ) ) {
                continue;
            }

            $name = self::fieldName( $field['name'] ?? null );

            if ( null === $name ) {
                continue;
            }

            $options = [];

            foreach ( (array) ( $field['options'] ?? [] ) as $option ) {
                if ( is_array( $option ) && is_scalar( $option['value'] ?? null ) ) {
                    $options[] = self::normalizedOption( $option, $currency );
                }
            }

            $meta = is_array( $field['meta'] ?? null ) ? $field['meta'] : [];

            $fields[] = [
                'type'        => $field['type'],
                'name'        => $name,
                'id'          => 'ec-product-' . $this->product->id . '-option-' . str_replace( '.', '-', $name ),
                'label'       => (string) ( $field['label'] ?? $name ),
                'options'     => $options,
                'rules'       => $field['rules'] ?? [],
                'required'    => (bool) ( $field['required'] ?? false ),
                'default'     => $field['default'] ?? null,
                'help'        => is_string( $field['help'] ?? null ) ? $field['help'] : null,
                'meta'        => $meta,
                'maxLength'   => in_array( $field['type'], [ 'text', 'textarea' ], true ) ? self::maxLength( $field['rules'] ?? [] ) : null,
                'visibleWhen' => self::conditions( $field['visible_when'] ?? null ),
                'disables'    => self::disables( $meta['disables'] ?? null ),
                'cards'       => 'radio' === $field['type'] && 'cards' === ( $meta['display'] ?? null ),
            ];
        }

        return $this->fieldCache = $fields;
    }

    /**
     * A field name in dot notation (bracket notation converted), or null
     * when it isn't a valid name.
     *
     * @since 2.0.0
     *
     * @param  mixed  $name  The schema's name.
     *
     * @return string|null
     */
    protected static function fieldName( mixed $name ): ?string
    {
        $name = str_replace( [ '[', ']' ], [ '.', '' ], is_scalar( $name ) ? (string) $name : '' );

        return 1 === preg_match( '/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/', $name ) ? $name : null;
    }

    /**
     * One choice, normalised. A `price_delta` shows only in the shopper's
     * currency ("+$5.00", "−$2.00"); an image or swatch image only with a
     * safe URL; a swatch color only as a hex color.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $option    The schema's option.
     * @param  string                $currency  The shopper's currency.
     *
     * @return array{value: string, label: string, hint: string|null, delta: string|null, image: array{url: string, srcset: string|null, alt: string}|null, swatch: string|null, disabled: bool, reason: string|null}
     */
    protected static function normalizedOption( array $option, string $currency ): array
    {
        $delta = null;

        if ( is_array( $option['price_delta'] ?? null ) && is_numeric( $option['price_delta']['amount'] ?? null ) && $currency === ( $option['price_delta']['currency'] ?? null ) && 0 !== (int) $option['price_delta']['amount'] ) {
            $amount = (int) $option['price_delta']['amount'];
            $money  = MoneyFormatter::format( abs( $amount ), $currency );
            $delta  = $amount > 0 ? __( '+:amount', [ 'amount' => $money ] ) : __( '−:amount', [ 'amount' => $money ] );
        }

        $image = null;

        if ( is_array( $option['image'] ?? null ) && is_string( $option['image']['url'] ?? null ) ) {
            $url = ProductImages::safeUrl( $option['image']['url'] );

            if ( null !== $url ) {
                $image = [
                    'url'    => $url,
                    'srcset' => ProductImages::safeSrcset( $option['image']['srcset'] ?? null ),
                    'alt'    => is_string( $option['image']['alt'] ?? null ) ? $option['image']['alt'] : '',
                ];
            }
        }

        $swatch = null;

        if ( is_array( $option['swatch'] ?? null ) ) {
            $hex    = is_string( $option['swatch']['hex'] ?? null ) && 1 === preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $option['swatch']['hex'] ) ? $option['swatch']['hex'] : null;
            $swatch = $hex ?? ( is_string( $option['swatch']['image_url'] ?? null ) ? ProductImages::safeUrl( $option['swatch']['image_url'] ) : null );
        }

        return [
            'value'    => (string) $option['value'],
            'label'    => (string) ( $option['label'] ?? $option['value'] ),
            'hint'     => is_string( $option['hint'] ?? null ) && '' !== trim( $option['hint'] ) ? $option['hint'] : null,
            'delta'    => $delta,
            'image'    => $image,
            'swatch'   => $swatch,
            'disabled' => true === ( $option['disabled'] ?? false ),
            'reason'   => null,
        ];
    }

    /**
     * A field's `visible_when` conditions: each a `field` name and the
     * values (`in`, as strings) it must hold. Malformed conditions are
     * dropped.
     *
     * @since 2.0.0
     *
     * @param  mixed  $conditions  The schema's conditions.
     *
     * @return array<int, array{field: string, in: array<int, string>}>
     */
    protected static function conditions( mixed $conditions ): array
    {
        $normalized = [];

        foreach ( is_array( $conditions ) ? $conditions : [] as $condition ) {
            $field = is_array( $condition ) ? self::fieldName( $condition['field'] ?? null ) : null;

            if ( null === $field || ! is_array( $condition['in'] ?? null ) ) {
                continue;
            }

            $values = array_map(
                static fn ( mixed $value ): string => is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value,
                array_values( array_filter( $condition['in'], 'is_scalar' ) ),
            );

            $normalized[] = [ 'field' => $field, 'in' => $values ];
        }

        return $normalized;
    }

    /**
     * A field's `meta.disables`: for each of its values, the values of
     * other fields (by dot-notation name) that choosing it disables.
     *
     * @since 2.0.0
     *
     * @param  mixed  $disables  The schema's map.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    protected static function disables( mixed $disables ): array
    {
        $normalized = [];

        foreach ( is_array( $disables ) ? $disables : [] as $value => $targets ) {
            foreach ( is_array( $targets ) ? $targets : [] as $target => $values ) {
                $target = self::fieldName( $target );

                if ( null !== $target && is_array( $values ) ) {
                    $normalized[ (string) $value ][ $target ] = array_map( 'strval', array_values( array_filter( $values, 'is_scalar' ) ) );
                }
            }
        }

        return $normalized;
    }

    /**
     * The longest value a text field accepts when it declares a `max:N`
     * rule: the smallest declared limit, capped at the
     * {@see self::TEXT_MAX_LENGTH} every text field is validated against.
     * Null without a declared limit.
     *
     * @since 2.0.0
     *
     * @param  mixed  $rules  The field's rules (a pipe string or a list).
     *
     * @return int|null
     */
    protected static function maxLength( mixed $rules ): ?int
    {
        $rules = is_string( $rules ) ? explode( '|', $rules ) : (array) $rules;
        $max   = null;

        foreach ( $rules as $rule ) {
            if ( is_string( $rule ) && 1 === preg_match( '/^max:(\d+)$/', trim( $rule ), $matches ) ) {
                $max = null === $max ? (int) $matches[1] : min( $max, (int) $matches[1] );
            }
        }

        return null !== $max && $max > 0 ? min( $max, self::TEXT_MAX_LENGTH ) : null;
    }
}
