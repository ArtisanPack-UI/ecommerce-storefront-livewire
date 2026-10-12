<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\Drawer;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\HeaderButton;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Support\Facades\View;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Fixtures\Media\FakeMedia;

/**
 * The shopper's cart with `$quantity` of a product called `$name`.
 */
function drawerCartWith( string $name = 'Mug', int $quantity = 1, int $price = 1000 ): Cart
{
    $cart = app( StorefrontCart::class )->current( true );
    app( StorefrontCartService::class )->addItem( $cart, makeProduct( $price, [ 'name' => $name ] )->id, null, $quantity );

    return $cart->refresh();
}

it( 'renders the drawer, opened and closed by browser events', function (): void {
    drawerCartWith( 'Mug', 2, 1250 );

    Livewire::test( Drawer::class )
        ->dispatch( 'ecommerce-cart-open' )
        ->assertOk()
        ->assertSeeHtml( 'x-on:ecommerce-cart-open.window="open = true"' )
        ->assertSeeHtml( 'x-on:ecommerce-cart-close.window="close()"' )
        ->assertSeeHtml( '@keydown.window.escape="close()"' )
        ->assertSeeHtml( 'x-trap="open"' )
        ->assertSeeHtml( 'drawer-end' )
        ->assertSeeHtml( 'role="dialog"' )
        ->assertSeeHtml( 'aria-modal="true"' )
        ->assertSeeHtml( 'aria-labelledby="ecommerce-cart-drawer-title"' )
        ->assertSeeHtml( 'aria-label="Close cart"' )
        ->assertSee( 'Your cart' )
        ->assertSee( '2 items' )
        ->assertSee( 'Mug' )
        ->assertSee( '$25.00' )
        ->assertSee( 'Subtotal' )
        ->assertSee( 'View cart' )
        ->assertSee( 'Checkout' );
} );

it( 'says when the cart is empty', function (): void {
    Livewire::test( Drawer::class )
        ->assertSee( 'Your cart is empty' )
        ->assertSee( '0 items' )
        ->assertDontSeeHtml( 'data-cart-drawer-checkout' );
} );

it( 'changes a quantity and removes a line from the drawer', function (): void {
    $line = drawerCartWith()->items()->sole();

    Livewire::test( Drawer::class )
        ->dispatch( 'ecommerce-cart-open' )
        ->set( 'quantities.' . $line->id, 3 )
        ->assertDispatched( 'ecommerce-cart-updated', count: 3 )
        ->assertSee( '$30.00' )
        ->call( 'removeLine', $line->id )
        ->assertDispatched( 'ecommerce-cart-updated', count: 0 )
        ->assertSee( 'Mug was removed from your cart.' )
        ->call( 'undoRemove' )
        ->assertDispatched( 'ecommerce-cart-updated', count: 3 );
} );

it( 'shows the engine\'s refusal on the line', function (): void {
    $cart = drawerCartWith();
    $line = $cart->items()->sole();

    setStock( $line->product, 1 );

    Livewire::test( Drawer::class )
        ->dispatch( 'ecommerce-cart-open' )
        ->set( 'quantities.' . $line->id, 9 )
        ->assertHasErrors( 'quantities.' . $line->id )
        ->assertSet( 'quantities.' . $line->id, 1 );
} );

it( 'refreshes its lines when the cart changes elsewhere', function (): void {
    $component = Livewire::test( Drawer::class )->dispatch( 'ecommerce-cart-open' )->assertSee( 'Your cart is empty' );

    drawerCartWith( 'Teapot' );

    $component->dispatch( 'ecommerce-cart-updated', count: 1 )
        ->assertSee( 'Teapot' );
} );

it( 'hides checkout while a line can\'t be bought', function (): void {
    $cart = drawerCartWith( 'Lamp' );
    $cart->items()->sole()->product->forceFill( [ 'status' => 'draft' ] )->save();

    Livewire::test( Drawer::class )
        ->dispatch( 'ecommerce-cart-open' )
        ->assertSee( 'This product is no longer available.' )
        ->assertDontSeeHtml( 'data-cart-drawer-checkout' );
} );

