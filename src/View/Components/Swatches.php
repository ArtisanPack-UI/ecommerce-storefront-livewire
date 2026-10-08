<?php

/**
 * Swatch selector component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-swatches :legend="__( 'Colour' )" name="colour" :options="$options" wire:model.live="selected.4" />`
 *
 * A group of swatches or pills, one native radio (or checkbox with
 * `multiple`) per option, so arrow keys move within the group and Tab moves
 * between groups. Local stand-in for the library's swatch selector
 * (livewire-ui-components#122, spec §10 U3); when it ships, this is the one
 * file to swap.
 *
 * Each option is `value`, `label`, and optionally `swatch` (a hex colour or
 * an http(s) image URL), `count` (shown after the label), `selected`,
 * `disabled`, and `reason` (why it is disabled, read to screen readers and
 * shown as a tooltip). `wire:model` attributes go to every input.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Swatches extends Component
{
    /**
     * The options, normalised.
     *
     * @since 1.0.0
     *
     * @var array<int, array{value: string, label: string, color: string|null, image: string|null, count: int|null, selected: bool, disabled: bool, reason: string|null}>
     */
    public array $items;

    /**
     * @since 1.0.0
     *
     * @param  string                            $name      The inputs' name (unique per group on the page).
     * @param  string                            $legend    The group's visible label.
     * @param  array<int, array<string, mixed>>  $options   The options.
     * @param  bool                              $multiple  Checkboxes (many) instead of radios (one).
     * @param  string|null                       $idPrefix  Prefix for the inputs' ids.
     */
    public function __construct(
        public string $name,
        public string $legend,
        public array $options = [],
        public bool $multiple = false,
        public ?string $idPrefix = null,
    ) {
        $this->idPrefix ??= 'ec-swatch-' . preg_replace( '/[^A-Za-z0-9_-]/', '-', $name );
        $this->items      = array_values( array_map( self::normalize( ... ), array_filter( $options, 'is_array' ) ) );
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
        return view( 'ecommerce-storefront::components.swatches' );
    }

    /**
     * One option with a safe colour or image.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $option  Option.
     *
     * @return array{value: string, label: string, color: string|null, image: string|null, count: int|null, selected: bool, disabled: bool, reason: string|null}
     */
    private static function normalize( array $option ): array
    {
        $swatch = is_string( $option['swatch'] ?? null ) ? trim( $option['swatch'] ) : '';
        $color  = 1 === preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $swatch ) ? $swatch : null;

        return [
            'value'    => (string) ( $option['value'] ?? '' ),
            'label'    => (string) ( $option['label'] ?? $option['value'] ?? '' ),
            'color'    => $color,
            'image'    => null === $color && '' !== $swatch ? ProductImages::safeUrl( $swatch ) : null,
            'count'    => is_int( $option['count'] ?? null ) ? $option['count'] : null,
            'selected' => (bool) ( $option['selected'] ?? false ),
            'disabled' => (bool) ( $option['disabled'] ?? false ),
            'reason'   => is_string( $option['reason'] ?? null ) && '' !== $option['reason'] ? $option['reason'] : null,
        ];
    }
}
