<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\BackInStockSubscription;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductChild;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\BundledForm;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\GroupedForm;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\SimpleForm;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\VariableForm;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Show;
use Livewire\Livewire;

/*
 * The add-to-cart row's "Notify me" back-in-stock signup (#155).
 */

beforeEach( function (): void {
    if ( ! engineHasBackInStock() ) {
        $this->markTestSkipped( 'Needs engine 1.4 (BackInStockService).' );
    }

    $this->mug = makeProduct( 2800, [ 'name' => 'Oxbow mug' ] );
    setStock( $this->mug, 0 );
} );

it( 'replaces the quantity and button with the Notify me alert when the product is out of stock', function (): void {
    $form = 'ec-product-' . $this->mug->id;

    Livewire::test( SimpleForm::class, [ 'product' => $this->mug ] )
        ->assertSeeHtml( 'data-notify-me' )
        ->assertSeeHtml( 'apui-alert' )
        ->assertSee( 'This product is sold out' )
        ->assertSee( 'Leave your email and we\'ll tell you as soon as it\'s back in stock.' )
        ->assertSeeHtml( 'id="' . $form . '-notify-email"' )
        ->assertSeeHtml( 'aria-label="Email address"' )
        ->assertSeeHtml( 'autocomplete="email"' )
        ->assertSee( 'Notify me' )
        ->assertDontSeeHtml( 'data-add-to-cart>' )
        ->assertDontSeeHtml( 'data-add-blocked' )
        ->assertDontSeeHtml( 'aria-label="Increase quantity"' );
} );

it( 'does not offer it for an unpriced product or one in stock', function ( Closure $make ): void {
    Livewire::test( SimpleForm::class, [ 'product' => $make() ] )
        ->assertDontSeeHtml( 'data-notify-me' );
} )->with( [
    'unpriced' => [ function (): Product {
        $product = Product::factory()->create();
        setStock( $product, 0 );

        return $product;
    } ],
    'in stock' => [ fn (): Product => makeProduct() ],
] );

it( 'signs the shopper up, confirms in place, and announces it', function (): void {
    Livewire::test( SimpleForm::class, [ 'product' => $this->mug ] )
        ->set( 'notifyEmail', '  Sam@Example.com ' )
        ->call( 'notifyMe' )
        ->assertHasNoErrors()
        ->assertSet( 'notifySubscribed', true )
        ->assertSet( 'announcement', 'We\'ll email you when it\'s back.' )
        ->assertSeeHtml( 'data-notify-me-done' )
        ->assertDontSeeHtml( 'data-notify-me-form' );

    $subscription = BackInStockSubscription::query()->sole();

    expect( $subscription->email )->toBe( 'sam@example.com' )
        ->and( (int) $subscription->product_id )->toBe( (int) $this->mug->id )
        ->and( $subscription->product_variant_id )->toBeNull()
        ->and( $subscription->locale )->toBe( 'en' );
} );

it( 'treats a second signup for the same email as a success', function (): void {
    foreach ( [ 1, 2 ] as $attempt ) {
        Livewire::test( SimpleForm::class, [ 'product' => $this->mug ] )
            ->set( 'notifyEmail', 'sam@example.com' )
            ->call( 'notifyMe' )
            ->assertHasNoErrors()
            ->assertSet( 'notifySubscribed', true );
    }

    expect( BackInStockSubscription::query()->count() )->toBe( 1 );
} );

it( 'validates the email and links the error to the field', function ( string $email, string $message ): void {
    $form = 'ec-product-' . $this->mug->id;

    $html = Livewire::test( SimpleForm::class, [ 'product' => $this->mug ] )
        ->set( 'notifyEmail', $email )
        ->call( 'notifyMe' )
        ->assertHasErrors( [ 'notifyEmail' => $message ] )
        ->assertSet( 'notifySubscribed', false )
        ->assertSeeHtml( 'data-notify-me-error-announcement' )
        ->assertSee( 'Not signed up: ' . $message )
        ->assertDontSeeHtml( 'data-cart-error-announcement' )
        ->html();

    expect( $html )->toMatch( '/id="' . $form . '-notify-email"[^>]*aria-invalid="true"/' )
        ->toMatch( '/aria-describedby="[^"]*' . $form . '-notify-email-error/' );

    expect( BackInStockSubscription::query()->count() )->toBe( 0 );
} )->with( [
    'empty'   => [ '', 'Enter your email address.' ],
    'invalid' => [ 'not-an-email', 'Enter an email address like name@example.com.' ],
] );

it( 'rate-limits signups', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.backInStock.subscribe.per_ip', 1 );

    $component = Livewire::test( SimpleForm::class, [ 'product' => $this->mug ] )
        ->set( 'notifyEmail', 'one@example.com' )
        ->call( 'notifyMe' )
        ->set( 'notifySubscribed', false )
        ->set( 'notifyEmail', 'two@example.com' )
        ->call( 'notifyMe' )
        ->assertSet( 'notifySubscribed', false );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Too many attempts' )
        ->and( BackInStockSubscription::query()->count() )->toBe( 1 );
} );

