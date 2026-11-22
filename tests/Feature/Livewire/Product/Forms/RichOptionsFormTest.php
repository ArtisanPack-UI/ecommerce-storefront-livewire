<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\Ecommerce\Services\PriceQuoteService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product\Forms\OptionsForm;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\Fixtures\ProductTypes\ConfiguredPrintProductType;

/*
 * The richer engine 1.4 option schema in the generic purchase form (#66):
 * swatches, choice cards, price differences, disabled choices,
 * `meta.disables`, `visible_when`, and the live quote.
 */

beforeEach( function (): void {
    if ( ! class_exists( PriceQuoteService::class ) ) {
        $this->markTestSkipped( 'Needs engine 1.4 (PriceQuoteService).' );
    }

    app( ProductTypeRegistry::class )->register( ConfiguredPrintProductType::KEY, new ConfiguredPrintProductType() );

    $this->print = Product::factory()->create( [ 'type' => ConfiguredPrintProductType::KEY, 'name' => 'Harbour print' ] );
    ProductPrice::factory()->forPriceable( $this->print )->create( [ 'currency' => 'USD', 'price_amount' => 6000, 'compare_at_amount' => null ] );
    setStock( $this->print, 10 );

    $this->field = fn ( string $name ): string => 'ec-product-' . $this->print->id . '-option-' . $name;
} );

it( 'renders a swatch field with color and image dots, price differences, and a disabled choice with its hint', function (): void {
    $html = Livewire::test( OptionsForm::class, [ 'product' => $this->print ] )
        ->assertSeeHtml( 'data-swatches="' . ( $this->field )( 'frame' ) . '"' )
        ->assertSeeHtml( 'style="background-color: #2b2118"' )
        ->assertSeeHtml( 'src="https://example.com/linen.jpg"' )
        ->assertSee( 'Black oak' )
        ->assertSeeHtml( 'data-swatch-meta>+$15.00' )
        ->assertSee( 'Back in January' )
        ->html();

    expect( $html )->toMatch( '/value="walnut"[^>]*\sdisabled/' );
} );

it( 'renders a cards radio as choice cards with the image, hint, price difference and a check', function (): void {
    Livewire::test( OptionsForm::class, [ 'product' => $this->print ] )
        ->assertSeeHtml( 'data-choice-cards="' . ( $this->field )( 'size' ) . '"' )
        ->assertSeeHtml( 'data-choice-card="small"' )
        ->assertSeeHtml( 'src="https://example.com/small.jpg"' )
        ->assertSeeHtml( 'alt="An 8 by 10 print on a desk"' )
        ->assertSee( 'Desk or shelf' )
        ->assertSeeHtml( 'data-choice-card-meta><bdi>+$45.00</bdi>' )
        ->assertSeeHtml( 'ec-choice-card__check"' )
        ->assertSeeHtml( 'id="' . ( $this->field )( 'size' ) . '-hint"' )
        ->assertSee( 'Inches, before framing.' );
} );

it( 'shows a plain radio\'s hint and price difference through the design-system RadioGroup', function (): void {
    Livewire::test( OptionsForm::class, [ 'product' => $this->print ] )
        ->assertSeeHtml( 'apui-radio-group' )
        ->assertSee( 'Cotton, archival' )
        ->assertSee( '+$18.00' );
} );

it( 'shows a field only while its visible_when conditions hold', function (): void {
    Livewire::test( OptionsForm::class, [ 'product' => $this->print ] )
        ->assertDontSeeHtml( 'data-option-field="mat"' )
        ->set( 'options.frame', 'oak' )
        ->assertSeeHtml( 'data-option-field="mat"' )
        ->assertSee( 'Black · +$8.00' )
        ->set( 'options.frame', 'none' )
        ->assertDontSeeHtml( 'data-option-field="mat"' )
        ->assertDontSeeHtml( 'data-option-field="gift_note"' )
        ->set( 'options.gift', true )
        ->assertSeeHtml( 'data-option-field="gift_note"' );
} );

it( 'disables the choices another choice rules out, and clears one already picked', function (): void {
    $component = Livewire::test( OptionsForm::class, [ 'product' => $this->print ] )
        ->set( 'options.paper', 'rag' )
        ->set( 'options.size', 'large' )
        ->assertSet( 'options.paper', null )
        ->assertSee( 'Not available with 24 × 36' );

    expect( $component->html() )->toMatch( '/value="rag"[^>]*\sdisabled/' );

    $component->set( 'options.size', 'small' );

    expect( $component->html() )->not->toMatch( '/value="rag"[^>]*\sdisabled/' );
} );

it( 'clears a disabled choice sent anyway and refuses the add', function (): void {
    Livewire::test( OptionsForm::class, [ 'product' => $this->print ] )
        ->set( 'options.frame', 'walnut' )
        ->assertSet( 'options.frame', null )
        ->call( 'addToCart' )
        ->assertHasErrors( 'options.frame' );

    expect( Cart::query()->count() )->toBe( 0 );
} );

it( 'validates choices against the enabled ones only', function (): void {
    $component            = Livewire::test( OptionsForm::class, [ 'product' => $this->print ] )->set( 'options.size', 'large' );
    $form                 = $component->instance();
    $visible              = ( new ReflectionMethod( $form, 'visibleFields' ) )->invoke( $form );
    [ $rules, $messages ] = ( new ReflectionMethod( $form, 'validation' ) )->invoke( $form, $visible );

    $validator = Validator::make( [ 'options' => [ 'paper' => 'rag', 'frame' => 'walnut' ] ], array_intersect_key( $rules, array_flip( [ 'options.paper', 'options.frame' ] ) ), $messages );

    expect( $validator->errors()->first( 'options.paper' ) )->toBe( 'Choose an available Paper.' )
        ->and( $validator->errors()->first( 'options.frame' ) )->toBe( 'Choose an available Frame.' );
} );

