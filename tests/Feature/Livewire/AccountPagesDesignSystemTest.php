<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\LicenseKey;
use ArtisanPackUI\Ecommerce\Services\CustomerOrderHistory;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Addresses;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Claim;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Downloads;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Orders;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The order history, address book, downloads, profile and claim screens on
 * the design system (#147–#151).
 */

/** Tailwind and DaisyUI classes these screens used before the rebuild. */
const OLD_ACCOUNT_SCREEN_CLASSES = [
    'flex', 'flex-col', 'flex-wrap', 'grid', 'gap-1', 'gap-2', 'gap-3', 'gap-4', 'gap-6', 'gap-8', 'text-sm', 'text-xl', 'font-semibold', 'font-bold',
    'font-mono', 'rounded-box', 'border', 'border-base-content/10', 'text-base-content/70', 'divide-y', 'divide-base-content/10', 'p-4', 'p-5',
    'max-w-xs', 'max-w-xl', 'max-w-2xl', 'md:grid-cols-2', 'sm:grid-cols-2', 'sm:col-span-2', 'justify-end', 'justify-between', 'items-center',
    'mt-auto', 'mb-2', 'link', 'modal-action', 'sr-only', 'text-end', 'break-all',
];

beforeEach( function (): void {
    Storage::fake( 'local' );

    Route::get( 'login', static fn (): string => 'login' )->middleware( 'web' )->name( 'login' );
    app( 'router' )->getRoutes()->refreshNameLookups();
} );

/**
 * A saved address for `$customer`.
 *
 * @param  array<string, mixed>  $attributes  Overrides.
 */
function designAddress( Customer $customer, array $attributes = [] ): CustomerAddress
{
    return CustomerAddress::factory()->create( $attributes + [ 'customer_id' => $customer->id ] + checkoutAddress() );
}

it( 'heads the order history with "Orders" beside the "Show" filter, as a table and a compact list (#147)', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [ 'order_number' => 'ORD-7001' ], $customer );

    $html = Livewire::test( Orders::class, [ 'showTitle' => true ] )->html();

    expect( $html )->toMatch( '#<div class="ec-orders__head">\s*(<!--\[if BLOCK\]><!\[endif\]-->)?\s*<h1 class="ec-orders__title">Orders</h1>#' )
        ->toContain( 'class="apui-select ec-orders__filter' )
        ->toContain( 'data-account-orders-table' )
        ->toMatch( '#scope="col"\s*>\s*(<!--\[if BLOCK\]><!\[endif\]-->)?\s*Placed\s#' )
        ->toContain( 'data-numeric' )
        ->toContain( 'data-account-order-view="' . $order->id . '"' )
        ->toContain( 'View<span class="apui-sr-only">: Order ORD-7001</span>' )
        ->toContain( 'data-account-order-row="' . $order->id . '"' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_ACCOUNT_SCREEN_CLASSES );
} );

it( 'leaves the order history untitled when embedded', function (): void {
    shopper();

    Livewire::test( Orders::class )
        ->assertDontSeeHtml( '<h1' )
        ->assertSeeHtml( 'ec-orders__head--untitled' );
} );

it( 'shows no orders as the empty state in a card', function (): void {
    shopper();

    Livewire::test( Orders::class )
        ->assertSeeHtml( 'data-empty-state-card' )
        ->assertSeeHtml( 'data-account-orders-empty="none"' )
        ->assertDontSeeHtml( 'data-account-orders-table' );
} );

it( 'offers "Try again" when the orders couldn\'t load', function (): void {
    shopper();

    app()->instance( CustomerOrderHistory::class, new class extends CustomerOrderHistory {
        public function query( Customer $customer, ?string $status = null ): Builder
        {
            throw new RuntimeException( 'Database is down.' );
        }
    } );

    Livewire::test( Orders::class, [ 'showTitle' => true ] )
        ->assertSee( 'Your orders couldn\'t load.' )
        ->assertSeeHtml( 'data-account-orders-retry' )
        ->assertSeeHtml( 'wire:click="$refresh"' )
        ->assertSeeHtml( 'class="ec-orders__title"' )
        ->assertDontSeeHtml( 'data-account-orders-table' )
        ->assertDontSeeHtml( 'data-empty-state-card' );
} );

it( 'heads the order history page with "Orders" as its only h1', function (): void {
    shopper();

    $html = $this->get( route( 'artisanpack.ecommerce.account.orders.index' ) )->assertOk()->getContent();

    expect( substr_count( $html, '<h1' ) )->toBe( 1 )
        ->and( $html )->toContain( '<h1 class="ec-orders__title">Orders</h1>' );
} );

