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
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\OrderAccess;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Throwable;

/**
 * `<x-artisanpack-ec-account-shell :title="__( 'Orders' )">…</x-artisanpack-ec-account-shell>`
 *
 * Without a title the shell prints no heading, for content that brings its
 * own h1 (the dashboard's greeting, an order's number).
 *
 * The frame every account page sits in (spec §7.5, S25), built on the
 * design system: from 1024px a 232px sidebar (the shopper's Avatar, name
 * and email, the account navigation and a "Sign out" button) beside the
 * content; below that, the same navigation as a row of tabs that scrolls
 * sideways. The content starts with a PageHeader holding the page's one h1.
 * Both navigations are `<nav aria-label="Account">`; only one shows at a
 * time. "Sign out" posts to the host's `auth.logout_route` and is left out
 * when the route doesn't exist.
 *
 * The navigation (a design-system Menu) lists the storefront's own
 * account pages, then the entries satellites register with the engine's
 * `AccountMenuRegistry` (subscriptions, wishlists, …) that the shopper may
 * see, all sorted by position (the storefront's use 10–60; a satellite's
 * default is 100). The engine's own entries (`ENGINE_CORE_ENTRIES`) are
 * left out, and "Claim an order" shows only to shoppers who may claim
 * (`OrderAccess::canClaim()`). The entry for the current page is marked active and
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
     * @since 2.0.0 The icons are design-system registry names.
     *
     * @var array<string, array{icon: string, position: int}>
     */
    public const CORE_ENTRIES = [
        'dashboard'    => [ 'icon' => 'layout-grid', 'position' => 10 ],
        'orders.index' => [ 'icon' => 'package', 'position' => 20 ],
        'addresses'    => [ 'icon' => 'map-pin', 'position' => 30 ],
        'downloads'    => [ 'icon' => 'download', 'position' => 40 ],
        'profile'      => [ 'icon' => 'user', 'position' => 50 ],
        'claim'        => [ 'icon' => 'tag', 'position' => 60 ],
    ];

    /**
     * The engine's own account-menu entries (key => `ecommerce.account.*`
     * route), which the storefront's pages already cover: its profile page
     * holds the email preferences and its downloads page the license keys.
     * They're left out so a host that defines those routes doesn't get
     * every page twice.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const ENGINE_CORE_ENTRIES = [
        'profile'       => 'ecommerce.account.profile',
        'orders'        => 'ecommerce.account.orders',
        'addresses'     => 'ecommerce.account.addresses',
        'downloads'     => 'ecommerce.account.downloads',
        'license-keys'  => 'ecommerce.account.license-keys',
        'notifications' => 'ecommerce.account.notifications',
    ];

    /**
     * @since 1.0.0
     * @since 2.0.0 The title is optional.
     *
     * @param  string|null  $title  The page heading, or null when the content has its own h1.
     */
    public function __construct(
        public ?string $title = null,
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

            if ( ! Route::has( $name ) || ( 'claim' === $route && ! OrderAccess::canClaim( auth()->user() ) ) ) {
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
            if ( null === $entry['url'] || '' === trim( (string) $entry['label'] ) || ( self::ENGINE_CORE_ENTRIES[ $entry['key'] ] ?? null ) === $entry['route'] ) {
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
     * The signed-in shopper's name, email and initials for the sidebar, or
     * null for a guest.
     *
     * @since 2.0.0
     *
     * @return array{name: string, email: string, initials: string}|null
     */
    public function shopper(): ?array
    {
        $user = auth()->user();

        if ( null === $user ) {
            return null;
        }

        $email = (string) ( $user->email ?? '' );
        $name  = trim( (string) ( $user->name ?? '' ) );

        $name  = '' === $name ? $email : $name;
        $words = preg_split( '/[\s@._-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY ) ?: [];

        return [
            'name'     => $name,
            'email'    => $email,
            'initials' => mb_strtoupper( implode( '', array_map( static fn ( string $word ): string => mb_substr( $word, 0, 1 ), array_slice( $words, 0, 2 ) ) ) ),
        ];
    }

    /**
     * Where "Sign out" posts: the host's `auth.logout_route`, or null when
     * it doesn't exist.
     *
     * @since 2.0.0
     *
     * @return string|null
     */
    public function signOutUrl(): ?string
    {
        $route = (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.logout_route', 'logout' );

        return '' !== $route && Route::has( $route ) ? route( $route ) : null;
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
