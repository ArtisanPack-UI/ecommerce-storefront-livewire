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
     * The payload. `type` is the toaster color: success, warning or error.
     *
     * @since 1.0.0
     * @since 2.0.0 The payload is the toaster's event detail (`title`,
     *              `description`, `color`); the alert class is gone and
     *              the text is no longer HTML-escaped.
     *
     * @param  string       $type         The toast type.
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     *
     * @return array{toast: array{title: string, description: string|null, color: string}}
     */
    public static function make( string $type, string $title, ?string $description = null ): array
    {
        return [
            'toast' => [
                'title'       => $title,
                'description' => $description,
                'color'       => $type,
            ],
        ];
    }

    /**
     * A script statement that shows the payload's toast.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $payload  A payload from {@see self::make()}.
     *
     * @return string
     */
    public static function script( array $payload ): string
    {
        return 'window.dispatchEvent( new CustomEvent( "toast", { detail: ' . self::encode( $payload['toast'] ?? [] ) . ' } ) );';
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
}
