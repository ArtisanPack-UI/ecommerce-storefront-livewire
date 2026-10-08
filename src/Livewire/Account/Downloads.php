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
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
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
 * it in the browser's player. An expired or used-up entitlement says why
 * instead. Licence keys show in full with a copy button, their activation
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
     * `url` (download or stream, when available).
     *
     * @since 1.0.0
     *
     * @param  DigitalDownload  $download  The entitlement, with its file and order line.
     *
     * @return array{id: int, product: string, file: string, version: string|null, remaining: int|null, expires: string|null, state: string, streaming: bool, url: string|null}
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
            'file'      => '' !== $label ? $label : __( 'Download' ),
            'version'   => '' !== $version ? $version : null,
            'remaining' => null === $download->downloads_remaining ? null : (int) $download->downloads_remaining,
            'expires'   => null === $download->expires_at ? null : LocalizedDate::format( $download->expires_at ),
            'state'     => $state,
            'streaming' => $streaming,
            'url'       => 'available' === $state && Route::has( $route ) ? route( $route, [ 'download' => $download->id ] ) : null,
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
     * @return array{id: int, product: string, key: string, activations: int, limit: int|null, expires: string|null, state: string}
     */
    protected function licenceRow( LicenseKey $licence ): array
    {
        return [
            'id'          => (int) $licence->id,
            'product'     => $this->productName( $licence->orderItem ),
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
}
