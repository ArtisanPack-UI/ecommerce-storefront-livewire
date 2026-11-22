<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\StorefrontStyles;

/**
 * The inlined stylesheet: minified, and the rules the 2.0 review asked for.
 */
function storefrontStylesheetSource(): string
{
    return (string) file_get_contents( StorefrontStyles::path() );
}

describe( 'minified output', function (): void {
    it( 'inlines a minified stylesheet that keeps its layer and selectors', function (): void {
        $css = StorefrontStyles::css();

        expect( $css )->not->toContain( '/*' )
            ->and( strlen( $css ) )->toBeLessThan( 125_000 )
            ->and( strlen( $css ) )->toBeLessThan( strlen( storefrontStylesheetSource() ) )
            ->and( $css )->toContain( '.ec-product-card' )
            ->and( $css )->toContain( '@layer apui.components{' )
            ->and( $css )->not->toContain( "\n" );
    } );

    it( 'drops comments and collapses whitespace but keeps strings as written', function (): void {
        $css = "/* head */\n.a  >  .b ,\n.c :hover {\n    content: '/* not a comment */  ;}';\n    font-family: \"A  B\", serif;\n    margin: calc( 1px + 2px );\n}\n@media (min-width: 48rem) {\n    .d::after { content: ''; }\n}\n";

        expect( StorefrontStyles::minify( $css ) )->toBe(
            ".a > .b,.c :hover{content: '/* not a comment */  ;}';font-family: \"A  B\",serif;margin: calc( 1px + 2px )}@media (min-width: 48rem){.d::after{content: ''}}",
        );
    } );

    it( 'keeps escaped quotes inside strings', function (): void {
        expect( StorefrontStyles::minify( ".a { content: 'it\\'s  /* x */'; }" ) )
            ->toBe( ".a{content: 'it\\'s  /* x */'}" );
    } );

    it( 'still gives the minified inline styles the CSP nonce', function (): void {
        Illuminate\Support\Facades\Vite::useCspNonce( 'n0nce' );

        expect( $this->get( route( 'artisanpack.ecommerce.storefront.catalog' ) )->getContent() )
            ->toMatch( '/<style data-ecommerce-storefront-styles\s+nonce="n0nce"\s*>@layer apui\.components\{/' );
    } );
} );

describe( 'review rules', function (): void {
    it( 'leaves mirroring the related-products chevrons to the design system (FE-12)', function (): void {
        expect( storefrontStylesheetSource() )->not->toContain( '.ec-related__control svg' );

        $this->blade( '<x-artisanpack-button icon-only leading-icon="chevron-left" aria-label="Previous" />' )
            ->assertSee( 'data-mirror', false );
    } );

    it( 'pads the scroll root below the sticky header (FE-13)', function (): void {
        expect( storefrontStylesheetSource() )
            ->toContain( 'html:has(> body.ec-storefront .apui-navbar[data-sticky]) {' )
            ->toContain( 'scroll-padding-block-start: calc(var(--apui-navbar-height) + 16px);' );
    } );

    it( 'marks a selected swatch and the current chip with a 2px primary border (FE-14)', function (): void {
        $css = storefrontStylesheetSource();

        preg_match( '/\n    \.ec-swatch:has\(\.ec-swatch__input:checked\) \{([^}]+)\}/', $css, $swatch );
        preg_match( '/\n    \.ec-chip\[data-variant="select"\]\[aria-current\] \{([^}]+)\}/', $css, $chip );

        foreach ( [ $swatch[1], $chip[1] ] as $rule ) {
            expect( $rule )->toContain( 'border-color: var(--apui-color-primary);' )
                ->toContain( 'box-shadow: inset 0 0 0 calc(var(--apui-selected-indicator-width) - var(--apui-border-width)) var(--apui-color-primary);' );
        }

        expect( $css )->toContain( '.ec-swatch:has(.ec-swatch__input:checked):has(.ec-swatch__input:focus-visible) {' )
            ->toContain( '.ec-chip[data-variant="select"][aria-current]:focus-visible {' );
    } );

    it( 'rings a card only for keyboard focus on its name link (FE-15)', function (): void {
        expect( storefrontStylesheetSource() )
            ->not->toContain( ':focus-within {' )
            ->toContain( '.ec-product-card:has(.ec-product-card__name a:focus-visible) {' )
            ->toContain( '.ec-recent__card:has(.ec-recent__name a:focus-visible) {' )
            ->toContain( '.ec-category-tile:has(.ec-category-tile__name a:focus-visible) {' );
    } );

    it( 'leaves room for the focus ring in the related-products scroller (FE-16)', function (): void {
        preg_match( '/\n    \.ec-related__list \{([^}]+)\}/', storefrontStylesheetSource(), $list );

        expect( $list[1] )->toContain( 'padding: 4px 4px 8px;' )
            ->toContain( 'margin: 0 -4px;' )
            ->toContain( 'scroll-padding-inline: 4px;' );
    } );

    it( 'lets a long chip label wrap instead of overflowing (FE-17)', function (): void {
        preg_match( '/\n    \.ec-chip \{([^}]+)\}/', storefrontStylesheetSource(), $chip );

        expect( $chip[1] )->not->toContain( 'white-space: nowrap' )
            ->toContain( 'overflow-wrap: anywhere;' )
            ->toContain( 'max-inline-size: 100%;' )
            ->toContain( 'min-inline-size: 0;' );
    } );
} );
