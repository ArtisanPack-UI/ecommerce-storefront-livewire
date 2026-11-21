<?php

declare( strict_types=1 );

namespace Tests\Browser\Support;

use PHPUnit\Framework\Assert;

/**
 * axe checks for the browser suite (#43).
 *
 * Runs every axe rule and fails on any violation, except the ones listed in
 * {@see self::LIBRARY_ISSUES}: failures that come from livewire-ui-components
 * markup this package cannot change. Each entry names the axe rule and a
 * CSS selector; a node is ignored only when it (or an ancestor) matches.
 * Remove an entry once the library fixes it, and the suite enforces it here.
 */
final class Accessibility
{
    /**
     * Known library failures: axe rule => selector the node must sit in.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const LIBRARY_ISSUES = [
        // livewire-ui-components 3.0: the Drawer's panel is an <aside> with
        // role="dialog", a role axe doesn't allow on <aside>.
        [ 'aria-allowed-role', '[data-scope="drawer"][data-part="content"]' ],
        // ArtisanPack-UI/livewire-ui-components#117: `x-artisanpack-select`
        // labels with a <legend>, so the control itself has no accessible
        // name.
        [ 'select-name', 'fieldset' ],
        // ArtisanPack-UI/livewire-ui-components#129:
        // `x-artisanpack-breadcrumbs` puts the separator <span>s straight
        // into its <ul>.
        [ 'list', 'nav[aria-label="Breadcrumb"]' ],
        // ArtisanPack-UI/livewire-ui-components#130: `x-artisanpack-collapse`
        // inside an accordion renders an unlabelled radio as the section
        // toggle.
        [ 'label', '.collapse' ],
        // ArtisanPack-UI/livewire-ui-components#131: form components print
        // field errors in `text-error`, about 3:1 on the default light theme.
        [ 'color-contrast', '[x-class="text-error"]' ],
    ];

    /**
     * Asserts the page has no accessibility violations beyond the known
     * library ones.
     *
     * @param  object       $page    The page (a Pest browser webpage).
     * @param  string|null  $within  A selector to check only that part of the page (an open modal dialog).
     *
     * @return object
     */
    public static function assertAccessible( object $page, ?string $within = null ): object
    {
        $page->assertScript( 'document.readyState', 'complete' );

        $ignored = json_encode( self::LIBRARY_ISSUES );
        $context = null === $within ? 'document' : 'document.querySelector( ' . json_encode( $within ) . ' )';

        /** @var array<int, array{id: string, impact: string|null, help: string, nodes: array<int, string>}> $violations */
        $violations = (array) $page->script( <<<JS
            async () => {
                const ignored = {$ignored};
                // Colors mid-transition (a button re-enabled after a request) aren't the final ones.
                // Looping animations (a skeleton's shimmer) never finish, so only the finite ones are awaited.
                // Livewire can drop a loading state a frame after the morph the test waited for, starting a
                // transition after the first look, so wait two frames before each look until none are running.
                const finite = () => document.getAnimations()
                    .filter( ( animation ) => Number.isFinite( animation.effect?.getComputedTiming().iterations ?? 1 ) );
                const frames = () => new Promise( ( resolve ) => requestAnimationFrame( () => requestAnimationFrame( resolve ) ) );

                for ( let attempt = 0; attempt < 10; attempt++ ) {
                    await frames();

                    const running = finite();

                    if ( 0 === running.length ) {
                        break;
                    }

                    await Promise.all( running.map( ( animation ) => animation.finished.catch( () => null ) ) );
                }
                // target-size (WCAG 2.2 SC 2.5.8, 24px) is off by default in axe 4.x.
                const { violations } = await window.axe.run( {$context}, { rules: { 'target-size': { enabled: true } } } );

                return violations
                    .map( ( violation ) => ( {
                        id: violation.id,
                        impact: violation.impact,
                        help: violation.help,
                        nodes: violation.nodes
                            .filter( ( node ) => {
                                const element = document.querySelector( node.target[ 0 ] );

                                return ! ignored.some( ( [ rule, selector ] ) => rule === violation.id && element?.closest( selector ) );
                            } )
                            .map( ( node ) => node.target.join( ' ' ) + ' :: ' + node.html.slice( 0, 200 ) ),
                    } ) )
                    .filter( ( violation ) => violation.nodes.length > 0 );
            }
            JS );

        $report = implode( "\n", array_map(
            static fn ( array $violation ): string => sprintf( "- [%s] %s (%s)\n    %s", $violation['impact'] ?? 'n/a', $violation['help'], $violation['id'], implode( "\n    ", $violation['nodes'] ) ),
            $violations,
        ) );

        Assert::assertSame( [], $violations, "Accessibility violations:\n" . $report );

        return $page;
    }
}
