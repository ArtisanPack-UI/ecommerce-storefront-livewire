<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Checkout\CheckoutState;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Services\CheckoutService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Checkout\Index;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The checkout, past the contact step.
 */
function checkoutAtAddress(): Testable
{
    return Livewire::test( Index::class )
        ->set( 'email', 'ada@example.test' )
        ->call( 'saveContact' )
        ->assertSet( 'step', 'address' );
}

/**
 * A signed-in shopper with saved addresses (the first is their default).
 *
 * @return array{customer: Customer, home: CustomerAddress, work: CustomerAddress}
 */
function shopperWithAddressBook(): array
{
    $user = makeUser( [ 'email' => 'ada@example.test' ] );
    test()->actingAs( $user );
    checkoutCart();

    $customer = Customer::forUser( $user );
    $home     = CustomerAddress::factory()->defaultShipping()->defaultBilling()->create( [ 'customer_id' => $customer->id, 'label' => 'Home' ] + checkoutAddress( [ 'address1' => '1 Home Road' ] ) );
    $work     = CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'label' => 'Work' ] + checkoutAddress( [ 'address1' => '9 Office Park', 'city' => 'Chicago', 'postal_code' => '60601' ] ) );

    return [ 'customer' => $customer, 'home' => $home, 'work' => $work ];
}

beforeEach( function (): void {
    checkoutShipping();
} );

it( 'shows the shipping address form with country and region lists and autofill hints', function (): void {
    checkoutCart();

    checkoutAtAddress()
        ->assertSeeHtml( 'data-checkout-address-form' )
        ->assertSeeHtml( 'data-address-form="shipping"' )
        ->assertSee( 'Shipping address' )
        ->assertSee( 'United States' )
        ->assertSee( 'Illinois' )
        ->assertSee( 'ZIP code' )
        ->assertSee( 'For example: 12345' )
        ->assertSeeHtml( 'autocomplete="shipping given-name"' )
        ->assertSeeHtml( 'autocomplete="shipping address-line1"' )
        ->assertSeeHtml( 'autocomplete="shipping postal-code"' )
        ->assertSeeHtml( 'data-checkout-billing-same' )
        ->assertSet( 'billingSameAsShipping', true )
        ->assertDontSeeHtml( 'data-address-form="billing"' )
        ->assertDontSeeHtml( 'data-checkout-saved=' )
        ->assertDontSeeHtml( 'data-checkout-save-address' );
} );

it( 'saves the shipping address, billing the same, and moves on to shipping', function (): void {
    $cart = checkoutCart();

    checkoutAtAddress()
        ->set( 'shipping', checkoutAddress() )
        ->call( 'saveAddress' )
        ->assertHasNoErrors()
        ->assertSet( 'step', 'shipping' )
        ->assertSet( 'announcement', 'Step 3 of 5: Shipping' )
        ->assertSeeHtml( 'data-checkout-step-summary="address"' )
        ->assertSee( '1 Main Street' )
        ->assertSee( 'Same as shipping' );

    expect( $cart->refresh() )
        ->checkout_state->toBe( CheckoutState::SHIPPING_SELECTION )
        ->billing_address->toBeNull()
        ->shipping_address->toMatchArray( [
            'first_name'   => 'Ada',
            'address1'     => '1 Main Street',
            'city'         => 'Springfield',
            'region'       => 'Illinois',
            'region_code'  => 'IL',
            'postal_code'  => '62701',
            'country_code' => 'US',
            'company'      => null,
        ] );
} );

it( 'takes a Canadian address with a province', function (): void {
    $cart = checkoutCart();

    $component = checkoutAtAddress()
        ->set( 'shipping.country_code', 'CA' )
        ->assertSee( 'Province or territory' )
        ->assertSee( 'Ontario' )
        ->assertSee( 'Postal code' );

    $component->set( 'shipping', checkoutAddress( [ 'country_code' => 'CA', 'region_code' => 'ON', 'city' => 'Ottawa', 'postal_code' => 'K1A 0B1' ] ) )
        ->call( 'saveAddress' )
        ->assertHasNoErrors()
        ->assertSet( 'step', 'shipping' );

    expect( $cart->refresh()->shipping_address )->toMatchArray( [ 'country_code' => 'CA', 'region_code' => 'ON', 'region' => 'Ontario', 'postal_code' => 'K1A 0B1' ] );
} );

it( 'clears the region when the country changes', function (): void {
    checkoutCart();

    checkoutAtAddress()
        ->set( 'shipping.region_code', 'IL' )
        ->set( 'shipping.country_code', 'CA' )
        ->assertSet( 'shipping.region_code', '' );
} );

