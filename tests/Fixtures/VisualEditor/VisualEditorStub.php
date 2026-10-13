<?php

/**
 * Test-only stand-in for visual-editor's main service.
 *
 * visual-editor is a soft dependency, so it isn't installed here.
 * `require_once`-loading this file makes `class_exists()` true for the
 * service; a test then binds an instance to simulate an installed editor.
 * It records what `registerServerBlock()` receives.
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor;

if ( ! class_exists( __NAMESPACE__ . '\\VisualEditor', false ) ) {
    /**
     * Stub. The real class lives in artisanpack-ui/visual-editor.
     */
    class VisualEditor
    {
        /**
         * Registered server blocks, by name.
         *
         * @var array<string, array{metadata: array<string, mixed>, render: callable, callbacks: array<string, callable>}>
         */
        public array $blocks = [];

        /**
         * Records a server block.
         *
         * @param  array<string, mixed>     $metadata   Metadata.
         * @param  callable                 $render     Render callback.
         * @param  array<string, callable>  $callbacks  Other callbacks.
         */
        public function registerServerBlock( string $name, array $metadata, callable $render, array $callbacks = [] ): object
        {
            $this->blocks[ $name ] = [ 'metadata' => $metadata, 'render' => $render, 'callbacks' => $callbacks ];

            return (object) [ 'name' => $name ];
        }
    }
}
