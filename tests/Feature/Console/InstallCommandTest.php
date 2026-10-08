<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\PaymentGateway;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Console\Commands\InstallCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

afterEach( function (): void {
    File::delete( config_path( InstallCommand::CONFIG_FILE ) );
} );

/**
 * Defines the host's sign-in and registration routes.
 */
function defineAuthRoutes(): void
{
    Route::get( 'login', static fn (): string => 'login' )->name( 'login' );
    Route::get( 'register', static fn (): string => 'register' )->name( 'register' );
    app( 'router' )->getRoutes()->refreshNameLookups();
}

/**
 * Registers a payment gateway under `$key`.
 */
function registerGateway( string $key ): void
{
    app( PaymentGatewayRegistry::class )->register( $key, Mockery::mock( PaymentGateway::class ) );
}

it( 'publishes the config', function (): void {
    $this->artisan( 'ecommerce-storefront:install' )->assertSuccessful();

    expect( File::exists( config_path( InstallCommand::CONFIG_FILE ) ) )->toBeTrue();
} );

it( 'asks before overwriting a published config and keeps it on "no"', function (): void {
    File::ensureDirectoryExists( dirname( config_path( InstallCommand::CONFIG_FILE ) ) );
    File::put( config_path( InstallCommand::CONFIG_FILE ), '<?php return [ "custom" => true ];' );

    $this->artisan( 'ecommerce-storefront:install' )
        ->expectsConfirmation( 'config/artisanpack/ecommerce-storefront-livewire.php already exists. Overwrite it?', 'no' )
        ->expectsOutputToContain( 'Kept your existing config file.' )
        ->assertSuccessful();

    expect( File::get( config_path( InstallCommand::CONFIG_FILE ) ) )->toContain( 'custom' );
} );

it( 'overwrites a published config on "yes" or with --force', function ( array $options, bool $asks ): void {
    File::ensureDirectoryExists( dirname( config_path( InstallCommand::CONFIG_FILE ) ) );
    File::put( config_path( InstallCommand::CONFIG_FILE ), '<?php return [ "custom" => true ];' );

    $command = $this->artisan( 'ecommerce-storefront:install', $options );

    if ( $asks ) {
        $command->expectsConfirmation( 'config/artisanpack/ecommerce-storefront-livewire.php already exists. Overwrite it?', 'yes' );
    }

    $command->assertSuccessful()->run();

    expect( File::get( config_path( InstallCommand::CONFIG_FILE ) ) )->not->toContain( 'custom' )->toContain( "'route_prefix'" );
} )->with( [
    'confirmed' => [ [], true ],
    '--force'   => [ [ '--force' => true ], false ],
] );

it( 'prints the Tailwind source lines and the layout include', function (): void {
    $command = $this->artisan( 'ecommerce-storefront:install' );

    foreach ( InstallCommand::TAILWIND_SOURCES as $source ) {
        $command->expectsOutputToContain( $source );
    }

    $command->expectsOutputToContain( InstallCommand::LAYOUT_INCLUDE )
        ->expectsOutputToContain( 'If your layout is a Blade component' )
        ->assertSuccessful();
} );

it( 'warns about each missing sign-in or registration route', function (): void {
    $this->artisan( 'ecommerce-storefront:install' )
        ->expectsOutputToContain( 'No "login" route is defined (auth.login_route).' )
        ->expectsOutputToContain( 'No "register" route is defined (auth.register_route).' )
        ->expectsOutputToContain( 'Install an auth starter kit' )
        ->assertSuccessful();
} );

it( 'checks the configured route names', function (): void {
    Route::get( 'sign-in', static fn (): string => 'sign in' )->name( 'customer.login' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    config()->set( 'artisanpack.ecommerce-storefront-livewire.auth.login_route', 'customer.login' );

    $this->artisan( 'ecommerce-storefront:install' )
        ->doesntExpectOutputToContain( 'No "customer.login" route' )
        ->expectsOutputToContain( 'No "register" route is defined' )
        ->assertSuccessful();
} );

it( 'confirms the sign-in routes when the host has them', function (): void {
    defineAuthRoutes();

    $this->artisan( 'ecommerce-storefront:install' )
        ->expectsOutputToContain( 'Sign-in and registration routes found.' )
        ->doesntExpectOutputToContain( 'route is defined' )
        ->assertSuccessful();
} );

it( 'warns when no payment gateway is registered', function (): void {
    $this->artisan( 'ecommerce-storefront:install' )
        ->expectsOutputToContain( 'No payment gateway is registered' )
        ->assertSuccessful();
} );

it( 'warns when the only gateway is Stripe without its keys', function (): void {
    registerGateway( 'stripe' );
    config()->set( 'artisanpack.ecommerce.gateways.stripe.secret_key', '' );

    $this->artisan( 'ecommerce-storefront:install' )
        ->expectsOutputToContain( 'A payment gateway is registered but not configured: stripe.' )
        ->assertSuccessful();
} );

it( 'reports the gateways that are ready', function (): void {
    registerGateway( 'stripe' );
    registerGateway( 'paypal' );
    config()->set( 'artisanpack.ecommerce.gateways.stripe.secret_key', 'sk_test_123' );
    config()->set( 'artisanpack.ecommerce.gateways.stripe.publishable_key', 'pk_test_123' );

    $this->artisan( 'ecommerce-storefront:install' )
        ->expectsOutputToContain( 'Payment gateways ready: stripe, paypal.' )
        ->doesntExpectOutputToContain( 'No payment gateway' )
        ->assertSuccessful();
} );

it( 'says nothing about templates when visual-editor is absent', function (): void {
    expect( class_exists( InstallCommand::VISUAL_EDITOR_FACADE ) )->toBeFalse();

    $this->artisan( 'ecommerce-storefront:install' )
        ->doesntExpectOutputToContain( 'visual-editor' )
        ->assertSuccessful();
} );

it( 'explains the template option when visual-editor is installed with templates off, and confirms it when on', function (): void {
    // Stand in for the visual-editor facade.
    class_alias( stdClass::class, InstallCommand::VISUAL_EDITOR_FACADE );

    $this->artisan( 'ecommerce-storefront:install' )
        ->expectsOutputToContain( 'visual_editor.templates is off' )
        ->assertSuccessful();

    config()->set( 'artisanpack.ecommerce-storefront-livewire.visual_editor.templates', true );

    $this->artisan( 'ecommerce-storefront:install', [ '--force' => true ] )
        ->expectsOutputToContain( 'Storefront pages render through visual-editor templates.' )
        ->doesntExpectOutputToContain( 'visual_editor.templates is off' )
        ->assertSuccessful();
} );
