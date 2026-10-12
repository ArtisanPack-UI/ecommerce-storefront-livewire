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
 * (`control`) whose error and hint are linked to it: when the field's key
 * has an error, the control gets `aria-invalid="true"`, and
 * `aria-describedby` points at the error and the hint, each rendered here
 * with its own id. The library prints both without ids and never marks
 * the control invalid (livewire-ui-components#117), so it is told to omit
 * its own error. A select or textarea also gets its label as
 * `aria-label`: the library labels them with a `<legend>`, which doesn't
 * name the control (same issue).
 *
 * The error key is `error-field`, or else the `wire:model` property.
 * Every other attribute (`wire:model`, `type`, `required`, `autocomplete`,
 * `data-*`) is passed to the library control; the select's options are
 * props here because the library takes them as props. A checkbox keeps
 * its hint inside its label, where it is already part of the name.
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
     * @param  string                         $id                The id passed to the library control (it prefixes it); also the base of the error and hint ids.
     * @param  string                         $control           The library control: input, select, textarea, password, or checkbox.
     * @param  string|null                    $label             The visible label.
     * @param  string|null                    $hint              Help text shown under the control.
     * @param  string|null                    $errorField        The error bag key (defaults to the `wire:model` property).
     * @param  array<int, array<string, mixed>>  $options        Select options.
     * @param  string|null                    $placeholder       The select's empty option.
     * @param  string|null                    $placeholderValue  The empty option's value.
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
