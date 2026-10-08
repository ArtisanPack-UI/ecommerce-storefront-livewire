<?php

/**
 * Account shell component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Registries\AccountMenuRegistry;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Throwable;

/**
 * `<x-artisanpack-ec-account-shell :title="__( 'Orders' )">…</x-artisanpack-ec-account-shell>`
 *
 * The frame every account page sits in (spec §7.5, S25): the page heading
 * and the account navigation beside the content.
 *
 * The navigation (`x-artisanpack-menu`) lists the storefront's own
 * account pages, then the entries satellites register with the engine's
 * `AccountMenuRegistry` (subscriptions, wishlists, …) that the shopper may
 * see, all sorted by position (the storefront's use 10–60; a satellite's
 * default is 100). The entry for the current page is marked active and
 * `aria-current="page"`; an entry stays active on its sub-pages (an
 * order's detail under "Orders").
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class AccountShell extends Component
{
    /**
     * The storefront's own account pages: route name (under
     * `artisanpack.ecommerce.account.`) => icon and position.
     *
     * @since 1.0.0
     *
     * @var array<string, array{icon: string, position: int}>
     */
    public const CORE_ENTRIES = [
        'dashboard'    => [ 'icon' => 'o-home', 'position' => 10 ],
        'orders.index' => [ 'icon' => 'o-shopping-bag', 'position' => 20 ],
        'addresses'    => [ 'icon' => 'o-map-pin', 'position' => 30 ],
        'downloads'    => [ 'icon' => 'o-arrow-down-tray', 'position' => 40 ],
        'profile'      => [ 'icon' => 'o-user', 'position' => 50 ],
        'claim'        => [ 'icon' => 'o-document-check', 'position' => 60 ],
    ];

    /**
     * @since 1.0.0
     *
     * @param  string  $title  The page heading.
     */
    public function __construct(
        public string $title,
    ) {
    }

    /**
     * The navigation entries, in order: `key`, `label`, `url`, `icon`,
     * and `active`.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string, url: string, icon: string|null, active: bool}>
     */
    public function entries(): array
    {
        $entries = [];
        $current = (string) Route::currentRouteName();

        foreach ( self::CORE_ENTRIES as $route => $entry ) {
            $name = 'artisanpack.ecommerce.account.' . $route;

            if ( ! Route::has( $name ) ) {
                continue;
            }

            $section   = str_ends_with( $name, '.index' ) ? substr( $name, 0, -strlen( 'index' ) ) : $name . '.';
            $entries[] = [
                'key'      => $route,
                'label'    => $this->coreLabel( $route ),
                'url'      => route( $name ),
                'icon'     => $entry['icon'],
                'position' => $entry['position'],
                'active'   => $current === $name || str_starts_with( $current, $section ),
            ];
        }

        foreach ( $this->satelliteEntries() as $entry ) {
            if ( null === $entry['url'] || '' === trim( (string) $entry['label'] ) ) {
                continue;
            }

            $entries[] = [
                'key'      => 'satellite-' . $entry['key'],
                'label'    => (string) $entry['label'],
                'url'      => (string) $entry['url'],
                'icon'     => $entry['icon'] ?? null,
                'position' => (int) $entry['position'],
                'active'   => $current === $entry['route'] || rtrim( (string) $entry['url'], '/' ) === rtrim( request()->url(), '/' ),
            ];
        }

        $order = array_flip( array_column( $entries, 'key' ) );

        usort( $entries, static fn ( array $a, array $b ): int => [ $a['position'], $order[ $a['key'] ] ] <=> [ $b['position'], $order[ $b['key'] ] ] );

        return array_map( static fn ( array $entry ): array => array_diff_key( $entry, [ 'position' => true ] ), $entries );
    }

    /**
     * Gets the view / contents that represent the component.
     *
     * @since 1.0.0
     *
     * @return Closure|string|View
     */
    public function render(): View|Closure|string
    {
        return view( 'ecommerce-storefront::components.account-shell' );
    }

    /**
     * The label of a core entry.
     *
     * @since 1.0.0
     *
     * @param  string  $route  The entry's route (under `account.`).
     *
     * @return string
     */
    protected function coreLabel( string $route ): string
    {
        return match ( $route ) {
            'dashboard'    => __( 'Dashboard' ),
            'orders.index' => __( 'Orders' ),
            'addresses'    => __( 'Addresses' ),
            'downloads'    => __( 'Downloads' ),
            'profile'      => __( 'Profile' ),
            default        => __( 'Claim an order' ),
        };
    }

    /**
     * The entries satellites registered that the shopper may see. A
     * failing registry hides them rather than the page.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string, route: string, url: string|null, icon: string|null, position: int}>
     */
    protected function satelliteEntries(): array
    {
        try {
            return app( AccountMenuRegistry::class )->visibleTo( Customer::forUser( auth()->user() ) );
        } catch ( Throwable $exception ) {
            report( $exception );

            return [];
        }
    }
}
