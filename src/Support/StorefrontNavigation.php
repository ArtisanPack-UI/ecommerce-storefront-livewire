<?php

/**
 * Storefront layout navigation.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPack\LivewireUiComponents\Support\SafeUrl;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * The links the standalone layout (#129) shows: the header's store
 * navigation and the footer's link columns, and the store's mark.
 *
 * **Header** — "Shop all" (the catalog), then the first
 * `storefront.nav_categories` top-level categories. The link for the page
 * the shopper is on (the catalog, or a category or anything under it) is
 * current. Change the list with
 * `ap.ecommerceStorefrontLivewire.layout.navigation`: a list of
 * `label`, `url` and `current`.
 *
 * ```php
 * addFilter( 'ap.ecommerceStorefrontLivewire.layout.navigation', function ( array $links ): array {
 *     $links[] = [ 'label' => __( 'Journal' ), 'url' => url( '/journal' ), 'current' => request()->is( 'journal*' ) ];
 *
 *     return $links;
 * } );
 * ```
 *
 * **Footer** — `shop` (all products and the same categories), `help`
 * ("Find an order") and `account` (sign in or the account, orders and
 * addresses), each with a `title` and `links` (`label` and `url`).
 * Change them with `ap.ecommerceStorefrontLivewire.layout.footerLinks`,
 * e.g. to add a "Shipping & returns" page to `help`. Links whose route
 * isn't registered, links without a label or URL, and links with an unsafe
 * URL (`javascript:`, `data:`, …) are left out, and so is a column with no
 * links.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */
final class StorefrontNavigation
{
    /**
     * The catalog route.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const CATALOG_ROUTE = 'artisanpack.ecommerce.storefront.catalog';

    /**
     * The header's store navigation.
     *
     * @since 2.0.0
     *
     * @return array<int, array{label: string, url: string, current: bool}>
     */
    public static function header(): array
    {
        $links = [];

        if ( Route::has( self::CATALOG_ROUTE ) ) {
            $links[] = [ 'label' => __( 'Shop all' ), 'url' => route( self::CATALOG_ROUTE ), 'current' => request()->routeIs( self::CATALOG_ROUTE ) ];
        }

        $current = self::currentTopCategory();

        foreach ( self::categories() as $category ) {
            $links[] = [ 'label' => $category['name'], 'url' => $category['url'], 'current' => $current === $category['id'] ];
        }

        return self::links( applyFilters( 'ap.ecommerceStorefrontLivewire.layout.navigation', $links ), [ 'label', 'url' ], true );
    }

