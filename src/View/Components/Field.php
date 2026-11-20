<?php

/**
 * Form field component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * `<x-artisanpack-ec-field id="email" type="email" :label="__( 'Email' )" :hint="__( 'For your receipt.' )" wire:model="email" />`
 *
 * A component-library input, select, textarea, password, or checkbox
 * (`control`) whose error and hint are linked to it. Since 2.0.0 the
 * library control does this itself: it prints the label, the hint and the
 * error with ids, points `aria-describedby` at them (after any the caller
 * passes), and sets `aria-invalid="true"` when the field's key has an
 * error. The wrapper carries `data-field-key` for the error summary.
 *
 * The error key is `error-field`, or else the `wire:model` property.
 * Every other attribute (`wire:model`, `type`, `required`, `autocomplete`,
 * `data-*`) is passed to the library control; the select's options are
 * props here because the library takes them as props, and are mapped to
 * the library's `value` / `label` shape. A checkbox shows its hint as its
 * description.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Field extends Component
{
    /**
     * The controls this component wraps.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const CONTROLS = [ 'input', 'select', 'textarea', 'password', 'checkbox' ];

    /**
     * @since 1.0.0
     *
     * @param  string                         $id                The id passed to the library control, which bases its error and hint ids on it.
     * @param  string                         $control           The library control: input, select, textarea, password, or checkbox.
     * @param  string|null                    $label             The visible label.
     * @param  string|null                    $hint              Help text shown under the control.
     * @param  string|null                    $errorField        The error bag key (defaults to the `wire:model` property).
     * @param  array<int, array<string, mixed>>  $options        Select options.
     * @param  string|null                    $placeholder       The select's empty option.
     * @param  string|null                    $placeholderValue  Unused since 2.0.0: the library's empty option always has an empty value.
     * @param  string                         $optionValue       The option key holding the value.
     * @param  string                         $optionLabel       The option key holding the label.
     *
     * @throws InvalidArgumentException When `control` is not one of {@see self::CONTROLS}.
     */
    public function __construct(
        public string $id,
        public string $control = 'input',
        public ?string $label = null,
        public ?string $hint = null,
        public ?string $errorField = null,
        public array $options = [],
        public ?string $placeholder = null,
        public ?string $placeholderValue = null,
        public string $optionValue = 'id',
        public string $optionLabel = 'name',
    ) {
        if ( ! in_array( $control, self::CONTROLS, true ) ) {
            throw new InvalidArgumentException( sprintf( 'Unknown field control "%s".', $control ) );
        }
    }

    /**
     * The id base for the error and hint: the id with anything an id
     * reference would trip on replaced.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function baseId(): string
    {
        return 'ec-field-' . (string) preg_replace( '/[^A-Za-z0-9_-]+/', '-', $this->id );
    }

    /**
     * The error bag key: `error-field`, or the `wire:model` property.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function errorKey(): ?string
    {
        if ( null !== $this->errorField && '' !== $this->errorField ) {
            return $this->errorField;
        }

        $model = $this->attributes->whereStartsWith( 'wire:model' )->first();

        return is_string( $model ) && '' !== $model ? $model : null;
    }

    /**
     * The select's options in the shape the library's Select reads
     * (`value`, `label`), taken from each option's `option-value` and
     * `option-label` keys.
     *
     * @since 2.0.0
     *
     * @return list<array{value: string, label: string, disabled?: bool}>
     */
    public function selectOptions(): array
    {
        $options = [];

        foreach ( $this->options as $option ) {
            if ( ! is_array( $option ) || ! array_key_exists( $this->optionValue, $option ) ) {
                continue;
            }

            $options[] = array_filter( [
                'value'    => (string) $option[ $this->optionValue ],
                'label'    => (string) ( $option[ $this->optionLabel ] ?? $option[ $this->optionValue ] ),
                'disabled' => ! empty( $option['disabled'] ) ? true : null,
            ], static fn ( mixed $value ): bool => null !== $value );
        }

        return $options;
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
        return view( 'ecommerce-storefront::components.field' );
    }
}