it( 'saves a separate billing address', function (): void {
    $cart = checkoutCart();

    checkoutAtAddress()
        ->set( 'billingSameAsShipping', false )
        ->assertSeeHtml( 'data-address-form="billing"' )
        ->assertSeeHtml( 'autocomplete="billing address-line1"' )
        ->set( 'shipping', checkoutAddress() )
        ->set( 'billing', checkoutAddress( [ 'first_name' => 'Charles', 'last_name' => 'Babbage', 'address1' => '5 Bill Street' ] ) )
        ->call( 'saveAddress' )
        ->assertHasNoErrors()
        ->assertSee( '5 Bill Street' );

    expect( $cart->refresh()->billing_address )->toMatchArray( [ 'first_name' => 'Charles', 'address1' => '5 Bill Street' ] );
} );

it( 'summarises the problems at the top, linked to their fields', function (): void {
    checkoutCart();

    checkoutAtAddress()
        ->set( 'shipping', checkoutAddress( [ 'first_name' => '', 'address1' => ' ', 'city' => '', 'region_code' => '', 'postal_code' => '' ] ) )
        ->call( 'saveAddress' )
        ->assertHasErrors( [ 'shipping.first_name', 'shipping.address1', 'shipping.city', 'shipping.region_code', 'shipping.postal_code' ] )
        ->assertSet( 'step', 'address' )
        ->assertSet( 'errorSummary', 1 )
        ->assertSeeHtml( 'data-checkout-errors' )
        ->assertSee( 'There are 5 problems to fix:' )
        ->assertSeeHtml( 'data-checkout-error="shipping.address1"' )
        ->assertSeeHtml( 'data-field="shipping.address1"' )
        ->assertSee( 'Shipping address: Address:' )
        ->assertSee( 'Enter the street address.' )
        ->assertSee( 'Choose a state or region.' )
        ->assertSee( 'Enter the ZIP code.' );
} );

it( 'checks the postcode format for the country', function ( string $country, string $region, string $postcode, ?string $message ): void {
    checkoutCart();

    $component = checkoutAtAddress()
        ->set( 'shipping', checkoutAddress( [ 'country_code' => $country, 'region_code' => $region, 'postal_code' => $postcode ] ) )
        ->call( 'saveAddress' );

    null === $message
        ? $component->assertHasNoErrors()
        : $component->assertHasErrors( 'shipping.postal_code' )->assertSee( $message );
} )->with( [
    'US ZIP'          => [ 'US', 'IL', '62701-1234', null ],
    'US too short'    => [ 'US', 'IL', '6270', 'For example: 12345' ],
    'Canada'          => [ 'CA', 'ON', 'k1a0b1', null ],
    'Canada wrong'    => [ 'CA', 'ON', '12345', 'For example: K1A 0B1' ],
] );

it( 'requires a valid country', function (): void {
    checkoutCart();

    checkoutAtAddress()
        ->set( 'shipping', checkoutAddress( [ 'country_code' => 'XX', 'region_code' => '' ] ) )
        ->call( 'saveAddress' )
        ->assertHasErrors( 'shipping.country_code' )
        ->assertSee( 'Choose a country.' );
} );

it( 'validates the billing address when it differs', function (): void {
    checkoutCart();

    checkoutAtAddress()
        ->set( 'billingSameAsShipping', false )
        ->set( 'shipping', checkoutAddress() )
        ->set( 'billing', checkoutAddress( [ 'city' => '' ] ) )
        ->call( 'saveAddress' )
        ->assertHasErrors( 'billing.city' )
        ->assertHasNoErrors( 'shipping.city' )
        ->assertSee( 'Billing address: City:' );
} );

it( 'shows the engine\'s refusal of the address', function (): void {
    checkoutCart();

    addFilter( 'ap.ecommerce.checkout.canTransitionTo', static fn ( bool $allowed, ArtisanPackUI\Ecommerce\Models\Cart $cart, string $to ): bool => CheckoutState::SHIPPING_SELECTION !== $to, 10, 3 );

    checkoutAtAddress()
        ->set( 'shipping', checkoutAddress() )
        ->call( 'saveAddress' )
        ->assertHasErrors( 'address' )
        ->assertSeeHtml( 'data-checkout-errors' )
        ->assertSet( 'step', 'address' );

    removeAllFilters( 'ap.ecommerce.checkout.canTransitionTo' );
} );

it( 'sends the shopper back to the cart when it expired mid-checkout', function (): void {
    $cart = checkoutCart();

    $component = checkoutAtAddress()->set( 'shipping', checkoutAddress() );

    $cart->forceFill( [ 'expires_at' => now()->subMinute() ] )->save();

    $component->call( 'saveAddress' )
        ->assertRedirect( route( 'artisanpack.ecommerce.storefront.cart' ) );
} );