it( 'shows saved addresses as cards with default badges and footer actions (#148)', function (): void {
    [ , $customer ] = shopper();
    $home           = designAddress( $customer, [ 'label' => 'Home', 'is_default_shipping' => true, 'is_default_billing' => true ] );
    $studio         = designAddress( $customer, [ 'label' => 'Studio' ] );

    $html = Livewire::test( Addresses::class, [ 'showTitle' => true ] )->html();

    expect( $html )->toContain( '<h1 class="ec-addresses__title">Saved addresses</h1>' )
        ->toContain( 'ec-address-book-card' )
        ->toContain( 'data-account-address-default="shipping"' )
        ->toContain( 'data-account-address-default="billing"' )
        ->toContain( 'data-account-address-make-default="shipping"' )
        ->toContain( 'data-account-address-delete="' . $studio->id . '"' )
        ->toMatch( '#data-scope="card" data-part="footer"#' )
        ->and( substr_count( $html, 'data-account-address-make-default=' ) )->toBe( 2 )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_ACCOUNT_SCREEN_CLASSES );

    expect( $home->id )->toBeInt();
} );

it( 'opens the address form in a dialog with its buttons in the footer', function (): void {
    shopper();

    $html = Livewire::test( Addresses::class )->call( 'add' )->html();

    expect( $html )->toContain( 'id="ec-account-address-form"' )
        ->toContain( 'form="ec-account-address-form"' )
        ->toContain( 'data-size="lg"' )
        ->not->toContain( 'modal-action' );
} );

it( 'asks before deleting in an alert dialog', function (): void {
    [ , $customer ] = shopper();
    $address        = designAddress( $customer, [ 'label' => 'Studio' ] );

    $html = Livewire::test( Addresses::class )->call( 'confirmDelete', $address->id )->html();

    expect( $html )->toContain( 'role="alertdialog"' )
        ->toContain( 'Delete this address?' )
        ->toContain( 'It will be removed from your address book. Orders that used it keep their copy.' )
        ->toContain( 'data-account-address-delete-confirm' );
} );

it( 'shows no addresses as the empty state in a card, with "Add an address" in the header too', function (): void {
    shopper();

    Livewire::test( Addresses::class, [ 'showTitle' => true ] )
        ->assertSeeHtml( 'data-empty-state-card' )
        ->assertSeeHtml( 'ec-addresses__add' )
        ->assertSeeHtml( 'data-account-address-add-empty' )
        ->assertSee( 'No saved addresses' );
} );

it( 'heads the address book page with "Saved addresses" as its only h1', function (): void {
    shopper();

    $html = $this->get( route( 'artisanpack.ecommerce.account.addresses' ) )->assertOk()->getContent();

    expect( substr_count( $html, '<h1' ) )->toBe( 1 )
        ->and( $html )->toContain( 'Saved addresses</h1>' );
} );

it( 'lists files with icon tiles and the order, and licence keys in the clipboard field (#149)', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [ 'order_number' => 'ORD-8001' ], $customer, [ makeProduct( 1500, [ 'name' => 'Glaze recipe book' ] ) ] );
    $item           = $order->items->first();
    DB::table( $item->getTable() )->where( 'id', $item->id )->update( [ 'product_snapshot' => json_encode( [ 'name' => 'Glaze recipe book', 'variant_name' => 'Studio and teaching' ] ) ] );
    $file = DigitalFile::factory()->create( [ 'product_id' => $item->product_id, 'label' => 'Glaze recipe book.pdf', 'version' => '2.1', 'path' => 'digital/glaze.pdf' ] );
    Storage::disk( 'local' )->put( $file->path, 'PDF bytes' );
    DigitalDownload::factory()->create( [ 'order_item_id' => $item->id, 'digital_file_id' => $file->id, 'downloads_remaining' => null, 'expires_at' => null ] );
    LicenseKey::factory()->create( [ 'order_item_id' => $item->id, 'key' => 'FKGL-7Q2M-X8RD-41PK', 'expires_at' => null ] );
    LicenseKey::factory()->create( [ 'order_item_id' => $item->id, 'is_revoked' => true ] );

    $html = Livewire::test( Downloads::class )->html();

    expect( $html )->toContain( 'Files and licence keys from your orders.' )
        ->toContain( 'apui-icon-tile' )
        ->toContain( 'Glaze recipe book.pdf' )
        ->toContain( 'Version 2.1' )
        ->toContain( 'Unlimited downloads' )
        ->toContain( 'Order ORD-8001' )
        ->toContain( 'data-account-download-button' )
        ->toContain( 'Studio and teaching' )
        ->toContain( 'data-scope="clipboard"' )
        ->toContain( 'data-variant="field"' )
        ->toContain( 'FKGL-7Q2M-X8RD-41PK' )
        ->toContain( 'data-account-licence-badge="active"' )
        ->toContain( 'data-account-licence-badge="revoked"' )
        ->toContain( 'This licence key has been revoked.' )
        ->and( substr_count( $html, 'data-scope="clipboard" data-part="field"' ) )->toBe( 1 )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_ACCOUNT_SCREEN_CLASSES );
} );

