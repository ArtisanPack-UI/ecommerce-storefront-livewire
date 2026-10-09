<?php

/**
 * Storefront cache.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Caches what every catalog page recomputes (spec §12, S41): the walked
 * category tree ({@see CategoryPaths}) and catalog facet counts.
 *
 * Entries are tagged `ecommerce-storefront` on stores that support tags;
 * on others every key carries a version number that a flush bumps. The
 * cache is flushed whenever the catalog changes — the engine's
 * `ap.ecommerce.product.saved`, `.deleted`, `.published`, and
 * `.unpublished` hooks, stock changes (`ap.ecommerce.inventory.adjusted`,
 * `.outOfStock`), and a category or tag being saved or deleted — and
 * entries expire after `performance.cache_ttl` seconds regardless.
 * `performance.cache_ttl` of 0 turns the cache off.
 *
 * Facet counts are keyed by the listing's scope and filters. A host whose
 * `ap.ecommerce.product.listQuery` filter shows shoppers different
 * products (per customer group, say) should turn the cache off.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
final class StorefrontCache
{
    /**
     * The cache tag.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const TAG = 'ecommerce-storefront';

    /**
     * The key holding the version on stores without tags.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const VERSION_KEY = 'ecommerce-storefront:cache-version';

    /**
     * The engine hooks after which cached catalog data is stale.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const FLUSH_ON = [
        'ap.ecommerce.product.saved',
        'ap.ecommerce.product.deleted',
        'ap.ecommerce.product.published',
        'ap.ecommerce.product.unpublished',
        'ap.ecommerce.inventory.adjusted',
        'ap.ecommerce.inventory.outOfStock',
    ];

    /**
     * Flushes the cache whenever the catalog changes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function boot(): void
    {
        foreach ( self::FLUSH_ON as $hook ) {
            addAction( $hook, static fn () => self::flush() );
        }

        foreach ( [ ProductCategory::class, ProductTag::class ] as $model ) {
            $model::saved( static fn () => self::flush() );
            $model::deleted( static fn () => self::flush() );
        }
    }

    /**
     * How long entries live, in seconds; 0 when the cache is off.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public static function ttl(): int
    {
        return max( 0, (int) config( 'artisanpack.ecommerce-storefront-livewire.performance.cache_ttl', 600 ) );
    }

    /**
     * The cached value of `$key`, computed by `$callback` when missing (or
     * every time with the cache off). A cache failure falls back to
     * computing the value.
     *
     * @since 1.0.0
     *
     * @template TValue
     *
     * @param  string            $key       The key, unique within the storefront.
     * @param  Closure(): TValue  $callback  Computes the value.
     *
     * @return TValue
     */
    public static function remember( string $key, Closure $callback ): mixed
    {
        $ttl = self::ttl();

        if ( 0 === $ttl ) {
            return $callback();
        }

        try {
            return self::tagged()
                ? Cache::tags( [ self::TAG ] )->remember( self::TAG . ':' . $key, $ttl, $callback )
                : Cache::remember( self::TAG . ':v' . self::version() . ':' . $key, $ttl, $callback );
        } catch ( Throwable $exception ) {
            report( $exception );

            return $callback();
        }
    }

    /**
     * Drops every cached entry.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function flush(): void
    {
        try {
            if ( self::tagged() ) {
                Cache::tags( [ self::TAG ] )->flush();

                return;
            }

            Cache::forever( self::VERSION_KEY, self::version() + 1 );
        } catch ( Throwable $exception ) {
            report( $exception );
        }
    }

    /**
     * A stable key for `$parts`.
     *
     * @since 1.0.0
     *
     * @param  string  $prefix  What is cached (`facets`).
     * @param  mixed   $parts   What the value depends on.
     *
     * @return string
     */
    public static function key( string $prefix, mixed $parts ): string
    {
        return $prefix . ':' . hash( 'xxh128', serialize( $parts ) );
    }

    /**
     * Whether the default store supports tags.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    private static function tagged(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }

    /**
     * The current version on stores without tags.
     *
     * @since 1.0.0
     *
     * @return int
     */
    private static function version(): int
    {
        return (int) Cache::get( self::VERSION_KEY, 0 );
    }
}
