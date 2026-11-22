<?php

/**
 * Safe HTML helper.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

/**
 * Cleans store-owner HTML (product and category descriptions, filter
 * content) before the storefront prints it unescaped.
 *
 * It runs `kses()` from `artisanpack-ui/security` with an explicit
 * htmLawed configuration, so it behaves the same on security 1.x (whose
 * default configuration keeps `<script>`, event handlers and `javascript:`
 * URLs) and 2.x (whose hardened default only applies when no config is
 * passed). The configuration mirrors the security package's safe profile:
 *
 * - script-capable elements, `<style>`, `<link>`, `<meta>`, `<base>`,
 *   forms, form controls, frames, `<template>`, `<slot>` and custom
 *   (hyphenated) elements are removed;
 * - `on*`, `style`, `class` and `ping` attributes are removed, and
 *   {@see self::tag()} also drops Alpine/Livewire directives (`x-*`, `@*`,
 *   `:*`, `wire:*`) and `is`, which every storefront page would otherwise
 *   execute because Livewire bundles Alpine;
 * - URLs are limited to `http`/`https` (plus `mailto`/`tel` in `href`).
 *
 * `class` is denied because content could otherwise borrow the theme's own
 * utility classes (`fixed inset-0 z-50 …`) to draw a full-page overlay
 * over the real storefront, for example a fake sign-in prompt.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class SafeHtml
{
    /**
     * The htmLawed configuration.
     *
     * @since 1.0.0
     * @since 2.0.0 Removes custom elements, `<template>`, `<slot>`, `class`
     *              and Alpine/Livewire directives, and limits URL schemes.
     *
     * @var array<string, mixed>
     */
    public const CONFIG = [
        'safe'               => 1,
        'elements'           => '* -base -button -datalist -dialog -form -frame -frameset -input -isindex -keygen -link -meta -optgroup -option -select -slot -style -template -textarea',
        'deny_attribute'     => 'on*, class, ping, style',
        'any_custom_element' => 0,
        'schemes'            => 'href: http, https, mailto, tel; src: http, https; *: http, https',
        'hook_tag'           => [ self::class, 'tag' ],
    ];

    /**
     * The cleaned HTML, or null when nothing is left.
     *
     * @since 1.0.0
     *
     * @param  string|null  $html  HTML.
     *
     * @return string|null
     */
    public static function clean( ?string $html ): ?string
    {
        if ( null === $html || '' === trim( $html ) ) {
            return null;
        }

        $clean = trim( security()->kses( $html, self::CONFIG ) );

        return '' === $clean ? null : $clean;
    }

    /**
     * Renders a tag for htmLawed's `hook_tag`.
     *
     * htmLawed calls this with the attributes that survived its own
     * filtering (values already escaped). It drops Alpine/Livewire
     * directives and `is`, removes the `opener` link type and adds
     * `noopener` to targeted links.
     *
     * @since 2.0.0
     *
     * @param  string  $element  The element name.
     * @param  array<string, string>|int  $attributes  The filtered attributes, or `0` for a closing tag.
     *
     * @return string The rendered tag.
     */
    public static function tag( string $element, array|int $attributes = 0 ): string
    {
        if ( ! is_array( $attributes ) ) {
            return '</' . $element . '>';
        }

        $safe = [];

        foreach ( $attributes as $name => $value ) {
            $name = (string) $name;

            if ( 1 === preg_match( '/^(x-|@|:|wire:|is$)/i', $name ) ) {
                continue;
            }

            $safe[ $name ] = (string) $value;
        }

        if ( isset( $safe['rel'] ) || isset( $safe['target'] ) ) {
            $rel = array_values( array_filter(
                preg_split( '/\s+/', trim( $safe['rel'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY ) ?: [],
                fn ( string $type ): bool => 'opener' !== strtolower( $type ),
            ) );

            if ( isset( $safe['target'] ) && ! in_array( 'noopener', array_map( 'strtolower', $rel ), true ) ) {
                $rel[] = 'noopener';
            }

            $safe['rel'] = implode( ' ', $rel );

            if ( '' === $safe['rel'] ) {
                unset( $safe['rel'] );
            }
        }

        $html = '<' . $element;

        foreach ( $safe as $name => $value ) {
            $html .= ' ' . $name . '="' . $value . '"';
        }

        $void = [ 'area', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'isindex', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr' ];

        return $html . ( in_array( $element, $void, true ) ? ' />' : '>' );
    }
}