it( 'tells same-named files apart by their product, in the row and the button\'s name', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [], $customer, [ makeProduct( 1500, [ 'name' => 'Glaze recipe book' ] ), makeProduct( 900, [ 'name' => 'Kiln log template' ] ) ] );

    foreach ( $order->items as $item ) {
        $file = DigitalFile::factory()->create( [ 'product_id' => $item->product_id, 'label' => 'Manual.pdf', 'version' => '1.0', 'path' => 'digital/manual-' . $item->id . '.pdf' ] );
        DigitalDownload::factory()->create( [ 'order_item_id' => $item->id, 'digital_file_id' => $file->id ] );
    }

    Livewire::test( Downloads::class )
        ->assertSeeHtml( 'data-account-download-product>Glaze recipe book</span>' )
        ->assertSeeHtml( 'data-account-download-product>Kiln log template</span>' )
        ->assertSeeHtml( 'aria-label="Download Manual.pdf (Glaze recipe book)"' )
        ->assertSeeHtml( 'aria-label="Download Manual.pdf (Kiln log template)"' );
} );

it( 'leaves the product out when the file is named after it', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [], $customer, [ makeProduct( 1500, [ 'name' => 'Field guide' ] ) ] );
    $item           = $order->items->first();
    $file           = DigitalFile::factory()->create( [ 'product_id' => $item->product_id, 'label' => '', 'path' => 'digital/guide.pdf' ] );
    DigitalDownload::factory()->create( [ 'order_item_id' => $item->id, 'digital_file_id' => $file->id ] );

    Livewire::test( Downloads::class )
        ->assertSee( 'Field guide' )
        ->assertDontSeeHtml( 'data-account-download-product' )
        ->assertSeeHtml( 'aria-label="Download Field guide"' );
} );

it( 'marks an expired file with a badge instead of a button', function (): void {
    [ , $customer ] = shopper();
    $order          = placedOrder( [], $customer );
    $item           = $order->items->first();
    $file           = DigitalFile::factory()->create( [ 'product_id' => $item->product_id, 'label' => 'Kiln firing log.xlsx', 'path' => 'digital/log.xlsx' ] );
    DigitalDownload::factory()->create( [ 'order_item_id' => $item->id, 'digital_file_id' => $file->id, 'expires_at' => now()->subDay() ] );

    Livewire::test( Downloads::class )
        ->assertSeeHtml( 'data-account-download-badge="expired"' )
        ->assertSeeHtml( 'data-account-download-reason="expired"' )
        ->assertDontSeeHtml( 'data-account-download-button' );
} );

it( 'shows no downloads as the empty state in a card', function (): void {
    shopper();

    Livewire::test( Downloads::class )
        ->assertSeeHtml( 'data-empty-state-card' )
        ->assertSee( 'No downloads yet' );
} );

it( 'groups the profile into three cards with footer buttons (#150)', function (): void {
    shopper();
    config()->set( 'artisanpack.ecommerce-storefront-livewire.auth.password_route', 'password.edit' );
    Route::get( '/password', static fn (): string => 'password' )->name( 'password.edit' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    $html = Livewire::test( Profile::class )->html();

    expect( substr_count( $html, 'ec-profile__card' ) )->toBe( 3 )
        ->and( $html )->toContain( '(optional)' )
        ->toContain( 'class="ec-profile__fields"' )
        ->toContain( 'data-account-host-link="password"' )
        ->toContain( 'You can change your mind at any time.' )
        ->toContain( 'data-account-preference-needs-consent' )
        ->toMatch( '#data-scope="card" data-part="footer">\s*<div class="ec-profile__footer">#' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_ACCOUNT_SCREEN_CLASSES );
} );

it( 'shows the claim form in a card, under the lead (#151)', function (): void {
    shopper();

    $html = Livewire::test( Claim::class )->html();

    expect( $html )->toContain( 'class="ec-claim__lead"' )
        ->toContain( 'apui-card ec-claim__card' )
        ->toContain( 'class="ec-claim__fields"' )
        ->toContain( 'ec-claim__number' )
        ->not->toContain( 'data-account-claim-success' )
        ->and( classTokens( $html ) )->not->toContain( ...OLD_ACCOUNT_SCREEN_CLASSES );
} );

it( 'heads the claim page with "Claim orders" as its only h1', function (): void {
    verifiedShopper();

    $html = $this->get( route( 'artisanpack.ecommerce.account.claim' ) )->assertOk()->getContent();

    expect( substr_count( $html, '<h1' ) )->toBe( 1 )
        ->and( $html )->toContain( 'Claim orders' );
} );
