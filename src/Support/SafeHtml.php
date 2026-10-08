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
 * It runs `kses()` from `artisanpack-ui/security` in htmLawed's safe mode:
 * the helper's default configuration keeps `<script>`, event-handler
 * attributes, and `javascript:` URLs, which a storefront must never print.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class SafeHtml
{
    /**
     * The htmLawed configuration: safe mode, plus no inline styles or forms.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    public const CONFIG = [
        'safe'           => 1,
        'elements'       => '* -form -input -button -select -textarea -option -style -link -meta -base',
        'deny_attribute' => 'on*, style',
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
}
