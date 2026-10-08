<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Exceptions\CartOperationException;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Services\CurrentCart;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\Ecommerce\Support\GuestCartCookie;
use ArtisanPackUI\Ecommerce\ValueObjects\PendingCartMerge;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Cart\MergePrompt;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Fixtures\User;

beforeEach( function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'USD', 'EUR' ] );
} );

/**
 * A product priced in USD and EUR.
 */
function dualPricedProduct( string $name ): Product
{
    $product = makeProduct( 1000, [ 'name' => $name ] );
    ProductPrice::factory()->forPriceable( $product )->create( [ 'currency' => 'EUR', 'price_amount' => 900 ] );

    return $product;
}

/**
 * A guest cart in `$currency` holding one `$product`.
 */
function guestCartWith( Product $product, string $currency = 'USD' ): Cart
{
    $cart = app( StorefrontCartService::class )->create( $currency );
    app( StorefrontCartService::class )->addItem( $cart, $product->id, null, 1 );

    return $cart->fresh();
}

/**
 * `$user`'s account cart in `$currency` holding one `$product`.
 */
function accountCartWith( User $user, Product $product, string $currency ): Cart
{
    $customer = app( CustomerService::class )->customerForUser( $user, true );
    $cart     = app( StorefrontCartService::class )->create( $currency, $customer->email, $customer );
    app( StorefrontCartService::class )->addItem( $cart, $product->id, null, 1 );

    return $cart->fresh();
}

/**
 * Signs `$user` in with the guest cookie on the request, as the host's
 * login screen would, so the engine's login listener runs the merge.
 */
function signInWithGuestCart( User $user, Cart $guest ): void
{
    $request = app( 'request' );
    $request->cookies->set( GuestCartCookie::name(), $guest->token );
    $request->setLaravelSession( session()->driver() );

    Auth::login( $user );
    app()->forgetScopedInstances();
}

it( 'keeps a guest\'s items after they sign in (same currency)', function (): void {
    $user  = makeUser();
    $mug   = dualPricedProduct( 'Mug' );
    $guest = guestCartWith( $mug );

    signInWithGuestCart( $user, $guest );

    $cart = app( StorefrontCart::class )->current();

    expect( $cart )->not->toBeNull()
        ->and( $cart->customer_id )->not->toBeNull()
        ->and( $cart->items()->pluck( 'product_id' )->all() )->toBe( [ $mug->id ] )
        ->and( session()->has( PendingCartMerge::SESSION_KEY ) )->toBeFalse();

    Livewire::actingAs( $user )->test( MergePrompt::class )->assertSet( 'open', false );
} );

it( 'combines a guest cart into the account cart on sign-in (same currency)', function (): void {
    $user = makeUser();
    $mug  = dualPricedProduct( 'Mug' );
    $tea  = dualPricedProduct( 'Tea' );

    accountCartWith( $user, $tea, 'USD' );
    signInWithGuestCart( $user, guestCartWith( $mug ) );

    expect( app( StorefrontCart::class )->current()->items()->pluck( 'product_id' )->sort()->values()->all() )
        ->toBe( collect( [ $mug->id, $tea->id ] )->sort()->values()->all() );
} );

it( 'asks a shopper whose carts are in different currencies what to do', function (): void {
    $user = makeUser();

    accountCartWith( $user, dualPricedProduct( 'Tea' ), 'EUR' );
    signInWithGuestCart( $user, guestCartWith( dualPricedProduct( 'Mug' ), 'USD' ) );

    expect( session()->has( PendingCartMerge::SESSION_KEY ) )->toBeTrue();

    Livewire::actingAs( $user )
        ->test( MergePrompt::class )
        ->assertSet( 'open', true )
        ->assertSet( 'guestCurrency', 'USD' )
        ->assertSet( 'accountCurrency', 'EUR' )
        ->assertSee( 'Combine your carts?' )
        ->assertSee( 'Keep USD' )
        ->assertSee( 'Switch to EUR' )
        ->assertSee( "Don't merge" );
} );

