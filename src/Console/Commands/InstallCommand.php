<?php

/**
 * Install command.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Console\Commands;

use ArtisanPackUI\Ecommerce\Gateways\Stripe\StripeGateway;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\EcommerceStorefrontLivewire\Blocks\StorefrontBlocks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * Takes a host from `composer require` to a working storefront (spec §4.3,
 * §14): publishes the config, prints the front-end and layout steps, and
 * checks what the storefront relies on but doesn't own: the host's sign-in
 * routes (D6), a payment gateway, and the visual-editor template option.
 *
 * The checks only warn; nothing here fails the install.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class InstallCommand extends Command
{
    /**
     * The design-system stylesheet lines a host adds to its main stylesheet.
     * The `@layer` line comes first in the file, above Tailwind's import.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const STYLESHEET_IMPORTS = [
        '@layer theme, base, apui, components, utilities;',
        "@import '@artisanpack-ui/css';",
    ];

    /**
     * The Tailwind `@source` lines a host adds to its main stylesheet.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const TAILWIND_SOURCES = [
        '@source "../../vendor/artisanpack-ui/ecommerce-storefront-livewire/resources/views/**/*.blade.php";',
        '@source "../../vendor/artisanpack-ui/ecommerce-storefront-livewire/src/**/*.php";',
    ];

    /**
     * The component library's Tailwind `@source` lines, for hosts that don't
     * list them already.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const COMPONENT_LIBRARY_SOURCES = [
        '@source "../../vendor/artisanpack-ui/livewire-ui-components/resources/views/**/*.php";',
        '@source "../../vendor/artisanpack-ui/livewire-ui-components/src/**/*.php";',
    ];

    /**
     * The npm packages the storefront depends on: the design system's CSS and
     * the behavior engine behind its drawers, dialogs, and toasts.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const NPM_PACKAGES = [ '@artisanpack-ui/css@^1', '@artisanpack-ui/behavior@^1' ];

    /**
     * The lines that register the design system's behavior engine, for
     * `resources/js/app.js`. Livewire starts Alpine, so the engine registers
     * on `alpine:init`.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const JS_GLOBALS = [
        "import apuiBehavior from '@artisanpack-ui/behavior/alpine';",
        "document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );",
    ];

    /**
     * The commands that build the host's assets once the lines are added:
     * the production build, then the development server alternative.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const BUILD_COMMANDS = [ 'npm run build', 'npm run dev' ];

    /**
     * The full front-end setup guide.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const FRONT_END_DOCS = 'https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/blob/main/docs/installation/front-end.md';

    /**
     * The directive a host's own layout adds to its `<head>` so the shopper's
     * theme applies before the page paints.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const THEME_SCRIPT = '@apuiThemeScript';

    /**
     * The include a host's own layout needs (cart drawer, toasts, merge prompt).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const LAYOUT_INCLUDE = "@include( 'ecommerce-storefront::partials.global' )";

    /**
     * The one-line wrapper for hosts whose layout is a Blade component.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const COMPONENT_LAYOUT_WRAPPER = "<x-layouts.app :title=\"\$__env->yieldContent( 'title' )\">@yield( 'content' ) @include( 'ecommerce-storefront::partials.global' )</x-layouts.app>";

    /**
     * The config file the install publishes.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CONFIG_FILE = 'artisanpack/ecommerce-storefront-livewire.php';

    /**
     * @var string
     */
    protected $signature = 'ecommerce-storefront:install
        {--force : Overwrite a previously published config file without asking.}';

    /**
     * @var string
     */
    protected $description = 'Publish the ecommerce storefront config and print the setup steps.';

    /**
     * Runs the command.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function handle(): int
    {
        $this->components->info( __( 'Installing the ecommerce storefront.' ) );

        $this->publishConfig();
        $this->printFrontEnd();
        $this->printLayout();

        $this->newLine();
        $this->checkAuthRoutes();
        $this->checkPaymentGateways();
        $this->checkVisualEditor();

        return self::SUCCESS;
    }

    /**
     * Publishes the config, asking before overwriting a published copy.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function publishConfig(): void
    {
        $exists = File::exists( config_path( self::CONFIG_FILE ) );
        $force  = (bool) $this->option( 'force' );

        if ( $exists && ! $force ) {
            $force = $this->components->confirm(
                __( 'config/:file already exists. Overwrite it?', [ 'file' => self::CONFIG_FILE ] ),
                false,
            );

            if ( ! $force ) {
                $this->components->info( __( 'Kept your existing config file.' ) );

                return;
            }
        }

        $this->call( 'vendor:publish', [
            '--tag'   => 'ecommerce-storefront-config',
            '--force' => $force,
        ] );
    }

    /**
     * Prints the design-system setup: the npm packages, the stylesheet lines,
     * the behavior engine, the build step, and the daisyUI reminder.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function printFrontEnd(): void
    {
        $this->newLine();
        $this->components->info( __( 'Install the front-end dependencies:' ) );
        $this->line( '    npm install ' . implode( ' ', self::NPM_PACKAGES ) );

        $this->newLine();
        $this->components->info( __( 'Add these lines to your main stylesheet (e.g. resources/css/app.css):' ) );

        foreach ( self::STYLESHEET_IMPORTS as $line ) {
            $this->line( '    ' . $line );
        }

        foreach ( self::TAILWIND_SOURCES as $source ) {
            $this->line( '    ' . $source );
        }

        $this->components->info( __( 'Keep the @layer line first in the file, above @import \'tailwindcss\'.' ) );
        $this->components->info( __( 'And the component library\'s views, if your stylesheet doesn\'t list them already:' ) );

        foreach ( self::COMPONENT_LIBRARY_SOURCES as $source ) {
            $this->line( '    ' . $source );
        }

        $this->newLine();
        $this->components->info( __( 'Add these lines to your main script (e.g. resources/js/app.js):' ) );

        foreach ( self::JS_GLOBALS as $line ) {
            $this->line( '    ' . $line );
        }

        $this->newLine();
        $this->components->info( __( 'Then build your assets (or run the development server while you work):' ) );

        foreach ( self::BUILD_COMMANDS as $command ) {
            $this->line( '    ' . $command );
        }

        $this->newLine();
        $this->components->warn( __( 'Remove daisyUI: the storefront renders on the ArtisanPack UI design system.' ) );
        $this->components->info( __( 'Full front-end setup: :url', [ 'url' => self::FRONT_END_DOCS ] ) );
    }

    /**
     * Prints how to use the host's own layout.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function printLayout(): void
    {
        $this->newLine();
        $this->components->info( __( 'Storefront pages use the package layout. To use your own, set storefront.layout in the config to a layout that yields "title" and "content" and includes:' ) );
        $this->line( '    ' . self::LAYOUT_INCLUDE );
        $this->components->info( __( 'Add @stack( \'head\' ) inside its <head> for page descriptions, canonical URLs, and structured data.' ) );
        $this->components->info( __( 'Add :directive inside its <head>, before your Vite entries, so the shopper\'s light or dark theme applies before the page paints.', [ 'directive' => self::THEME_SCRIPT ] ) );

        $this->newLine();
        $this->components->info( __( 'If your layout is a Blade component, point storefront.layout at a view containing just:' ) );
        $this->line( '    ' . self::COMPONENT_LAYOUT_WRAPPER );
    }

    /**
     * Warns when the host's sign-in or registration route is missing (D6).
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function checkAuthRoutes(): void
    {
        $routes = [
            'login_route'    => (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.login_route', 'login' ),
            'register_route' => (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.register_route', 'register' ),
        ];

        $missing = false;

        foreach ( $routes as $key => $name ) {
            if ( '' !== $name && Route::has( $name ) ) {
                continue;
            }

            $missing = true;

            $this->components->warn( __( 'No ":route" route is defined (auth.:key). The storefront links to it for signing in and registering.', [ 'route' => $name, 'key' => $key ] ) );
        }

        if ( $missing ) {
            $this->components->info( __( 'Install an auth starter kit, or set auth.login_route and auth.register_route to your own route names.' ) );

            return;
        }

        $this->components->info( __( 'Sign-in and registration routes found.' ) );
    }

    /**
     * Warns when no payment gateway is registered and configured.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function checkPaymentGateways(): void
    {
        $keys = app( PaymentGatewayRegistry::class )->keys();

        if ( [] === $keys ) {
            $this->components->warn( __( 'No payment gateway is registered, so shoppers can\'t pay at checkout. Enable Stripe (ECOMMERCE_STRIPE_ENABLED and its keys) or install a gateway package.' ) );

            return;
        }

        $configured = array_values( array_filter( $keys, fn ( string $key ): bool => $this->gatewayConfigured( $key ) ) );

        if ( [] === $configured ) {
            $this->components->warn( __( 'A payment gateway is registered but not configured: :gateways. Add its API keys before taking orders.', [ 'gateways' => implode( ', ', $keys ) ] ) );

            return;
        }

        $this->components->info( __( 'Payment gateways ready: :gateways.', [ 'gateways' => implode( ', ', $configured ) ] ) );
    }

    /**
     * Explains the template option when visual-editor is installed but its
     * templates are off.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function checkVisualEditor(): void
    {
        // The same check block registration makes, so the two never disagree.
        if ( ! class_exists( StorefrontBlocks::EDITOR ) || ! $this->laravel->bound( StorefrontBlocks::EDITOR ) ) {
            return;
        }

        if ( (bool) config( 'artisanpack.ecommerce-storefront-livewire.visual_editor.templates', false ) ) {
            $this->components->info( __( 'Storefront pages render through visual-editor templates.' ) );

            return;
        }

        $this->components->warn( __( 'visual-editor is installed, but storefront pages don\'t use its templates (visual_editor.templates is off). Turn it on to design product, category, cart, and checkout pages in the editor; it is off by default so installing the editor doesn\'t change an existing store.' ) );
    }

    /**
     * Whether a registered gateway has its credentials. The built-in Stripe
     * gateway needs its secret and publishable keys; other gateways manage
     * their own settings and count as configured once registered.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The gateway key.
     *
     * @return bool
     */
    protected function gatewayConfigured( string $key ): bool
    {
        if ( StripeGateway::KEY !== $key ) {
            return true;
        }

        return '' !== trim( (string) config( 'artisanpack.ecommerce.gateways.stripe.secret_key', '' ) )
            && '' !== trim( (string) config( 'artisanpack.ecommerce.gateways.stripe.publishable_key', '' ) );
    }
}
