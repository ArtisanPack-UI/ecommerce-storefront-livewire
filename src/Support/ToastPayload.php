<?php

/**
 * Toast payloads.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

/**
 * Builds the `toast` event `<x-artisanpack-toaster>` listens for: a title,
 * an optional description and a design-system color.
 *
 * The toaster prints the title and description as text (Alpine's
 * `x-text`), so they are never parsed as markup, and the JSON is encoded
 * with the HEX flags so it can sit inside a `<script>` without breaking
 * out of it.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class ToastPayload
{
    /**
     * The session key a toast for the next page is kept under until a
     * page shows it.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SESSION_KEY = 'ecommerce_storefront_toast';

    /**
     * The toast variants: `inverse` is the dark toast the design uses for a
     * removal with Undo.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const VARIANTS = [ 'default', 'inverse' ];

    /**
     * The payload. `type` is the toaster color: success, warning or error.
     *
     * `$options` takes the design's extras (#97):
     *
     * - `variant`: `inverse` for the dark toast; anything else is dropped.
     * - `icon`: an icon name (letters, digits and hyphens) in place of the
     *   color's status icon.
     * - `action`: one button, `[ 'label' => …, 'url' => … ]` to open a page
     *   (an http(s) or root-relative URL) or `[ 'label' => …, 'call' => … ]`
     *   to call a method on the component that sent the toast (Undo). A
     *   toast with an action stays until it is dismissed.
     *
     * @since 1.0.0
     * @since 2.0.0 The payload is the toaster's event detail (`title`,
     *              `description`, `color`); the alert class is gone and
     *              the text is no longer HTML-escaped.
     * @since 2.0.0 Added `$options` (variant, icon, action).
     *
     * @param  string                                                                   $type         The toast type.
     * @param  string                                                                   $title        The title.
     * @param  string|null                                                              $description  The description.
     * @param  array{variant?: string, icon?: string, action?: array<string, string>}  $options      The variant, icon and action.
     *
     * @return array{toast: array<string, mixed>}
     */
    public static function make( string $type, string $title, ?string $description = null, array $options = [] ): array
    {
        $toast = [
            'title'       => $title,
            'description' => $description,
            'color'       => $type,
        ];

        $variant = $options['variant'] ?? null;

        if ( is_string( $variant ) && 'default' !== $variant && in_array( $variant, self::VARIANTS, true ) ) {
            $toast['variant'] = $variant;
        }

        $icon = $options['icon'] ?? null;

        if ( is_string( $icon ) && 1 === preg_match( '/^[a-z0-9][a-z0-9-]*$/i', $icon ) ) {
            $toast['icon'] = $icon;
        }

        $action = self::action( $options['action'] ?? null );

        if ( null !== $action ) {
            $toast['action'] = $action;
        }

        return [ 'toast' => $toast ];
    }

    /**
     * A script statement that shows the payload's toast.
     *
     * The action becomes the button's click handler: a `url` action opens
     * the page, a `call` action calls the method on `$wire`. Only a
     * component's own script has `$wire` (`$this->js()`), so a toast shown
     * on the next page (`$withWire` false) drops a `call` action.
     *
     * @since 2.0.0
     * @since 2.0.0 Added `$withWire`, and the action's click handler.
     *
     * @param  array<string, mixed>  $payload   A payload from {@see self::make()}.
     * @param  bool                  $withWire  Whether the script runs with the sending component's `$wire`.
     *
     * @return string
     */
    public static function script( array $payload, bool $withWire = false ): string
    {
        $toast  = is_array( $payload['toast'] ?? null ) ? $payload['toast'] : [];
        $action = self::action( $toast['action'] ?? null );

        unset( $toast['action'] );

        $detail = self::encode( $toast );

        if ( null !== $action && ( isset( $action['url'] ) || $withWire ) ) {
            $handler = isset( $action['url'] )
                ? 'window.location.assign( ' . self::encode( [ $action['url'] ] ) . '[0] )'
                : '$wire.call( ' . self::encode( [ $action['call'] ] ) . '[0] )';

            $detail = 'Object.assign( ' . $detail . ', { action: { label: ' . self::encode( [ $action['label'] ] ) . '[0], onClick: () => ' . $handler . ' } } )';
        }

        return 'window.dispatchEvent( new CustomEvent( "toast", { detail: ' . $detail . ' } ) );';
    }

    /**
     * The payload as JSON that is safe inside a `<script>`.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  A payload, or part of one.
     *
     * @return string
     */
    public static function encode( array $payload ): string
    {
        return json_encode( $payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR );
    }

    /**
     * A valid action, or null: a non-empty label and either a URL that is
     * http(s) or root-relative, or a method name.
     *
     * @since 2.0.0
     *
     * @param  mixed  $action  The action from the options or a payload.
     *
     * @return array{label: string, url?: string, call?: string}|null
     */
    private static function action( mixed $action ): ?array
    {
        if ( ! is_array( $action ) || ! is_string( $action['label'] ?? null ) || '' === trim( $action['label'] ) ) {
            return null;
        }

        $url  = $action['url'] ?? null;
        $call = $action['call'] ?? null;

        if ( is_string( $url ) && 1 === preg_match( '#^(https?://|/(?![/\\\\]))#i', $url ) ) {
            return [ 'label' => $action['label'], 'url' => $url ];
        }

        if ( is_string( $call ) && 1 === preg_match( '/^[a-z_][a-z0-9_]*$/i', $call ) ) {
            return [ 'label' => $action['label'], 'call' => $call ];
        }

        return null;
    }
}
