<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Route;

/*
 * <x-artisanpack-ec-account-shell> on the design system (#72): a sidebar with
 * the shopper, the account Menu and "Sign out" from 1024px; a row of tabs
 * below it; a PageHeader holding the page's h1.
 */

/**
 * Renders the shell around some content for the signed-in shopper.
 */
function renderAccountShell(): string
{
    return (string) test()->blade( '<x-artisanpack-ec-account-shell title="Orders"><p data-shell-content>Body</p></x-artisanpack-ec-account-shell>' );
}

it( 'heads the content with the page title as the one h1', function (): void {
    shopper();

    $html = renderAccountShell();

    expect( $html )->toContain( 'class="apui-page-header ec-account__title"' )
        ->and( substr_count( $html, '<h1' ) )->toBe( 1 )
        ->and( $html )->toMatch( '/<h1[^>]*>Orders<\/h1>/' )
        ->and( $html )->toContain( 'data-shell-content' );
} );

it( 'shows the shopper\'s initials, name and email in the sidebar', function (): void {
    shopper();
    auth()->user()->forceFill( [ 'name' => 'Dana Reyes', 'email' => 'dana@example.test' ] )->save();

    $html = renderAccountShell();

    expect( $html )->toContain( 'data-account-identity' )
        ->toContain( '>DR</span>' )
        ->toContain( 'Dana Reyes' )
        ->toContain( 'dana@example.test' )
        ->toContain( 'data-color="primary"' );
} );

it( 'lists the account pages twice, as the sidebar Menu and the tab row, both named "Account"', function (): void {
    shopper();

    $html = renderAccountShell();

    expect( substr_count( $html, 'aria-label="Account"' ) )->toBe( 2 )
        ->and( $html )->toContain( 'class="apui-menu"' )
        ->toContain( 'data-account-nav-entry="orders.index"' )
        ->toContain( 'data-account-tab="orders.index"' )
        ->not->toContain( 'wire:navigate' );
} );

it( 'marks the current page in both navigations', function (): void {
    shopper();

    $html = (string) $this->get( route( 'artisanpack.ecommerce.account.orders.index' ) )->assertOk()->getContent();

    preg_match( '/<a[^>]*data-account-tab="orders\.index"[^>]*>/', $html, $tab );
    preg_match( '/<a[^>]*data-account-tab="dashboard"[^>]*>/', $html, $other );

    expect( $tab[0] ?? '' )->toContain( 'aria-current="page"' )
        ->and( $other[0] ?? '' )->not->toContain( 'aria-current' );
} );

it( 'posts "Sign out" to the host\'s logout route, and leaves it out without one', function (): void {
    shopper();

    expect( renderAccountShell() )->not->toContain( 'data-account-sign-out' );

    Route::post( 'logout', static fn (): string => 'bye' )->name( 'logout' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    $html = renderAccountShell();

    expect( $html )->toContain( 'data-account-sign-out' )
        ->toContain( 'action="' . route( 'logout' ) . '"' )
        ->toContain( 'name="_token"' )
        ->toContain( 'Sign out' )
        ->toContain( 'data-variant="ghost"' );
} );

it( 'follows a custom logout route name', function (): void {
    shopper();
    config()->set( 'artisanpack.ecommerce-storefront-livewire.auth.logout_route', 'auth.sign-out' );
    Route::post( 'sign-out', static fn (): string => 'bye' )->name( 'auth.sign-out' );
    Route::post( 'logout', static fn (): string => 'bye' )->name( 'logout' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    expect( renderAccountShell() )->toContain( 'action="' . route( 'auth.sign-out' ) . '"' );
} );

it( 'draws the entries\' icons from the design-system registry', function (): void {
    shopper();

    $html = renderAccountShell();

    preg_match( '/<a[^>]*data-account-nav-entry="addresses"[^>]*>.*?<\/a>/s', $html, $entry );

    expect( $entry[0] ?? '' )->toContain( 'data-part="icon"' )->toContain( '<svg' );
} );

it( 'keeps "Sign out" and the shopper reachable below 1024px, outside the sidebar', function (): void {
    shopper();
    auth()->user()->forceFill( [ 'name' => 'Dana Reyes' ] )->save();

    Route::post( 'logout', static fn (): string => 'bye' )->name( 'logout' );
    app( 'router' )->getRoutes()->refreshNameLookups();

    $html = renderAccountShell();

    preg_match( '/<div class="ec-account__bar" data-account-bar>.*?(?=<nav aria-label="Account" class="ec-account__tabs")/s', $html, $bar );
    preg_match( '/<div class="ec-account__sidebar" data-account-sidebar>.*?(?=<div class="ec-account__bar")/s', $html, $sidebar );

    expect( $bar[0] ?? '' )->toContain( 'data-account-bar-sign-out' )
        ->toContain( 'method="POST"' )
        ->toContain( 'action="' . route( 'logout' ) . '"' )
        ->toContain( 'name="_token"' )
        ->toContain( 'Sign out' )
        ->toContain( 'Signed in as Dana Reyes' )
        ->and( $sidebar[0] ?? '' )->toContain( 'data-account-sign-out' )
        ->not->toContain( 'data-account-bar' );
} );

it( 'leaves the narrow-layout "Sign out" out without a logout route', function (): void {
    shopper();

    $html = renderAccountShell();

    expect( $html )->toContain( 'data-account-bar-identity' )
        ->not->toContain( 'data-account-bar-sign-out' );
} );

it( 'hides the narrow-layout bar from 1024px, where the sidebar shows', function (): void {
    $css = (string) file_get_contents( dirname( __DIR__, 3 ) . '/resources/css/storefront.css' );

    expect( $css )->toMatch( '/@media \(min-width: 64rem\) \{[^@]*\.ec-account__tabs,\s*\.ec-account__bar \{\s*display: none;/s' );
} );