it( 'applies each choice to the pending merge', function ( string $resolution, string $currency, int $lines ): void {
    $user = makeUser();

    accountCartWith( $user, dualPricedProduct( 'Tea' ), 'EUR' );
    signInWithGuestCart( $user, guestCartWith( dualPricedProduct( 'Mug' ), 'USD' ) );

    Livewire::actingAs( $user )
        ->test( MergePrompt::class )
        ->call( 'resolve', $resolution )
        ->assertSet( 'open', false )
        ->assertDispatched( 'ecommerce-cart-updated', count: $lines );

    $cart = app( CurrentCart::class )->accountCart( app( CustomerService::class )->customerForUser( $user ) );

    expect( $cart->currency )->toBe( $currency )
        ->and( $cart->items()->count() )->toBe( $lines )
        ->and( session()->has( PendingCartMerge::SESSION_KEY ) )->toBeFalse();
} )->with( [
    'keep the guest currency'            => [ 'keep_guest_currency', 'USD', 2 ],
    'switch to the account\'s'           => [ 'switch_to_account_currency', 'EUR', 2 ],
    'don\'t merge (keep the saved cart)' => [ 'cancel_merge', 'EUR', 1 ],
] );

it( 'ignores an unknown choice and stays open', function (): void {
    $user = makeUser();

    accountCartWith( $user, dualPricedProduct( 'Tea' ), 'EUR' );
    signInWithGuestCart( $user, guestCartWith( dualPricedProduct( 'Mug' ), 'USD' ) );

    Livewire::actingAs( $user )
        ->test( MergePrompt::class )
        ->call( 'resolve', 'merge_everything' )
        ->assertSet( 'open', true )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    expect( session()->has( PendingCartMerge::SESSION_KEY ) )->toBeTrue();
} );

it( 'stays closed for guests and when nothing is pending', function (): void {
    Livewire::test( MergePrompt::class )->assertSet( 'open', false )->assertDontSee( 'Combine your carts?' );
    Livewire::actingAs( makeUser() )->test( MergePrompt::class )->assertSet( 'open', false );
} );

it( 'closes without changes when the merge was already resolved elsewhere', function (): void {
    $user = makeUser();

    accountCartWith( $user, dualPricedProduct( 'Tea' ), 'EUR' );
    signInWithGuestCart( $user, guestCartWith( dualPricedProduct( 'Mug' ), 'USD' ) );

    $component = Livewire::actingAs( $user )->test( MergePrompt::class );

    session()->forget( PendingCartMerge::SESSION_KEY );

    $component->call( 'resolve', 'keep_guest_currency' )
        ->assertSet( 'open', false )
        ->assertNotDispatched( 'ecommerce-cart-updated' );
} );

it( 'explains an engine failure and closes', function (): void {
    $user = makeUser();

    accountCartWith( $user, dualPricedProduct( 'Tea' ), 'EUR' );
    signInWithGuestCart( $user, guestCartWith( dualPricedProduct( 'Mug' ), 'USD' ) );

    // A proxy over the real resolver that fails only the merge itself.
    $currentCart = Mockery::mock( app( CurrentCart::class ) );
    $currentCart->shouldReceive( 'resolvePendingMerge' )->once()->andThrow( new CartOperationException( 'cart', 'cart-not-found', 'That cart could not be found.' ) );
    $this->instance( CurrentCart::class, $currentCart );
    app()->forgetScopedInstances();

    $component = Livewire::actingAs( $user )
        ->test( MergePrompt::class )
        ->call( 'resolve', 'keep_guest_currency' )
        ->assertSet( 'open', false )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )
        ->toContain( 'Your carts could not be combined.' )
        ->toContain( 'That cart could not be found.' );
} );

it( 'throttles repeated choices per shopper', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.cart.mutate.per_cart', 1 );

    $user = makeUser();

    accountCartWith( $user, dualPricedProduct( 'Tea' ), 'EUR' );
    signInWithGuestCart( $user, guestCartWith( dualPricedProduct( 'Mug' ), 'USD' ) );

    // Spend the shopper's one allowed cart change.
    app( ArtisanPackUI\Ecommerce\RateLimiting\EcommerceRateLimiter::class )->hit(
        'ecommerce.cart.mutate',
        app( StorefrontCart::class )->subject()->toRequest(),
    );

    Livewire::actingAs( $user )
        ->test( MergePrompt::class )
        ->call( 'resolve', 'keep_guest_currency' )
        ->assertSet( 'open', true )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    expect( session()->has( PendingCartMerge::SESSION_KEY ) )->toBeTrue();
} );

it( 'keeps the currencies it shows out of the browser\'s reach', function (): void {
    $user = makeUser();

    accountCartWith( $user, dualPricedProduct( 'Tea' ), 'EUR' );
    signInWithGuestCart( $user, guestCartWith( dualPricedProduct( 'Mug' ), 'USD' ) );

    expect( fn () => Livewire::actingAs( $user )->test( MergePrompt::class )->set( 'guestCurrency', '<script>' ) )
        ->toThrow( CannotUpdateLockedPropertyException::class );
} );
