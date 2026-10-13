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
 * Builds the payload `<x-artisanpack-toast>`'s `toast()` function takes.
 *
 * The toast container prints the title and description with Alpine's
 * `x-html`, so both are HTML-escaped, and the JSON is encoded with the HEX
 * flags so it can sit inside a `<script>` without breaking out of it.
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
     * The payload, with the title and description escaped.
     *
     * @since 1.0.0
     *
     * @param  string       $type         The toast type.
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     * @param  string       $css          The alert class.
     *
     * @return array{toast: array{type: string, title: string, description: string|null, icon: string, css: string}}
     */
    public static function make( string $type, string $title, ?string $description, string $css ): array
    {
        return [
            'toast' => [
                'type'        => $type,
                'title'       => e( $title ),
                'description' => null === $description ? null : e( $description ),
                'icon'        => '',
                'css'         => $css,
            ],
        ];
    }

    /**
     * The payload as JSON that is safe inside a `<script>`.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  A payload from {@see self::make()}.
     *
     * @return string
     */
    public static function encode( array $payload ): string
    {
        return json_encode( $payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR );
    }
}