it( 'quotes the chosen options live and shows the price on the button', function (): void {
    Livewire::test( OptionsForm::class, [ 'product' => $this->print ] )
        ->assertSeeHtml( 'x-on:change="$wire.$refresh()"' )
        ->assertSeeHtml( 'data-quoted-price' )
        ->assertSeeHtml( 'wire:target="$refresh"' )
        ->assertDontSeeHtml( 'data-add-to-cart-price' )
        ->set( 'options.frame', 'ash' )
        ->assertSeeHtml( '<bdi>$60.00</bdi></strong> including options' )
        ->set( 'options.frame', 'oak' )
        ->set( 'options.size', 'large' )
        ->assertSeeHtml( '<bdi>$120.00</bdi></strong> including options' )
        ->assertSeeHtml( 'data-add-to-cart-price' )
        ->set( 'quantity', 2 )
        ->assertSee( '$240.00' );
} );

it( 'adds the visible fields only, priced by the engine', function (): void {
    Livewire::test( OptionsForm::class, [ 'product' => $this->print ] )
        ->set( 'options.frame', 'oak' )
        ->set( 'options.mat', 'black' )
        ->set( 'options.gift_note', 'Left over from an earlier gift' )
        ->set( 'options.frame', 'none' )
        ->call( 'addToCart' )
        ->assertHasNoErrors()
        ->assertDispatched( 'ecommerce-cart-updated' );

    $line = Cart::query()->sole()->items()->sole();

    expect( $line->options )->toBe( [ 'frame' => 'none', 'size' => 'small', 'paper' => 'matte', 'gift' => false ] )
        ->and( (int) $line->unit_price_amount )->toBe( 6000 );
} );

it( 'validates a conditional field only while it shows', function (): void {
    Livewire::test( OptionsForm::class, [ 'product' => $this->print ] )
        ->set( 'options.frame', 'ash' )
        ->set( 'options.gift', true )
        ->call( 'addToCart' )
        ->assertHasErrors( 'options.gift_note' )
        ->set( 'options.gift_note', 'Happy birthday' )
        ->call( 'addToCart' )
        ->assertHasNoErrors();

    expect( Cart::query()->sole()->items()->sole()->options['gift_note'] )->toBe( 'Happy birthday' );
} );

it( 'shows a price difference only in the shopper\'s currency', function (): void {
    $normalized = new ReflectionMethod( OptionsForm::class, 'normalizedOption' );

    expect( $normalized->invoke( null, [ 'value' => 'a', 'price_delta' => [ 'amount' => 500, 'currency' => 'EUR' ] ], 'USD' )['delta'] )->toBeNull()
        ->and( $normalized->invoke( null, [ 'value' => 'a', 'price_delta' => [ 'amount' => -250, 'currency' => 'USD' ] ], 'USD' )['delta'] )->toBe( '−$2.50' )
        ->and( $normalized->invoke( null, [ 'value' => 'a', 'price_delta' => [ 'amount' => 0, 'currency' => 'USD' ] ], 'USD' )['delta'] )->toBeNull();
} );

it( 'drops unsafe option images and malformed conditions', function (): void {
    $normalized = new ReflectionMethod( OptionsForm::class, 'normalizedOption' );
    $conditions = new ReflectionMethod( OptionsForm::class, 'conditions' );

    expect( $normalized->invoke( null, [ 'value' => 'a', 'image' => [ 'url' => 'javascript:alert(1)' ], 'swatch' => [ 'hex' => 'red' ] ], 'USD' ) )
        ->toMatchArray( [ 'image' => null, 'swatch' => null ] )
        ->and( $conditions->invoke( null, [ [ 'field' => 'frame', 'in' => [ 'oak', true ] ], [ 'field' => 'bad name!', 'in' => [ 'x' ] ], 'junk' ] ) )
        ->toBe( [ [ 'field' => 'frame', 'in' => [ 'oak', '1' ] ] ] );
} );

it( 'keeps a form without the richer schema deferred', function (): void {
    app( ProductTypeRegistry::class )->register( Tests\Fixtures\ProductTypes\EngravedProductType::KEY, new Tests\Fixtures\ProductTypes\EngravedProductType() );

    $watch = Product::factory()->create( [ 'type' => Tests\Fixtures\ProductTypes\EngravedProductType::KEY ] );
    ProductPrice::factory()->forPriceable( $watch )->create( [ 'currency' => 'USD', 'price_amount' => 9900, 'compare_at_amount' => null ] );

    Livewire::test( OptionsForm::class, [ 'product' => $watch ] )
        ->assertDontSeeHtml( 'data-quoted-price' );
} );

it( 'keeps an option image\'s srcset only when every candidate is a safe URL', function (): void {
    expect( ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::safeSrcset( 'https://example.com/a.jpg 1x, https://example.com/b.jpg 2x' ) )->toBe( 'https://example.com/a.jpg 1x, https://example.com/b.jpg 2x' )
        ->and( ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::safeSrcset( 'https://example.com/a.jpg 480w, javascript:alert(1) 2x' ) )->toBeNull()
        ->and( ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::safeSrcset( 'https://example.com/a.jpg onerror=x' ) )->toBeNull()
        ->and( ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::safeSrcset( '' ) )->toBeNull();
} );
