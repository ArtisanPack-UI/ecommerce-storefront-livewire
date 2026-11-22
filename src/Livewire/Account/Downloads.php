<?php

/**
 * Account downloads and licence keys component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\LicenseKey;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Services\DigitalDownloadService;
use ArtisanPackUI\Ecommerce\Services\LicenseService;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * `<livewire:artisanpack-ecommerce-storefront-account-downloads />`
 *
 * The shopper's digital downloads and licence keys (spec §7.5, S28), from
 * the engine's owner services (`DigitalDownloadService::forCustomer()`,
 * `LicenseService::forCustomer()`).
 *
 * Each entitlement shows its product, file, version, downloads left, and
 * expiry, with a download button to the account's owner route (which
 * spends a download) — or, for a streaming-only file, a button that opens
 * it in the browser's player. Both are temporary signed URLs
 * (`DOWNLOAD_LINK_MINUTES`, `STREAM_LINK_MINUTES`); the Download button
 * asks `download()` for a fresh one when clicked, so a page left open
 * still works. An expired or used-up entitlement says why instead. Licence keys show in full with a copy button, their activation
 * count, and whether they are expired or revoked.
 *
 * Both lists are paginated (`downloads` and `licences` in the query
 * string). Only the signed-in shopper's own records are listed.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Downloads extends Component
{
    use SendsToasts;
    use WithPagination;

    /**
     * Entries per page in each list.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const PER_PAGE = 20;

    /**
     * How long a rendered download link works, in minutes. Links are
     * temporary signed URLs, so a cross-site GET or a link prefetcher
     * can't spend the shopper's downloads.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const DOWNLOAD_LINK_MINUTES = 10;

    /**
     * How long a rendered stream link works, in minutes. Longer than a
     * download link, since the browser's player keeps requesting byte
     * ranges from the same URL while the shopper listens or watches.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const STREAM_LINK_MINUTES = 240;

    /**
     * Sends the shopper to a freshly signed link for one of their files, so
     * a page left open longer than `DOWNLOAD_LINK_MINUTES` still downloads.
     * The Download button calls this; its `href` is the fallback without
     * JavaScript. Another customer's entitlement (or none) is a 404; an
     * expired or used-up one gets a toast and the page re-renders with why.
     *
     * @since 2.0.0
     *
     * @param  int  $id  The entitlement id.
     *
     * @return void
     */
    public function download( int $id ): void
    {
        $customer = Customer::forUser( auth()->user() );

        abort_if( null === $customer, 404 );

        $download = app( DigitalDownloadService::class )->forCustomer( $customer )->whereKey( $id )->first();

        abort_unless( $download instanceof DigitalDownload, 404 );

        if ( $download->isExpired() || ! $download->hasDownloadsRemaining() ) {
            $this->toastWarning(
                __( 'This download isn\'t available' ),
                $download->isExpired() ? __( 'This download has expired.' ) : __( 'You\'ve used all the downloads for this file.' ),
            );

            return;
        }

        $route = true === $download->file?->is_streaming_only ? 'artisanpack.ecommerce.account.downloads.stream' : 'artisanpack.ecommerce.account.downloads.file';

        abort_unless( Route::has( $route ), 404 );

        $this->redirect( URL::temporarySignedRoute( $route, now()->addMinutes( self::DOWNLOAD_LINK_MINUTES ), [ 'download' => $download->id ] ) );
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $customer  = Customer::forUser( auth()->user() );
        $downloads = null;
        $licences  = null;
        $failed    = false;

        if ( null !== $customer ) {
            try {
                $downloads = app( DigitalDownloadService::class )->forCustomer( $customer )
                    ->with( 'orderItem.order' )
                    ->orderByDesc( 'id' )
                    ->paginate( self::PER_PAGE, pageName: 'downloads' )
                    ->through( fn ( DigitalDownload $download ): array => $this->downloadRow( $download ) );

                $licences = app( LicenseService::class )->forCustomer( $customer )
                    ->orderByDesc( 'id' )
                    ->paginate( self::PER_PAGE, pageName: 'licences' )
                    ->through( fn ( LicenseKey $licence ): array => $this->licenceRow( $licence ) );
            } catch ( Throwable $exception ) {
                report( $exception );

                $failed = true;
            }
        }

        return view( 'ecommerce-storefront::livewire.account.downloads', [
            'downloads'  => $downloads,
            'licences'   => $licences,
            'failed'     => $failed,
            'catalogUrl' => Route::has( 'artisanpack.ecommerce.storefront.catalog' ) ? route( 'artisanpack.ecommerce.storefront.catalog' ) : null,
        ] );
    }

    /**
     * An entitlement as a row: `id`, `product`, `file`, `version`,
     * `remaining` (null for unlimited), `expires` (a date, or null),
     * `state` (`available`, `expired`, `exhausted`), `streaming`, and
     * `url` (a signed download or stream link, when available).
     *
     * @since 1.0.0
     *
     * @param  DigitalDownload  $download  The entitlement, with its file and order line.
     *
     * @return array{id: int, product: string, file: string, version: string|null, order: string|null, remaining: int|null, expires: string|null, state: string, streaming: bool, url: string|null}
     */
    protected function downloadRow( DigitalDownload $download ): array
    {
        $state = match ( true ) {
            $download->isExpired()               => 'expired',
            ! $download->hasDownloadsRemaining() => 'exhausted',
            default                              => 'available',
        };

        $streaming = true === $download->file?->is_streaming_only;
        $route     = $streaming ? 'artisanpack.ecommerce.account.downloads.stream' : 'artisanpack.ecommerce.account.downloads.file';
        $label     = trim( (string) $download->file?->label );
        $version   = trim( (string) $download->file?->version );

        return [
            'id'        => (int) $download->id,
            'product'   => $this->productName( $download->orderItem ),
            'file'      => '' !== $label ? $label : $this->productName( $download->orderItem ),
            'version'   => '' !== $version ? $version : null,
            'order'     => null === $download->orderItem?->order ? null : (string) $download->orderItem->order->order_number,
            'remaining' => null === $download->downloads_remaining ? null : (int) $download->downloads_remaining,
            'expires'   => null === $download->expires_at ? null : LocalizedDate::format( $download->expires_at ),
            'state'     => $state,
            'streaming' => $streaming,
            'url'       => 'available' === $state && Route::has( $route )
                ? URL::temporarySignedRoute( $route, now()->addMinutes( $streaming ? self::STREAM_LINK_MINUTES : self::DOWNLOAD_LINK_MINUTES ), [ 'download' => $download->id ] )
                : null,
        ];
    }

    /**
     * A licence key as a row: `id`, `product`, `key`, `activations`,
     * `limit` (null for unlimited), `expires`, and `state` (`active`,
     * `expired`, `revoked`).
     *
     * @since 1.0.0
     *
     * @param  LicenseKey  $licence  The licence, with its order line.
     *
     * @return array{id: int, product: string, variant: string|null, key: string, activations: int, limit: int|null, expires: string|null, state: string}
     */
    protected function licenceRow( LicenseKey $licence ): array
    {
        return [
            'id'          => (int) $licence->id,
            'product'     => $this->productName( $licence->orderItem ),
            'variant'     => $this->variantName( $licence->orderItem ),
            'key'         => (string) $licence->key,
            'activations' => (int) $licence->activations_count,
            'limit'       => null === $licence->activations_limit ? null : (int) $licence->activations_limit,
            'expires'     => null === $licence->expires_at ? null : LocalizedDate::format( $licence->expires_at ),
            'state'       => match ( true ) {
                (bool) $licence->is_revoked => 'revoked',
                $licence->isExpired()       => 'expired',
                default                     => 'active',
            },
        ];
    }

    /**
     * The product name the order line recorded.
     *
     * @since 1.0.0
     *
     * @param  OrderItem|null  $item  The order line.
     *
     * @return string
     */
    protected function productName( ?OrderItem $item ): string
    {
        $name = $item?->product_snapshot['name'] ?? null;

        return is_string( $name ) && '' !== trim( $name ) ? trim( $name ) : __( 'Item' );
    }

    /**
     * The order line's variant name (a licence tier, say), if it has one.
     *
     * @since 2.0.0
     *
     * @param  OrderItem|null  $item  The order line.
     *
     * @return string|null
     */
    protected function variantName( ?OrderItem $item ): ?string
    {
        $variant = $item?->product_snapshot['variant_name'] ?? null;

        return is_string( $variant ) && '' !== trim( $variant ) ? trim( $variant ) : null;
    }
}
