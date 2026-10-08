<?php

/**
 * Checkout layout and settings.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Registries\SettingsRegistry;
use ArtisanPackUI\Ecommerce\Settings\SettingDefinition;

/**
 * The store owner's checkout choices (spec §5.5, §7.4): the layout
 * (multi-step wizard or single page) and the terms page shoppers must
 * accept.
 *
 * Both are engine settings, so the admin's settings screen changes them
 * (`storefront.checkout_layout`, `storefront.terms_url`, under
 * "Checkout"); without a stored value they fall back to this package's
 * config (`checkout.layout`, `checkout.terms_url`).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class CheckoutLayout
{
    /**
     * One step at a time.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MULTI_STEP = 'multi_step';

    /**
     * Every section on one page.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SINGLE_PAGE = 'single_page';

    /**
     * The layout setting's key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const LAYOUT_SETTING = 'storefront.checkout_layout';

    /**
     * The terms page setting's key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const TERMS_SETTING = 'storefront.terms_url';

    /**
     * What a terms page may be: an http(s) URL, or a path on this site.
     * `//host` and `/\\host` are refused, since browsers open them on
     * another host.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const TERMS_URL_PATTERN = '#^(https?://\\S+|/(?![/\\\\])\\S*)$#i';

    /**
     * The layout to show.
     *
     * @since 1.0.0
     *
     * @return string {@see self::MULTI_STEP} or {@see self::SINGLE_PAGE}.
     */
    public static function current(): string
    {
        $layout = ecommerceSetting( self::LAYOUT_SETTING, config( 'artisanpack.ecommerce-storefront-livewire.checkout.layout', self::MULTI_STEP ) );

        return self::SINGLE_PAGE === $layout ? self::SINGLE_PAGE : self::MULTI_STEP;
    }

    /**
     * The terms and conditions page shoppers accept before placing an
     * order, or null when none is set (no checkbox is shown). Only an
     * http(s) URL or a path on this site is used.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public static function termsUrl(): ?string
    {
        $url = ecommerceSetting( self::TERMS_SETTING, config( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url' ) );
        $url = is_string( $url ) ? trim( $url ) : '';

        return 1 === preg_match( self::TERMS_URL_PATTERN, $url ) ? $url : null;
    }

    /**
     * Adds the settings to the engine's "Checkout" group, so the admin's
     * settings screen shows them.
     *
     * @since 1.0.0
     *
     * @param  SettingsRegistry  $settings  The engine's settings.
     *
     * @return void
     */
    public static function registerSettings( SettingsRegistry $settings ): void
    {
        if ( ! $settings->hasGroup( 'checkout' ) ) {
            $settings->addGroup( 'checkout', __( 'Checkout' ), 20 );
        }

        if ( ! $settings->has( self::LAYOUT_SETTING ) ) {
            $settings->define( new SettingDefinition(
                key: self::LAYOUT_SETTING,
                group: 'checkout',
                type: 'select',
                label: __( 'Checkout layout' ),
                rules: [ 'required', 'in:' . self::MULTI_STEP . ',' . self::SINGLE_PAGE ],
                description: __( 'Show checkout one step at a time, or every section on one page.' ),
                options: static fn (): array => [
                    self::MULTI_STEP  => __( 'Multi-step' ),
                    self::SINGLE_PAGE => __( 'Single page' ),
                ],
                configKey: 'artisanpack.ecommerce-storefront-livewire.checkout.layout',
                position: 50,
            ) );
        }

        if ( ! $settings->has( self::TERMS_SETTING ) ) {
            $settings->define( new SettingDefinition(
                key: self::TERMS_SETTING,
                group: 'checkout',
                type: 'string',
                label: __( 'Terms and conditions page' ),
                rules: [ 'nullable', 'string', 'max:2048', 'regex:' . self::TERMS_URL_PATTERN ],
                description: __( 'Shoppers must accept this page before placing an order. Leave blank to skip the checkbox.' ),
                configKey: 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url',
                position: 60,
            ) );
        }
    }
}