    /**
     * The footer's link columns.
     *
     * @since 2.0.0
     *
     * @return array<string, array{title: string, links: array<int, array{label: string, url: string}>}>
     */
    public static function footer(): array
    {
        $shop = Route::has( self::CATALOG_ROUTE ) ? [ [ 'label' => __( 'All products' ), 'url' => route( self::CATALOG_ROUTE ) ] ] : [];

        foreach ( self::categories() as $category ) {
            $shop[] = [ 'label' => $category['name'], 'url' => $category['url'] ];
        }

        $help = self::routeLinks( [ 'artisanpack.ecommerce.storefront.lookup' => __( 'Find an order' ) ] );

        $login   = (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.login_route', 'login' );
        $account = self::routeLinks( [
            ...( null === auth()->user() ? [ $login => __( 'Sign in' ) ] : [ 'artisanpack.ecommerce.account.dashboard' => __( 'Account' ) ] ),
            'artisanpack.ecommerce.account.orders.index' => __( 'Orders' ),
            'artisanpack.ecommerce.account.addresses'    => __( 'Addresses' ),
        ] );

        $columns = applyFilters( 'ap.ecommerceStorefrontLivewire.layout.footerLinks', [
            'shop'    => [ 'title' => __( 'Shop' ), 'links' => $shop ],
            'help'    => [ 'title' => __( 'Help' ), 'links' => $help ],
            'account' => [ 'title' => __( 'Account' ), 'links' => $account ],
        ] );

        $clean = [];

        foreach ( is_array( $columns ) ? $columns : [] as $key => $column ) {
            if ( ! is_string( $key ) || ! is_array( $column ) || ! is_string( $column['title'] ?? null ) || '' === $column['title'] ) {
                continue;
            }

            $links = self::links( $column['links'] ?? [], [ 'label', 'url' ], false );

            if ( [] !== $links ) {
                $clean[ $key ] = [ 'title' => $column['title'], 'links' => $links ];
            }
        }

        return $clean;
    }

    /**
     * The store's mark: the first letters of the first two words of its
     * name that start with a letter or number ("Field & Kiln" is "FK").
     *
     * @since 2.0.0
     *
     * @param  string  $name  The store name.
     *
     * @return string
     */
    public static function mark( string $name ): string
    {
        preg_match_all( '/(?<![\p{L}\p{N}])[\p{L}\p{N}]/u', $name, $letters );

        return mb_strtoupper( implode( '', array_slice( $letters[0], 0, 2 ) ) );
    }

    /**
     * The first `storefront.nav_categories` top-level categories that have
     * a page.
     *
     * @since 2.0.0
     *
     * @return array<int, array{id: int, name: string, url: string}>
     */
    private static function categories(): array
    {
        $limit = max( 0, (int) config( 'artisanpack.ecommerce-storefront-livewire.storefront.nav_categories', 3 ) );

        if ( 0 === $limit ) {
            return [];
        }

        try {
            $paths      = app( CategoryPaths::class );
            $categories = [];

            foreach ( $paths->children( null ) as $node ) {
                $url = $paths->url( (int) $node['id'] );

                if ( null !== $url ) {
                    $categories[] = [ 'id' => (int) $node['id'], 'name' => (string) $node['name'], 'url' => $url ];
                }

                if ( count( $categories ) >= $limit ) {
                    break;
                }
            }

            return $categories;
        } catch ( Throwable $exception ) {
            report( $exception );

            return [];
        }
    }

    /**
     * The top-level category of the category page the shopper is on, or
     * null.
     *
     * @since 2.0.0
     *
     * @return int|null
     */
    private static function currentTopCategory(): ?int
    {
        $category = app( StorefrontContext::class )->category();

        if ( null === $category ) {
            return null;
        }

        try {
            $ancestry = app( CategoryPaths::class )->ancestry( (int) $category->id );

            return [] === $ancestry ? null : (int) $ancestry[0]['id'];
        } catch ( Throwable $exception ) {
            report( $exception );

            return null;
        }
    }

    /**
     * Links for the registered routes: route name => label.
     *
     * @since 2.0.0
     *
     * @param  array<string, string>  $routes  Route name => label.
     *
     * @return array<int, array{label: string, url: string}>
     */
    private static function routeLinks( array $routes ): array
    {
        $links = [];

        foreach ( $routes as $name => $label ) {
            if ( '' !== $name && Route::has( $name ) ) {
                $links[] = [ 'label' => $label, 'url' => route( $name ) ];
            }
        }

        return $links;
    }

    /**
     * The complete links of a filtered list: a non-empty string `label`
     * and a safe `url` (no `javascript:`, `data:` or other unsafe scheme,
     * the design system's SafeUrl check), and (with `$current`) a boolean
     * `current`. The footer and the menu drawer print these URLs as they
     * are, so the check happens here.
     *
     * @since 2.0.0
     *
     * @param  mixed               $links    The filter's result.
     * @param  array<int, string>  $fields   Required string fields.
     * @param  bool                $current  Whether to keep `current`.
     *
     * @return array<int, array<string, bool|string>>
     */
    private static function links( mixed $links, array $fields, bool $current ): array
    {
        $clean = [];

        foreach ( is_array( $links ) ? $links : [] as $link ) {
            if ( ! is_array( $link ) ) {
                continue;
            }

            $entry = [];

            foreach ( $fields as $field ) {
                if ( ! is_string( $link[ $field ] ?? null ) || '' === trim( $link[ $field ] ) ) {
                    continue 2;
                }

                $entry[ $field ] = $link[ $field ];
            }

            if ( isset( $entry['url'] ) && null === SafeUrl::href( $entry['url'] ) ) {
                continue;
            }

            if ( $current ) {
                $entry['current'] = true === ( $link['current'] ?? false );
            }

            $clean[] = $entry;
        }

        return $clean;
    }
}
