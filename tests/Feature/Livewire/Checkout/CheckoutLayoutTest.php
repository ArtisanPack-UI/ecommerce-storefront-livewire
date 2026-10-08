<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\SettingsRegistry;
use ArtisanPackUI\Ecommerce\Settings\SettingsRepository;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\CheckoutLayout;
use Livewire\Livewire;

beforeEach( function (): void {
    checkoutShipping( [ 'Standard' => 500 ] );
    checkoutGateway();
    checkoutCart();
} );

it( 'shows the multi-step progress bar, the open step, and only finished steps by default', function (): void {
    $component = Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->assertSet( 'layout', CheckoutLayout::MULTI_STEP )
        ->assertSet( 'step', 'address' )
        ->assertSet( 'progress', 2 )
        ->assertSeeHtml( 'data-checkout-layout="multi_step"' )
        ->assertSeeHtml( 'data-checkout-progress' )
        ->assertSeeHtml( 'data-checkout-step="contact"' )
        ->assertSeeHtml( 'data-checkout-step-summary="contact"' )
        ->assertSeeHtml( 'data-checkout-edit="contact"' )
        ->assertSeeHtml( 'data-checkout-step="address"' )
        ->assertDontSeeHtml( 'data-checkout-step="shipping"' )
        ->assertDontSeeHtml( 'data-checkout-step="review"' )
        ->assertSet( 'announcement', 'Step 2 of 5: Address' );

    expect( $component->html() )->toContain( 'x-init="$nextTick( () => $el.focus() )"' );
} );

it( 'moves between steps with the query string, so browser back works', function (): void {
    Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->assertSet( 'step', 'address' );

    Livewire::withQueryParams( [ 'step' => 'contact' ] )
        ->test( Index::class )
        ->assertSet( 'step', 'contact' )
        ->assertSet( 'progress', 1 );

    // A step the shopper can't reach yet falls back to the furthest one.
    Livewire::withQueryParams( [ 'step' => 'review' ] )
        ->test( Index::class )
        ->assertSet( 'step', 'address' );
} );

it( 'shows every section on a single page, disabling the ones after an unfinished step', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.layout', CheckoutLayout::SINGLE_PAGE );

    Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->assertSet( 'layout', CheckoutLayout::SINGLE_PAGE )
        ->assertSeeHtml( 'data-checkout-layout="single_page"' )
        ->assertDontSeeHtml( 'data-checkout-progress' )
        ->assertSeeHtml( 'data-checkout-step-summary="contact"' )
        ->assertSeeHtml( 'data-checkout-address-form' )
        ->assertSeeHtml( 'data-checkout-step-locked="shipping"' )
        ->assertSeeHtml( 'data-checkout-step-locked="payment"' )
        ->assertSeeHtml( 'data-checkout-step-locked="review"' )
        ->assertSeeHtml( 'aria-disabled="true"' )
        ->assertSee( 'Complete “Address” first.' );
} );

it( 'takes the layout from the store setting over config', function (): void {
    app( SettingsRepository::class )->update( 'checkout', [ CheckoutLayout::LAYOUT_SETTING => CheckoutLayout::SINGLE_PAGE ] );

    Livewire::test( Index::class )->assertSet( 'layout', CheckoutLayout::SINGLE_PAGE );
} );

it( 'falls back to multi-step for an unknown layout', function (): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.layout', 'carousel' );

    expect( CheckoutLayout::current() )->toBe( CheckoutLayout::MULTI_STEP );
} );

it( 'collapses the order summary on small screens in both layouts', function ( string $layout ): void {
    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.layout', $layout );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-checkout-summary-toggle' )
        ->assertSeeHtml( 'aria-controls="ec-checkout-summary-body"' )
        ->assertSeeHtml( 'data-checkout-summary-body' )
        ->assertSee( 'Show order summary' );
} )->with( [ CheckoutLayout::MULTI_STEP, CheckoutLayout::SINGLE_PAGE ] );

it( 'offers the layout and terms page on the admin\'s checkout settings', function (): void {
    $settings = app( SettingsRegistry::class );

    expect( $settings->definition( CheckoutLayout::LAYOUT_SETTING ) )
        ->group->toBe( 'checkout' )
        ->type->toBe( 'select' )
        ->and( $settings->definition( CheckoutLayout::LAYOUT_SETTING )->options() )->toHaveKeys( [ CheckoutLayout::MULTI_STEP, CheckoutLayout::SINGLE_PAGE ] )
        ->and( $settings->definition( CheckoutLayout::LAYOUT_SETTING )->configKey() )->toBe( 'artisanpack.ecommerce-storefront-livewire.checkout.layout' )
        ->and( $settings->definition( CheckoutLayout::TERMS_SETTING ) )->group->toBe( 'checkout' )
        ->and( $settings->definition( CheckoutLayout::TERMS_SETTING )->configKey() )->toBe( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url' );
} );

it( 'escapes step labels in the progress bar, which renders them as HTML', function (): void {
    Livewire::component( 'age-check-step', Tests\Fixtures\Livewire\AgeCheckStep::class );

    addFilter( 'ap.ecommerceStorefrontLivewire.checkout.steps', static function ( array $steps ): array {
        $steps['age-check'] = [ 'label' => '<img src=x onerror=alert(1)>Age \'check\'', 'component' => 'age-check-step' ];

        return $steps;
    } );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->not->toContain( '<img src=x' )
        ->and( $html )->toContain( 'text: \'&amp;lt;img src=x onerror=alert(1)&amp;gt;Age &amp;#039;check&amp;#039;\'' );
} );

it( 'accepts the same terms pages in the admin setting as at checkout', function ( string $url, bool $allowed ): void {
    $rules = app( SettingsRegistry::class )->definition( CheckoutLayout::TERMS_SETTING )->rules();

    config()->set( 'artisanpack.ecommerce-storefront-livewire.checkout.terms_url', $url );

    expect( validator( [ 'value' => $url ], [ 'value' => $rules ] )->passes() )->toBe( $allowed )
        ->and( CheckoutLayout::termsUrl() )->toBe( $allowed ? $url : null );
} )->with( [
    'site path'          => [ '/terms', true ],
    'https URL'          => [ 'https://shop.test/terms', true ],
    'http URL'           => [ 'http://shop.test/terms', true ],
    'protocol-relative'  => [ '//evil.test/terms', false ],
    'backslash relative' => [ '/\\evil.test/terms', false ],
    'javascript'         => [ 'javascript:alert(1)', false ],
    'relative path'      => [ 'terms', false ],
] );
