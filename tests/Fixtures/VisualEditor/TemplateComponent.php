<?php

/**
 * Stand-in for visual-editor's `<x-ve-template>`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Fixtures\VisualEditor;

use Illuminate\View\Component;

/**
 * Renders the slug it was asked for.
 *
 * @since 1.0.0
 */
class TemplateComponent extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  string  $slug  Template slug.
     */
    public function __construct( public string $slug )
    {
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function render(): string
    {
        return '<div data-ve-template="{{ $slug }}">Saved template</div>';
    }
}
