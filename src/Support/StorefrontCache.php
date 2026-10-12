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

use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductAttributeValue;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Caches what every catalog page recomputes (spec §12, S41): catalog facet
 * counts, category grid counts, and attribute labels and swatches. (The
 * category tree is cached by the engine's `CategoryTree`.)
 *
 * Entries are tagged `ecommerce-storefront` on stores that support tags;
 * on others every key carries a version number that a flush bumps. The
 * cache is flushed whenever the catalog changes — the engine's
 * `ap.ecommerce.product.saved`, `.deleted`, `.published`, `.unpublished`,
 * and `ap.ecommerce.variant.saved` hooks; a product going out of stock
 * (`ap.ecommerce.inventory.outOfStock`) or back in stock (an
 * `ap.ecommerce.inventory.adjusted` from none to some, not every sale);
 * and a category, tag, price, attribute, attribute value, or variant
 * being saved or deleted — and entries expire after
 * `performance.cache_ttl` seconds regardless. `performance.cache_ttl` of
 * 0 turns the cache off.
 *
 * The engine fires no hook when a product's categories or tags are
 * re-synced (`ProductService::setCategories()`/`setTags()`), so counts
 * can lag behind those for up to `performance.cache_ttl` seconds.
 *
 * On stores with locks, one request computes a missing entry while the
 * others wait for it, so a flush doesn't send every shopper to the
 * database at once.
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
        'ap.ecommerce.variant.saved',
        'ap.ecommerce.inventory.outOfStock',
    ];

    /**
     * The models whose saves and deletes make cached catalog data stale.
     *
     * Stock rows aren't here: every sale saves one. Stock changes flush
     * only when a product runs out or comes back ({@see self::boot()}).
     *
     * @since 1.0.0
     *
     * @var array<int, class-string<Model>>
     */
    public const FLUSH_ON_MODELS = [
        ProductCategory::class,
        ProductTag::class,
        ProductPrice::class,
        ProductAttribute::class,
        ProductAttributeValue::class,
        ProductVariant::class,
    ];

    /**
     * How long a request waits for another to compute an entry, in seconds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    private const LOCK_WAIT = 5;

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

        // Back in stock: on hand went from none to some.
        addAction( 'ap.ecommerce.inventory.adjusted', static function ( mixed $item = null, mixed $delta = 0, mixed $onHand = 0 ): void {
            if ( is_numeric( $delta ) && is_numeric( $onHand ) && (int) $onHand > 0 && (int) $onHand - (int) $delta <= 0 ) {
                self::flush();
            }
        } );

        foreach ( self::FLUSH_ON_MODELS as $model ) {
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
     * every time with the cache off). A cache failure is reported and falls
     * back to computing the value; an exception from `$callback` itself is
     * thrown to the caller, once.
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

        $failed  = false;
        $compute = static function () use ( $callback, &$failed ): mixed {
            try {
                return $callback();
            } catch ( Throwable $exception ) {
                $failed = true;

                throw $exception;
            }
        };

        try {
            [ $cache, $fullKey ] = self::tagged()
                ? [ Cache::tags( [ self::TAG ] ), self::TAG . ':' . $key ]
                : [ Cache::store(), self::TAG . ':v' . self::version() . ':' . $key ];

            return self::rememberLocked( $cache, $fullKey, $ttl, $compute );
        } catch ( Throwable $exception ) {
            // The value's own failure is the caller's; only a cache failure
            // falls back to computing the value uncached.
            if ( $failed ) {
                throw $exception;
            }

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
     * `Repository::remember()`, but on a miss only one request computes
     * the value (under a lock, when the store has locks) while the others
     * wait and then read it. A request that waits too long computes it
     * itself.
     *
     * @since 1.0.0
     *
     * @param  Repository        $cache    The cache (tagged or not).
     * @param  string            $key      The full key.
     * @param  int               $ttl      Seconds.
     * @param  Closure(): mixed  $compute  Computes the value.
     *
     * @return mixed
     */
    private static function rememberLocked( Repository $cache, string $key, int $ttl, Closure $compute ): mixed
    {
        $store = Cache::getStore();
        $value = $cache->get( $key );

        if ( null !== $value || ! $store instanceof LockProvider ) {
            return $value ?? $cache->remember( $key, $ttl, $compute );
        }

        try {
            return $store->lock( $key . ':lock', self::LOCK_WAIT * 2 )->block( self::LOCK_WAIT, static fn (): mixed => $cache->remember( $key, $ttl, $compute ) );
        } catch ( LockTimeoutException ) {
            return $cache->remember( $key, $ttl, $compute );
        }
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
