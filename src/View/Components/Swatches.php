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
 * an http(s) image URL), `count` (shown after the label), `meta` (a short
 * muted note after the label, such as a price difference; since 2.0.0), `selected`,
 * `disabled`, and `reason` (why it is disabled, read to screen readers and
 * shown as a tooltip). `soldOut` (since 2.0.0) strikes an option through
 * while it stays selectable, as for a sold-out variant the shopper can sign
 * up for. `wire:model` attributes go to every input.
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
     * @var array<int, array{value: string, label: string, color: string|null, image: string|null, count: int|null, meta: string|null, selected: bool, disabled: bool, soldOut: bool, reason: string|null}>
     */
    public array $items;

    /**
     * @since 1.0.0
     * @since 2.0.0 Added `$required` and `$error`.
     *
     * @param  string                            $name      The inputs' name (unique per group on the page).
     * @param  string                            $legend    The group's visible label.
     * @param  array<int, array<string, mixed>>  $options   The options.
     * @param  bool                              $multiple  Checkboxes (many) instead of radios (one).
     * @param  string|null                       $idPrefix  Prefix for the inputs' ids.
     * @param  bool                              $required  Marks the group required (a "*" after the legend).
     * @param  string|null                       $error     The group's error, shown under it and linked to it.
     */
    public function __construct(
        public string $name,
        public string $legend,
        public array $options = [],
        public bool $multiple = false,
        public ?string $idPrefix = null,
        public bool $required = false,
        public ?string $error = null,
    ) {
        $this->idPrefix ??= 'ec-swatch-' . preg_replace( '/[^A-Za-z0-9_-]/', '-', $name );
        $this->items      = array_values( array_map( self::normalize( ... ), array_filter( $options, 'is_array' ) ) );
        $this->error      = null !== $error && '' !== trim( $error ) ? $error : null;
    }

    /**
     * The id of the group's error message.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function errorId(): string
    {
        return $this->idPrefix . '-error';
    }

    /**
     * The chosen value's label, for the legend ("Glaze: Stoneware").
     *
     * @since 2.0.0
     *
     * @return string|null Null when nothing is chosen.
     */
    public function chosenLabel(): ?string
    {
        foreach ( $this->items as $item ) {
            if ( $item['selected'] ) {
                return $item['label'];
            }
        }

        return null;
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
     * @return array{value: string, label: string, color: string|null, image: string|null, count: int|null, meta: string|null, selected: bool, disabled: bool, soldOut: bool, reason: string|null}
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
            'meta'     => is_string( $option['meta'] ?? null ) && '' !== trim( $option['meta'] ) ? $option['meta'] : null,
            'selected' => (bool) ( $option['selected'] ?? false ),
            'disabled' => (bool) ( $option['disabled'] ?? false ),
            'soldOut'  => (bool) ( $option['soldOut'] ?? false ),
            'reason'   => is_string( $option['reason'] ?? null ) && '' !== $option['reason'] ? $option['reason'] : null,
        ];
    }
}