it( 'asks only for a billing address when nothing ships', function (): void {
    $ebook = Product::factory()->digital()->create( [ 'name' => 'E-book' ] );
    ProductPrice::factory()->forPriceable( $ebook )->create( [ 'currency' => 'USD', 'price_amount' => 900 ] );
    $cart = checkoutCart( [ $ebook ] );

    checkoutAtAddress()
        ->assertSee( 'Billing address' )
        ->assertSeeHtml( 'data-checkout-digital-only' )
        ->assertSeeHtml( 'data-address-form="billing"' )
        ->assertDontSeeHtml( 'data-address-form="shipping"' )
        ->assertDontSeeHtml( 'data-checkout-billing-same' )
        ->assertDontSeeHtml( 'data-checkout-step="shipping"' )
        ->set( 'billing', checkoutAddress() )
        ->call( 'saveAddress' )
        ->assertHasNoErrors()
        ->assertSet( 'step', 'payment' )
        ->assertSet( 'announcement', 'Step 3 of 4: Payment' );

    expect( $cart->refresh() )
        ->shipping_address->toBeNull()
        ->billing_address->toMatchArray( [ 'address1' => '1 Main Street' ] )
        ->checkout_state->toBe( CheckoutState::PAYMENT_SELECTION );
} );

it( 'offers a signed-in shopper their saved addresses, starting with the default', function (): void {
    [ 'home' => $home ] = shopperWithAddressBook();

    Livewire::test( Index::class )
        ->assertSet( 'step', 'address' )
        ->assertSeeHtml( 'data-checkout-saved="shipping"' )
        ->assertSee( 'Home' )
        ->assertSee( 'Work' )
        ->assertSee( 'Use a new address' )
        ->assertSet( 'savedShipping', (string) $home->id )
        ->assertSet( 'shipping.address1', '1 Home Road' )
        ->assertDontSeeHtml( 'data-address-form="shipping"' );
} );

it( 'ships to the saved address the shopper picks', function (): void {
    [ 'work' => $work ] = shopperWithAddressBook();
    $cart               = app( ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontCart::class )->current();

    Livewire::test( Index::class )
        ->set( 'savedShipping', (string) $work->id )
        ->assertSet( 'shipping.address1', '9 Office Park' )
        ->call( 'saveAddress' )
        ->assertHasNoErrors()
        ->assertSet( 'step', 'shipping' );

    expect( $cart->refresh()->shipping_address )->toMatchArray( [ 'address1' => '9 Office Park', 'city' => 'Chicago' ] );
} );

it( 'ignores an address that isn\'t the shopper\'s', function (): void {
    shopperWithAddressBook();
    $stranger = CustomerAddress::factory()->create( checkoutAddress( [ 'address1' => '13 Stranger Lane' ] ) );

    Livewire::test( Index::class )
        ->set( 'savedShipping', (string) $stranger->id )
        ->assertNotSet( 'shipping.address1', '13 Stranger Lane' )
        ->assertSet( 'shipping.address1', '' );
} );

it( 'saves a new address to the address book when asked', function (): void {
    [ 'customer' => $customer ] = shopperWithAddressBook();

    Livewire::test( Index::class )
        ->set( 'savedShipping', 'new' )
        ->assertSeeHtml( 'data-address-form="shipping"' )
        ->assertSeeHtml( 'data-checkout-save-address' )
        ->set( 'shipping', checkoutAddress( [ 'address1' => '42 New Street' ] ) )
        ->set( 'saveToAddressBook', true )
        ->call( 'saveAddress' )
        ->assertHasNoErrors()
        ->assertSet( 'saveToAddressBook', false );

    expect( $customer->addresses()->where( 'address1', '42 New Street' )->exists() )->toBeTrue()
        ->and( $customer->addresses()->count() )->toBe( 3 );
} );

it( 'doesn\'t save to the address book unless asked', function (): void {
    [ 'customer' => $customer ] = shopperWithAddressBook();

    Livewire::test( Index::class )
        ->set( 'savedShipping', 'new' )
        ->set( 'shipping', checkoutAddress( [ 'address1' => '42 New Street' ] ) )
        ->call( 'saveAddress' );

    expect( $customer->addresses()->count() )->toBe( 2 );
} );

it( 'offers a signed-in shopper without saved addresses to save the new one', function (): void {
    $this->actingAs( makeUser() );
    checkoutCart();

    checkoutAtAddress()
        ->assertDontSeeHtml( 'data-checkout-saved=' )
        ->assertSeeHtml( 'data-checkout-save-address' );
} );

it( 'resumes with the address already on the cart', function (): void {
    $cart = checkoutCart();

    app( CheckoutService::class )->setEmail( $cart, 'ada@example.test' );
    app( CheckoutService::class )->setAddress( $cart, ArtisanPackUI\Ecommerce\ValueObjects\Address::fromArray( checkoutAddress( [ 'address1' => '7 Saved Street' ] ) ) );

    Livewire::withQueryParams( [ 'step' => 'address' ] )->test( Index::class )
        ->assertSet( 'step', 'address' )
        ->assertSet( 'shipping.address1', '7 Saved Street' )
        ->assertSet( 'billingSameAsShipping', true );
} );
