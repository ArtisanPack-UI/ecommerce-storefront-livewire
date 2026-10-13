<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\LicenseKey;
use ArtisanPackUI\Ecommerce\Services\DigitalDownloadService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Downloads;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach( function (): void {
    Storage::fake( 'local' );
    $this->freezeTime();

    Route::get( 'login', static fn (): string => 'login' )->middleware( 'web' )->name( 'login' );
    app( 'router' )->getRoutes()->refreshNameLookups();
} );

/**
 * An entitlement to a stored file on one of `$customer`'s orders (a
 * guest's when null).
 *
 * @param  array<string, mixed>  $download  Entitlement overrides.
 * @param  array<string, mixed>  $file      File overrides.
 */
function ownedDownload( ?Customer $customer, array $download = [], array $file = [] ): DigitalDownload
{
    $order = placedOrder( [], $customer, [ makeProduct( 1500, [ 'name' => 'Field guide' ] ) ] );
    $item  = $order->items->first();
    $model = DigitalFile::factory()->create( $file + [ 'product_id' => $item->product_id, 'label' => 'Field guide PDF', 'version' => '2.1', 'path' => 'digital/guide-' . uniqid() . '.pdf' ] );

    Storage::disk( 'local' )->put( $model->path, 'PDF bytes' );

    return DigitalDownload::factory()->create( $download + [ 'order_item_id' => $item->id, 'digital_file_id' => $model->id ] );
}

/**
 * The temporary signed link the downloads page renders for `$download`.
 *
 * @param  string  $kind  `file` or `stream`.
 */
function signedDownloadUrl( string $kind, DigitalDownload $download ): string
{
    $minutes = 'stream' === $kind ? Downloads::STREAM_LINK_MINUTES : Downloads::DOWNLOAD_LINK_MINUTES;

    return URL::temporarySignedRoute( 'artisanpack.ecommerce.account.downloads.' . $kind, now()->addMinutes( $minutes ), [ 'download' => $download->id ] );
}

/**
 * A licence key on one of `$customer`'s orders.
 *
 * @param  array<string, mixed>  $attributes  Overrides.
 */
function ownedLicence( ?Customer $customer, array $attributes = [] ): LicenseKey
{
    $order = placedOrder( [], $customer, [ makeProduct( 4900, [ 'name' => 'Photo app' ] ) ] );

    return LicenseKey::factory()->create( $attributes + [ 'order_item_id' => $order->items->first()->id ] );
}

it( 'lists the shopper\'s entitlements with the file, version, downloads left, expiry, and a download button', function (): void {
    [ , $customer ] = shopper();

    $download = ownedDownload( $customer, [ 'downloads_remaining' => 3, 'expires_at' => now()->addDays( 10 ) ] );
    $stranger = ownedDownload( Customer::factory()->create() );

    Livewire::test( Downloads::class )
        ->assertSeeHtml( 'data-account-download="' . $download->id . '"' )
        ->assertDontSeeHtml( 'data-account-download="' . $stranger->id . '"' )
        ->assertSee( 'Field guide' )
        ->assertSee( 'Field guide PDF' )
        ->assertSee( 'Version 2.1' )
        ->assertSee( '3 downloads left' )
        ->assertSee( 'Available until' )
        ->assertSeeHtml( 'href="' . signedDownloadUrl( 'file', $download ) . '"' );
} );

it( 'opens a streaming-only file in the player instead of downloading it', function (): void {
    [ , $customer ] = shopper();

    $download = ownedDownload( $customer, [], [ 'is_streaming_only' => true ] );

    Livewire::test( Downloads::class )
        ->assertSeeHtml( 'data-account-download-play' )
        ->assertDontSeeHtml( 'data-account-download-button' )
        ->assertSeeHtml( 'href="' . signedDownloadUrl( 'stream', $download ) . '"' );
} );

it( 'explains expired and used-up entitlements instead of offering them', function (): void {
    [ , $customer ] = shopper();

    $expired   = ownedDownload( $customer, [ 'expires_at' => now()->subDay() ] );
    $exhausted = ownedDownload( $customer, [ 'downloads_remaining' => 0 ] );

    Livewire::test( Downloads::class )
        ->assertSeeHtml( 'data-account-download-reason="expired"' )
        ->assertSee( 'This download expired on' )
        ->assertSeeHtml( 'data-account-download-reason="exhausted"' )
        ->assertSee( 'You\'ve used all the downloads for this file.' )
        ->assertDontSeeHtml( route( 'artisanpack.ecommerce.account.downloads.file', [ 'download' => $expired->id ] ) . '?' )
        ->assertDontSeeHtml( route( 'artisanpack.ecommerce.account.downloads.file', [ 'download' => $exhausted->id ] ) . '?' );
} );

