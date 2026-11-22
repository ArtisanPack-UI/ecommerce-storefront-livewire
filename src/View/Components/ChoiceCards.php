<?php

/**
 * Choice cards component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-choice-cards :legend="__( 'Frame' )" name="frame" :options="$options" wire:model="options.frame" />`
 *
 * One choice from a few, each a card: an optional image, the name, a hint
 * under it and a price difference at the end. Native radios in a fieldset,
 * so arrow keys move within the group and Tab moves between groups. Used by
 * the options form for a radio field with `meta.display = cards`. A local
 * stand-in for the design-system RadioGroup's `cards` variant, which can't
 * show an image yet.
 *
 * Each option is `value`, `label`, and optionally `hint`, `meta` (a price
 * difference), `image` (`url`, `srcset`, `alt`), `selected`, `disabled`,
 * and `reason` (why it is disabled, read to screen readers and shown under
 * the name). `wire:model` attributes go to every input.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */
class ChoiceCards extends Component
{
    /**
     * The options, normalised.
     *
     * @since 2.0.0
     *
     * @var array<int, array{value: string, label: string, hint: string|null, meta: string|null, image: array{url: string, srcset: string|null, alt: string}|null, selected: bool, disabled: bool, reason: string|null}>
     */
    public array $items;

    /**
     * @since 2.0.0
     *
     * @param  string                            $name      The inputs' name (unique per group on the page).
     * @param  string                            $legend    The group's visible label.
     * @param  array<int, array<string, mixed>>  $options   The options.
     * @param  string|null                       $idPrefix  Prefix for the inputs' ids.
     * @param  bool                              $required  Marks the group required (a "*" after the legend).
     * @param  string|null                       $hint      Help under the legend.
     * @param  string|null                       $error     The group's error, shown under it and linked to it.
     */
    public function __construct(
        public string $name,
        public string $legend,
        public array $options = [],
        public ?string $idPrefix = null,
        public bool $required = false,
        public ?string $hint = null,
        public ?string $error = null,
    ) {
        $this->idPrefix ??= 'ec-choice-' . preg_replace( '/[^A-Za-z0-9_-]/', '-', $name );
        $this->items      = array_values( array_map( self::normalize( ... ), array_filter( $options, 'is_array' ) ) );
        $this->hint       = null !== $hint && '' !== trim( $hint ) ? $hint : null;
        $this->error      = null !== $error && '' !== trim( $error ) ? $error : null;
    }

    /**
     * The id of the group's hint.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function hintId(): string
    {
        return $this->idPrefix . '-hint';
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
     * Renders the component.
     *
     * @since 2.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-storefront::components.choice-cards' );
    }

    /**
     * One option, with a safe image URL.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $option  Option.
     *
     * @return array{value: string, label: string, hint: string|null, meta: string|null, image: array{url: string, srcset: string|null, alt: string}|null, selected: bool, disabled: bool, reason: string|null}
     */
    private static function normalize( array $option ): array
    {
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

        $text = static fn ( string $key ): ?string => is_string( $option[ $key ] ?? null ) && '' !== trim( $option[ $key ] ) ? $option[ $key ] : null;

        return [
            'value'    => (string) ( $option['value'] ?? '' ),
            'label'    => (string) ( $option['label'] ?? $option['value'] ?? '' ),
            'hint'     => $text( 'hint' ),
            'meta'     => $text( 'meta' ),
            'image'    => $image,
            'selected' => (bool) ( $option['selected'] ?? false ),
            'disabled' => (bool) ( $option['disabled'] ?? false ),
            'reason'   => $text( 'reason' ),
        ];
    }
}
