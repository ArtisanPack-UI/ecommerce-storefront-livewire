<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\CurrencyResolver;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Services\StorefrontCartService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Currency\Switcher;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CurrencyNames;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ToastPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;

beforeEach( function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'USD', 'EUR' ] );
} );

/**
 * A product priced in USD and EUR.
 */
function twoCurrencyProduct( string $name = 'Mug', int $usd = 1000, int $eur = 900 ): Product
{
    $product = makeProduct( $usd, [ 'name' => $name ] );
    ProductPrice::factory()->forPriceable( $product )->create( [ 'currency' => 'EUR', 'price_amount' => $eur ] );

    return $product;
}

it( 'lists the enabled currencies by name, code, and symbol', function (): void {
    Livewire::test( Switcher::class )
        ->assertOk()
        ->assertSet( 'currency', 'USD' )
        ->assertSee( 'Currency' )
        ->assertSee( 'US Dollar (USD, $)' )
        ->assertSee( 'Euro (EUR, €)' )
        ->assertSeeHtml( 'wire:model.live="currency"' );
} );

it( 'is hidden when the store sells in one currency', function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [] );

    Livewire::test( Switcher::class )
        ->assertDontSeeHtml( 'ecommerce-currency-switcher"' )
        ->assertDontSee( 'Currency' );
} );

it( 'switches the shopper\'s currency and reloads the page with a toast', function (): void {
    $remembered = [];

    $this->app->instance( CurrencyResolver::class, new class( $remembered ) implements CurrencyResolver {
        public function __construct( private array &$remembered )
        {
        }

        public function resolve( Request $request ): string
        {
            return 'USD';
        }

        public function remember( Request $request, string $currency ): string
        {
            return $this->remembered[] = $currency;
        }
    } );

    $component = Livewire::test( Switcher::class )
        ->set( 'currency', 'eur' )
        ->assertHasNoErrors()
        ->assertSet( 'currency', 'EUR' );

    expect( $remembered )->toBe( [ 'EUR' ] )
        ->and( Cookie::queued( 'ecommerce_currency' )?->getValue() )->toBe( 'EUR' )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'window.location.reload()' )
        ->and( session( ToastPayload::SESSION_KEY )['toast']['title'] )->toBe( 'Prices are now shown in EUR.' )
        ->and( session( ToastPayload::SESSION_KEY )['toast']['description'] )->toBeNull();
} );

it( 'prices the catalog in the chosen currency on the next request', function (): void {
    twoCurrencyProduct( 'Mug', 1000, 900 );

    Livewire::test( Switcher::class )->set( 'currency', 'EUR' );

    $this->withCookie( 'ecommerce_currency', Cookie::queued( 'ecommerce_currency' )->getValue() )
        ->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertSee( '€9.00' )
        ->assertDontSee( '$10.00' );
} );

it( 'reprices an existing cart and says its total changed', function (): void {
    $cart = app( StorefrontCart::class )->current( true );
    app( StorefrontCartService::class )->addItem( $cart, twoCurrencyProduct()->id, null, 2 );

    Livewire::test( Switcher::class )
        ->set( 'currency', 'EUR' )
        ->assertDispatched( 'ecommerce-cart-updated', count: 2 );

    $cart = Cart::query()->sole();

    expect( $cart->currency )->toBe( 'EUR' )
        ->and( $cart->total_amount )->toBe( 1800 )
        ->and( session( ToastPayload::SESSION_KEY )['toast']['description'] )->toBe( 'Your cart was re-priced in EUR, so its total has changed.' );
} );

it( 'keeps the old currency when the cart can\'t be repriced', function (): void {
    $cart = app( StorefrontCart::class )->current( true );
    app( StorefrontCartService::class )->addItem( $cart, makeProduct( 1000, [ 'name' => 'USD only' ] )->id, null, 1 );

    $component = Livewire::test( Switcher::class )
        ->set( 'currency', 'EUR' )
        ->assertSet( 'currency', 'USD' )
        ->assertNotDispatched( 'ecommerce-cart-updated' );

    expect( Cart::query()->sole()->currency )->toBe( 'USD' )
        ->and( app( StorefrontCart::class )->currency() )->toBe( 'USD' )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Your cart can' )
        ->not->toContain( 'window.location.reload()' );
} );

it( 'rejects a currency the store doesn\'t sell in', function ( string $currency ): void {
    $component = Livewire::test( Switcher::class )
        ->set( 'currency', $currency )
        ->assertHasErrors( 'currency' )
        ->assertSet( 'currency', 'USD' );

    expect( Cookie::queued( 'ecommerce_currency' ) )->toBeNull()
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->not->toContain( 'window.location.reload()' );
} )->with( [ 'GBP', 'not-a-code', '' ] );

it( 'does nothing when the current currency is chosen again', function (): void {
    $component = Livewire::test( Switcher::class )->set( 'currency', 'USD' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->not->toContain( 'window.location.reload()' )
        ->and( session()->has( ToastPayload::SESSION_KEY ) )->toBeFalse();
} );

it( 'rate limits repricing the cart', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.cart.mutate.per_cart', 1 );

    $cart = app( StorefrontCart::class )->current( true );
    app( StorefrontCartService::class )->addItem( $cart, twoCurrencyProduct()->id, null, 1 );

    $component = Livewire::test( Switcher::class )
        ->set( 'currency', 'EUR' )
        ->set( 'currency', 'USD' )
        ->assertSet( 'currency', 'USD' );

    // The second switch was throttled: the cart stays in EUR.
    expect( Cart::query()->sole()->currency )->toBe( 'EUR' )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Too many attempts' );
} );

it( 'names currencies in the shopper\'s language', function (): void {
    expect( CurrencyNames::label( 'EUR', 'de' ) )->toBe( 'Euro (EUR, €)' )
        ->and( CurrencyNames::label( 'USD', 'fr' ) )->toContain( 'USD' )
        ->and( CurrencyNames::label( 'CHF', 'en' ) )->toBe( 'Swiss Franc (CHF)' )
        ->and( CurrencyNames::label( 'ZZZ', 'en' ) )->toBe( 'ZZZ' );
} );

it( 'shows a flashed toast after the reload', function (): void {
    session()->put( ToastPayload::SESSION_KEY, ToastPayload::make( 'success', '<b>Prices</b> changed', null, 'alert-success' ) );

    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertOk()
        ->assertSee( 'data-ecommerce-flash-toast', false )
        ->assertSee( 'livewire:initialized', false )
        ->assertDontSee( '<b>Prices</b>', false );

    expect( session()->has( ToastPayload::SESSION_KEY ) )->toBeFalse();
} );

it( 'is in the package header', function (): void {
    $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )
        ->assertSeeLivewire( Switcher::class )
        ->assertSee( 'Euro (EUR, €)' );
} );
