<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerNotificationPreference;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\Ecommerce\Services\NotificationPreferenceService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Account\Profile;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

it( 'loads the shopper\'s details and notification preferences', function (): void {
    [ , $customer ] = shopper( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '555 0100', 'accepts_marketing' => true ] );

    CustomerNotificationPreference::query()->create( [ 'customer_id' => $customer->id, 'channel' => 'mail', 'category' => 'review-requests', 'is_enabled' => false ] );

    Livewire::test( Profile::class )
        ->assertSet( 'profile.first_name', 'Ada' )
        ->assertSet( 'profile.last_name', 'Lovelace' )
        ->assertSet( 'profile.phone', '555 0100' )
        ->assertSet( 'profile.accepts_marketing', true )
        ->assertSet( 'preferences.mail.review-requests', false )
        ->assertSet( 'preferences.mail.shipping-updates', true )
        ->assertSet( 'preferences.mail.marketing', true )
        ->assertSee( 'Orders and account' )
        ->assertSee( 'You sign in with ' . $customer->email . '.' );
} );

it( 'never offers to turn off order and account emails', function (): void {
    shopper();

    $component = Livewire::test( Profile::class );

    expect( $component->get( 'preferences' )['mail'] )->not->toHaveKey( CustomerNotificationPreference::CATEGORY_TRANSACTIONAL );

    preg_match( '/<div[^>]*data-account-preference="mail\.transactional"[^>]*>.*?<input[^>]*>/s', $component->html(), $toggle );

    expect( $toggle[0] ?? '' )->toContain( 'disabled' )->toContain( 'checked' );
} );

it( 'saves the name and phone, and records marketing consent', function (): void {
    [ , $customer ] = shopper( [ 'accepts_marketing' => false ] );

    Livewire::test( Profile::class )
        ->set( 'profile.first_name', '  Grace ' )
        ->set( 'profile.last_name', 'Hopper' )
        ->set( 'profile.phone', '+1 (555) 010-0200' )
        ->set( 'profile.accepts_marketing', true )
        ->call( 'saveProfile' )
        ->assertHasNoErrors()
        ->assertSet( 'preferences.mail.marketing', true );

    $customer->refresh();

    expect( $customer->first_name )->toBe( 'Grace' )
        ->and( $customer->last_name )->toBe( 'Hopper' )
        ->and( $customer->phone )->toBe( '+1 (555) 010-0200' )
        ->and( $customer->accepts_marketing )->toBeTrue()
        ->and( $customer->accepts_marketing_at )->not->toBeNull();
} );

it( 'clears the consent date when the shopper opts out of marketing', function (): void {
    [ , $customer ] = shopper( [ 'accepts_marketing' => true, 'accepts_marketing_at' => now()->subMonth() ] );

    Livewire::test( Profile::class )
        ->set( 'profile.accepts_marketing', false )
        ->call( 'saveProfile' )
        ->assertHasNoErrors();

    expect( $customer->refresh()->accepts_marketing )->toBeFalse()
        ->and( $customer->accepts_marketing_at )->toBeNull();
} );

it( 'validates the profile', function (): void {
    [ , $customer ] = shopper( [ 'first_name' => 'Ada' ] );

    Livewire::test( Profile::class )
        ->set( 'profile.first_name', str_repeat( 'a', 256 ) )
        ->set( 'profile.phone', 'call me maybe' )
        ->call( 'saveProfile' )
        ->assertHasErrors( [ 'profile.first_name', 'profile.phone' ] );

    expect( $customer->refresh()->first_name )->toBe( 'Ada' );
} );

it( 'creates the customer record on the first save', function (): void {
    $user = makeUser();
    $this->actingAs( $user );

    Livewire::test( Profile::class )
        ->set( 'profile.first_name', 'Grace' )
        ->call( 'saveProfile' )
        ->assertHasNoErrors();

    expect( Customer::forUser( $user )?->first_name )->toBe( 'Grace' );
} );

it( 'turns off a notification category on a channel', function (): void {
    [ , $customer ] = shopper();

    Livewire::test( Profile::class )
        ->set( 'preferences.mail.shipping-updates', false )
        ->set( 'preferences.sms.marketing', true )
        ->call( 'savePreferences' )
        ->assertSet( 'preferences.mail.shipping-updates', false );

    $service = app( NotificationPreferenceService::class );

    expect( $service->allows( $customer, 'mail', 'shipping-updates' ) )->toBeFalse()
        ->and( $service->allows( $customer, 'mail', 'review-requests' ) )->toBeTrue()
        ->and( $customer->notificationPreferences()->where( 'channel', 'sms' )->exists() )->toBeFalse()
        ->and( $customer->notificationPreferences()->where( 'category', CustomerNotificationPreference::CATEGORY_TRANSACTIONAL )->exists() )->toBeFalse();
} );

