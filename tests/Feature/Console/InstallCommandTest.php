<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\PaymentGateway;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlocks;
use ArtisanPackUI\EcommerceStorefrontLivewire\Console\Commands\InstallCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Points config_path() and the config publish destination at a per-test temp
 * directory, so publishing never touches the shared testbench skeleton that
 * parallel workers load their configuration from.
 */
beforeEach( function (): void {
    $this->sharedConfigPath   = config_path();
    $this->isolatedConfigPath = sys_get_temp_dir() . '/apsf-config-' . getmypid() . '-' . uniqid();
    $this->originalPublishes  = [ ServiceProvider::$publishes, ServiceProvider::$publishGroups ];

    File::ensureDirectoryExists( $this->isolatedConfigPath );
    $this->app->useConfigPath( $this->isolatedConfigPath );

    $remap = function ( array $paths ): array {
        $remapped = [];

        foreach ( $paths as $from => $to ) {
            $remapped[ $from ] = str_starts_with( (string) $to, $this->sharedConfigPath )
                ? $this->isolatedConfigPath . substr( (string) $to, strlen( $this->sharedConfigPath ) )
                : $to;
        }

        return $remapped;
    };

    ServiceProvider::$publishes     = array_map( $remap, ServiceProvider::$publishes );
    ServiceProvider::$publishGroups = array_map( $remap, ServiceProvider::$publishGroups );
} );

afterEach( function (): void {
    [ ServiceProvider::$publishes, ServiceProvider::$publishGroups ] = $this->originalPublishes;

    $this->app->useConfigPath( $this->sharedConfigPath );
    File::deleteDirectory( $this->isolatedConfigPath );
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

it( 'prints the design-system setup', function (): void {
    $command = $this->artisan( 'ecommerce-storefront:install' )
        ->expectsOutputToContain( 'npm install @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1' );

    foreach ( [ ...InstallCommand::STYLESHEET_IMPORTS, ...InstallCommand::TAILWIND_SOURCES, ...InstallCommand::COMPONENT_LIBRARY_SOURCES, ...InstallCommand::JS_GLOBALS, ...InstallCommand::BUILD_COMMANDS ] as $line ) {
        $command->expectsOutputToContain( $line );
    }

    $command->expectsOutputToContain( 'Keep the @layer line first in the file' )
        ->expectsOutputToContain( 'Then build your assets' )
        ->expectsOutputToContain( 'Remove daisyUI' )
        ->expectsOutputToContain( InstallCommand::FRONT_END_DOCS )
        ->assertSuccessful();
} );

it( 'prints the @layer line before the design-system import and the sources', function (): void {
    Artisan::call( 'ecommerce-storefront:install' );

    $output = Artisan::output();
    $layer  = strpos( $output, InstallCommand::STYLESHEET_IMPORTS[0] );

    expect( $layer )->toBeInt();

    foreach ( [ InstallCommand::STYLESHEET_IMPORTS[1], InstallCommand::TAILWIND_SOURCES[0], InstallCommand::COMPONENT_LIBRARY_SOURCES[0] ] as $line ) {
        $position = strpos( $output, $line );

        expect( $position )->toBeInt( $line )->toBeGreaterThan( $layer );
    }
} );

it( 'prints the layout include, the theme script, and the component wrapper', function (): void {
    $this->artisan( 'ecommerce-storefront:install' )
        ->expectsOutputToContain( InstallCommand::LAYOUT_INCLUDE )
        ->expectsOutputToContain( 'Add ' . InstallCommand::THEME_SCRIPT . ' inside its <head>' )
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
    expect( app()->bound( StorefrontBlocks::EDITOR ) )->toBeFalse();

    $this->artisan( 'ecommerce-storefront:install' )
        ->doesntExpectOutputToContain( 'visual-editor' )
        ->assertSuccessful();
} );

it( 'explains the template option when visual-editor is installed with templates off, and confirms it when on', function (): void {
    require_once dirname( __DIR__, 2 ) . '/Fixtures/VisualEditor/VisualEditorStub.php';

    app()->instance( StorefrontBlocks::EDITOR, new ArtisanPackUI\VisualEditor\VisualEditor() );

    $this->artisan( 'ecommerce-storefront:install' )
        ->expectsOutputToContain( 'visual_editor.templates is off' )
        ->assertSuccessful();

    config()->set( 'artisanpack.ecommerce-storefront-livewire.visual_editor.templates', true );

    $this->artisan( 'ecommerce-storefront:install', [ '--force' => true ] )
        ->expectsOutputToContain( 'Storefront pages render through visual-editor templates.' )
        ->doesntExpectOutputToContain( 'visual_editor.templates is off' )
        ->assertSuccessful();
} );