it( 'lists no lines until it first opens, keeping the item count in its title', function (): void {
    drawerCartWith( 'Mug', 2, 1250 );

    $component = Livewire::test( Drawer::class )
        ->assertSet( 'loaded', false )
        ->assertSet( 'quantities', [] )
        ->assertSee( '2 items' )
        ->assertSeeHtml( 'data-cart-loading' )
        ->assertDontSee( 'Mug' )
        ->assertDontSeeHtml( 'data-cart-empty' )
        ->assertDontSeeHtml( 'data-cart-drawer-checkout' );

    // The title count follows the cart before the drawer opens.
    drawerCartWith( 'Teapot' );

    $component->dispatch( 'ecommerce-cart-updated', count: 3 )
        ->assertSee( '3 items' )
        ->assertSet( 'loaded', false )
        ->dispatch( 'ecommerce-cart-open' )
        ->assertSet( 'loaded', true )
        ->assertDontSeeHtml( 'data-cart-loading' )
        ->assertSee( 'Mug' )
        ->assertSee( 'Teapot' )
        ->assertSee( '3 items' );
} );

it( 'looks up no line images until it first opens', function (): void {
    require_once dirname( __DIR__, 3 ) . '/Fixtures/Media/MediaLibraryStub.php';

    ProductImages::fake( true );
    FakeMedia::register( 41, new FakeMedia( 800, 800, 'A mug' ) );

    $cart = app( StorefrontCart::class )->current( true );
    app( StorefrontCartService::class )->addItem( $cart, makeProduct( 1000, [ 'name' => 'Mug', 'featured_image_media_id' => 41 ] )->id, null, 1 );

    try {
        Livewire::test( Drawer::class )
            ->assertDontSeeHtml( 'https://media.example.test/' )
            ->dispatch( 'ecommerce-cart-open' )
            ->assertSeeHtml( 'https://media.example.test/' );
    } finally {
        FakeMedia::$items = [];
        ProductImages::fake( null );
    }
} );

it( 'keeps the loaded flag out of the browser\'s reach', function (): void {
    expect( fn () => Livewire::test( Drawer::class )->set( 'loaded', true ) )
        ->toThrow( CannotUpdateLockedPropertyException::class );
} );

it( 'keeps the undo line out of the browser\'s reach', function (): void {
    expect( fn () => Livewire::test( Drawer::class )->set( 'removedLine', [ 'product_id' => 1, 'variant_id' => null, 'quantity' => 1, 'options' => [], 'name' => 'X' ] ) )
        ->toThrow( CannotUpdateLockedPropertyException::class );
} );

it( 'is mounted once by the global partial, in the package layout and a host layout', function ( ?string $layout ): void {
    View::addNamespace( 'host', __DIR__ . '/../../../Fixtures/views/host' );

    if ( null !== $layout ) {
        config()->set( 'artisanpack.ecommerce-storefront-livewire.storefront.layout', $layout );
    }

    $html = $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertOk()
        ->assertSeeLivewire( Drawer::class )
        ->getContent();

    // The drawer's root; the header button's script also names the attribute.
    expect( preg_match_all( '/data-ecommerce-cart-drawer\s+x-data/', $html ) )->toBe( 1 );
} )->with( [
    'package layout' => [ null ],
    'host layout'    => [ 'host::layout' ],
] );

it( 'shows the header cart button with the count and an accessible name', function (): void {
    drawerCartWith( 'Mug', 3 );

    Livewire::test( HeaderButton::class )
        ->assertSet( 'count', 3 )
        ->assertSeeHtml( 'aria-label="Cart, 3 items"' )
        ->assertSeeHtml( 'aria-haspopup="dialog"' )
        ->assertSeeHtml( 'aria-controls="ecommerce-cart-drawer-panel"' )
        ->assertSeeHtml( '$dispatch( \'ecommerce-cart-open\' )' )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.storefront.cart' ) . '"' )
        ->assertSeeHtml( 'data-cart-count' );
} );

it( 'updates the header count when the cart changes, recounting rather than trusting the event', function (): void {
    $component = Livewire::test( HeaderButton::class )
        ->assertSet( 'count', 0 )
        ->assertSeeHtml( 'aria-label="Cart, 0 items"' )
        ->assertDontSeeHtml( 'data-cart-count' );

    drawerCartWith( 'Mug', 1 );

    $component->dispatch( 'ecommerce-cart-updated', count: 99 )
        ->assertSet( 'count', 1 )
        ->assertSeeHtml( 'aria-label="Cart, 1 item"' );

    expect( fn () => $component->set( 'count', 5 ) )->toThrow( CannotUpdateLockedPropertyException::class );
} );

it( 'renders the header button in the package layout', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertSeeLivewire( HeaderButton::class )
        ->assertSee( 'data-header-action="cart"', false );
} );