it( 'shows a switch for every configured channel', function (): void {
    config()->set( 'artisanpack.ecommerce.notifications.preference_channels', [ 'mail', 'sms' ] );

    [ , $customer ] = shopper();

    Livewire::test( Profile::class )
        ->assertSeeHtml( 'data-account-preferences-channel="sms"' )
        ->assertSee( 'Text message' )
        ->set( 'preferences.sms.shipping-updates', false )
        ->call( 'savePreferences' );

    expect( app( NotificationPreferenceService::class )->allows( $customer, 'sms', 'shipping-updates' ) )->toBeFalse()
        ->and( app( NotificationPreferenceService::class )->allows( $customer, 'mail', 'shipping-updates' ) )->toBeTrue();
} );

it( 'reports an engine failure saving the profile', function (): void {
    [ , $customer ] = shopper( [ 'first_name' => 'Ada' ] );

    app()->instance( CustomerService::class, Mockery::mock( CustomerService::class, function ( $mock ) use ( $customer ): void {
        $mock->shouldReceive( 'customerForUser' )->andReturn( $customer );
        $mock->shouldReceive( 'updateProfile' )->andThrow( new RuntimeException( 'Database is down.' ) );
    } ) );

    $component = Livewire::test( Profile::class )
        ->set( 'profile.first_name', 'Grace' )
        ->call( 'saveProfile' )
        ->assertSet( 'profile.first_name', 'Grace' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'save your profile' )
        ->and( $customer->refresh()->first_name )->toBe( 'Ada' );
} );

it( 'reports an engine failure saving the preferences', function (): void {
    shopper();

    app()->instance( NotificationPreferenceService::class, new class extends NotificationPreferenceService {
        public function update( Customer $customer, array $preferences ): array
        {
            throw new RuntimeException( 'Database is down.' );
        }
    } );

    $component = Livewire::test( Profile::class )
        ->set( 'preferences.mail.marketing', true )
        ->call( 'savePreferences' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'save your notification preferences' );
} );

it( 'links email and password changes to the host\'s screens', function (): void {
    Route::get( 'settings/profile', static fn (): string => 'profile' )->middleware( 'web' )->name( 'settings.profile' );
    Route::get( 'settings/password', static fn (): string => 'password' )->middleware( 'web' )->name( 'settings.password' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    shopper();

    Livewire::test( Profile::class )
        ->assertSeeHtml( 'data-account-host-link="profile"' )
        ->assertSeeHtml( 'href="' . route( 'settings.profile' ) . '"' )
        ->assertSeeHtml( 'data-account-host-link="password"' )
        ->assertSeeHtml( 'href="' . route( 'settings.password' ) . '"' );
} );

it( 'won\'t turn marketing on without the shopper\'s consent', function (): void {
    [ , $customer ] = shopper( [ 'accepts_marketing' => false ] );

    Livewire::test( Profile::class )
        ->assertSeeHtml( 'data-account-preference-needs-consent' )
        ->set( 'preferences.mail.marketing', true )
        ->call( 'savePreferences' )
        ->assertSet( 'preferences.mail.marketing', false );

    expect( app( NotificationPreferenceService::class )->allows( $customer->refresh(), 'mail', 'marketing' ) )->toBeFalse();
} );

it( 'switches marketing off on every channel when consent is withdrawn', function (): void {
    config()->set( 'artisanpack.ecommerce.notifications.preference_channels', [ 'mail', 'sms' ] );

    [ , $customer ] = shopper( [ 'accepts_marketing' => true, 'accepts_marketing_at' => now()->subMonth() ] );

    foreach ( [ 'mail', 'sms' ] as $channel ) {
        CustomerNotificationPreference::query()->create( [ 'customer_id' => $customer->id, 'channel' => $channel, 'category' => 'marketing', 'is_enabled' => true ] );
    }

    Livewire::test( Profile::class )
        ->assertDontSeeHtml( 'data-account-preference-needs-consent' )
        ->set( 'profile.accepts_marketing', false )
        ->call( 'saveProfile' )
        ->assertSet( 'preferences.mail.marketing', false )
        ->assertSet( 'preferences.sms.marketing', false );

    $service = app( NotificationPreferenceService::class );

    expect( $service->allows( $customer->refresh(), 'mail', 'marketing' ) )->toBeFalse()
        ->and( $service->allows( $customer, 'sms', 'marketing' ) )->toBeFalse();
} );