it( 'rate-limits signups per email address', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.backInStock.subscribe.per_email', 1 );

    $other = makeOutOfStock();

    Livewire::test( SimpleForm::class, [ 'product' => $this->mug ] )
        ->set( 'notifyEmail', 'sam@example.com' )
        ->call( 'notifyMe' )
        ->assertSet( 'notifySubscribed', true );

    $component = Livewire::test( SimpleForm::class, [ 'product' => $other ] )
        ->set( 'notifyEmail', 'Sam@Example.com' )
        ->call( 'notifyMe' )
        ->assertSet( 'notifySubscribed', false );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Too many attempts' );

    Livewire::test( SimpleForm::class, [ 'product' => $other ] )
        ->set( 'notifyEmail', 'alex@example.com' )
        ->call( 'notifyMe' )
        ->assertSet( 'notifySubscribed', true );
} );

it( 'prefills a signed-in shopper\'s email read-only and signs them up with it', function (): void {
    [ , $customer ] = shopper( [ 'email' => 'regular@example.com' ] );

    $html = Livewire::test( SimpleForm::class, [ 'product' => $this->mug ] )
        ->assertSet( 'notifyEmail', 'regular@example.com' )
        ->set( 'notifyEmail', 'someone-else@example.com' )
        ->call( 'notifyMe' )
        ->assertHasNoErrors()
        ->html();

    $subscription = BackInStockSubscription::query()->sole();

    expect( $subscription->email )->toBe( 'regular@example.com' )
        ->and( (int) $subscription->customer_id )->toBe( (int) $customer->id );

    expect( Livewire::test( SimpleForm::class, [ 'product' => makeOutOfStock() ] )->html() )
        ->toMatch( '/id="ec-product-\d+-notify-email"[^>]*readonly/' );
} );

it( 'offers it for a sold-out variant, for that variant, but not while a choice is missing', function (): void {
    $made   = makeVariableProduct( [
        'red/m'  => [ 'price' => 2000, 'stock' => 5 ],
        'red/xl' => [ 'price' => 2200, 'stock' => 0 ],
    ] );
    $colour = $made['groups']['colour']->id;
    $size   = $made['groups']['size']->id;

    $component = Livewire::test( VariableForm::class, [ 'product' => $made['product'] ] )
        ->set( "selected.{$colour}", (string) $made['values']['red']->id )
        ->assertDontSeeHtml( 'data-notify-me' )
        ->set( "selected.{$size}", (string) $made['values']['xl']->id )
        ->assertSeeHtml( 'data-notify-me' )
        ->assertSee( 'This option is sold out' )
        ->set( 'notifyEmail', 'sam@example.com' )
        ->call( 'notifyMe' )
        ->assertSet( 'notifySubscribed', true );

    expect( (int) BackInStockSubscription::query()->sole()->product_variant_id )->toBe( (int) $made['variants']['red/xl']->id );

    $component->set( "selected.{$size}", (string) $made['values']['m']->id )
        ->assertSet( 'notifySubscribed', false )
        ->assertDontSeeHtml( 'data-notify-me' );
} );

it( 'never offers it on a grouped product', function (): void {
    $group = Product::factory()->grouped()->create();
    setStock( $group, 0 );
    ProductChild::factory()->create( [ 'parent_product_id' => $group->id, 'child_product_id' => $this->mug->id ] );

    Livewire::test( GroupedForm::class, [ 'product' => $group ] )
        ->assertDontSeeHtml( 'data-notify-me' );
} );

it( 'offers it on a sold-out bundle', function (): void {
    $bundle = Product::factory()->bundled()->create();
    ArtisanPackUI\Ecommerce\Models\ProductPrice::factory()->forPriceable( $bundle )->create( [ 'currency' => 'USD', 'price_amount' => 5000, 'compare_at_amount' => null ] );
    ProductChild::factory()->create( [ 'parent_product_id' => $bundle->id, 'child_product_id' => $this->mug->id, 'quantity' => 1 ] );

    Livewire::test( BundledForm::class, [ 'product' => $bundle ] )
        ->assertSeeHtml( 'data-notify-me' );
} );

it( 'keeps the disabled button and reason when the filter turns it off', function (): void {
    withoutBackInStock();

    Livewire::test( SimpleForm::class, [ 'product' => $this->mug ] )
        ->assertDontSeeHtml( 'data-notify-me' )
        ->assertSee( 'This product is out of stock.' )
        ->call( 'notifyMe' )
        ->assertHasNoErrors();

    expect( BackInStockSubscription::query()->count() )->toBe( 0 );
} );

it( 'leaves the product page\'s own sold-out notice out while the form offers the signup', function (): void {
    Livewire::test( Show::class, [ 'product' => $this->mug ] )
        ->assertDontSeeHtml( 'data-product-sold-out' );

    withoutBackInStock();

    Livewire::test( Show::class, [ 'product' => $this->mug ] )
        ->assertSeeHtml( 'data-product-sold-out' );
} );

/**
 * Another priced, sold-out product.
 */
function makeOutOfStock(): Product
{
    $product = makeProduct( 1500 );
    setStock( $product, 0 );

    return $product;
}