it( 'lists licence keys with a copy button and the activation count', function (): void {
    [ , $customer ] = shopper();

    $licence  = ownedLicence( $customer, [ 'key' => 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2', 'activations_count' => 2, 'activations_limit' => 5 ] );
    ownedLicence( $customer, [ 'activations_count' => 0, 'activations_limit' => 1 ] );
    $revoked  = ownedLicence( $customer, [ 'is_revoked' => true, 'revoked_at' => now() ] );
    $expired  = ownedLicence( $customer, [ 'expires_at' => now()->subDay() ] );
    $stranger = ownedLicence( Customer::factory()->create() );

    Livewire::test( Downloads::class )
        ->assertSeeHtml( 'data-account-licence="' . $licence->id . '"' )
        ->assertDontSeeHtml( 'data-account-licence="' . $stranger->id . '"' )
        ->assertSee( 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2' )
        ->assertSee( '2 of 5 activations used' )
        ->assertSee( '0 of 1 activation used' )
        ->assertSeeHtml( 'data-account-licence-copy' )
        ->assertSeeHtml( 'data-account-licence-reason="revoked"' )
        ->assertSeeHtml( 'data-account-licence-reason="expired"' );
} );

it( 'shows an empty state when there is nothing to download, even without a customer record', function (): void {
    $this->actingAs( makeUser() );

    Livewire::test( Downloads::class )
        ->assertSeeHtml( 'data-account-no-downloads' )
        ->assertDontSeeHtml( 'data-account-files' );
} );

it( 'still renders when the engine can\'t list the downloads', function (): void {
    shopper();

    app()->instance( DigitalDownloadService::class, new class extends DigitalDownloadService {
        public function forCustomer( Customer $customer ): Illuminate\Database\Eloquent\Builder
        {
            throw new RuntimeException( 'Database is down.' );
        }
    } );

    Livewire::test( Downloads::class )
        ->assertOk()
        ->assertSeeHtml( 'data-account-downloads-failed' )
        ->assertDontSeeHtml( 'data-account-no-downloads' );
} );

it( 'sends the file and spends a download through the owner route', function (): void {
    [ , $customer ] = shopper();
    $download       = ownedDownload( $customer, [ 'downloads_remaining' => 2 ] );

    $response = $this->get( signedDownloadUrl( 'file', $download ) )
        ->assertOk()
        ->assertHeader( 'X-Robots-Tag', 'noindex, nofollow' );

    expect( $response->streamedContent() )->toBe( 'PDF bytes' )
        ->and( $download->refresh()->downloads_remaining )->toBe( 1 )
        ->and( $download->download_count )->toBe( 1 );
} );

it( 'refuses an unsigned or expired download link without spending a download', function ( string $kind ): void {
    [ , $customer ] = shopper();
    $download       = ownedDownload( $customer, [ 'downloads_remaining' => 2 ], [ 'is_streaming_only' => 'stream' === $kind ] );

    $this->get( route( 'artisanpack.ecommerce.account.downloads.' . $kind, [ 'download' => $download->id ] ) )->assertForbidden();

    $signed = signedDownloadUrl( $kind, $download );
    $this->travel( ( 'stream' === $kind ? Downloads::STREAM_LINK_MINUTES : Downloads::DOWNLOAD_LINK_MINUTES ) + 1 )->minutes();

    $this->get( $signed )->assertForbidden();

    expect( $download->refresh()->downloads_remaining )->toBe( 2 )
        ->and( $download->download_count )->toBe( 0 );
} )->with( [ 'file', 'stream' ] );

it( 'downloads through the signed link the downloads page renders', function (): void {
    [ , $customer ] = shopper();
    $download       = ownedDownload( $customer, [ 'downloads_remaining' => 2 ] );

    $html = Livewire::test( Downloads::class )->html();

    preg_match( '/href="([^"]*\/downloads\/' . $download->id . '\?[^"]*)"/', $html, $link );

    expect( $link[1] ?? '' )->toContain( 'signature=' );

    $response = $this->get( html_entity_decode( $link[1] ) )->assertOk();

    expect( $response->streamedContent() )->toBe( 'PDF bytes' )
        ->and( $download->refresh()->downloads_remaining )->toBe( 1 );
} );

it( 'streams a file through the owner route', function (): void {
    [ , $customer ] = shopper();
    $download       = ownedDownload( $customer, [ 'downloads_remaining' => 2 ], [ 'is_streaming_only' => true ] );

    $this->get( signedDownloadUrl( 'stream', $download ) )->assertOk();

    expect( $download->refresh()->downloads_remaining )->toBe( 1 );

    $this->get( signedDownloadUrl( 'file', $download ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.account.downloads' ) );
} );

it( 'sends an expired download back to the downloads page with the reason', function (): void {
    [ , $customer ] = shopper();
    $download       = ownedDownload( $customer, [ 'expires_at' => now()->subDay() ] );

    $this->get( signedDownloadUrl( 'file', $download ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.account.downloads' ) )
        ->assertSessionHas( ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload::SESSION_KEY );

    $this->get( signedDownloadUrl( 'stream', $download ) )->assertStatus( 410 );
} );

it( 'answers 404 for another customer\'s download, or a user without a customer record', function (): void {
    $stranger = ownedDownload( Customer::factory()->create() );

    $this->actingAs( makeUser() )
        ->get( signedDownloadUrl( 'file', $stranger ) )
        ->assertNotFound();

    shopper();

    $this->get( signedDownloadUrl( 'file', $stranger ) )->assertNotFound();
    $this->get( signedDownloadUrl( 'stream', $stranger ) )->assertNotFound();

    expect( $stranger->refresh()->download_count )->toBe( 0 );
} );

it( 'sends a guest to sign in from the download route', function (): void {
    $download = ownedDownload( null );

    $this->get( signedDownloadUrl( 'file', $download ) )->assertRedirect( route( 'login' ) );
} );
