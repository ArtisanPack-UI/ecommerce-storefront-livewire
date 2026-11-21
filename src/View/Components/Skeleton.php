<?php

/**
 * Skeleton placeholder component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-skeleton variant="card" :count="4" />`
 *
 * A loading placeholder for lazy sections (related products, reviews),
 * composed from the design-system Skeleton's block and text shapes in the
 * layout of what it stands in for: text lines, product cards in the
 * catalog grid, or a square image.
 *
 * The shapes sit in an `aria-busy` region hidden from screen readers, with
 * a "Loading…" status beside it.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Skeleton extends Component
{
    /**
     * The supported shapes.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const VARIANTS = [ 'text', 'card', 'image' ];

    /**
     * @since 1.0.0
     *
     * @param  string       $variant  `text` (lines), `card` (product cards), or `image`.
     * @param  int          $count    Lines or cards to draw.
     * @param  string|null  $label    The text announced while loading.
     */
    public function __construct(
        public string $variant = 'text',
        public int $count = 3,
        public ?string $label = null,
    ) {
        $this->variant = in_array( $this->variant, self::VARIANTS, true ) ? $this->variant : 'text';
        $this->count   = max( 1, min( 24, $this->count ) );
        $this->label ??= __( 'Loading…' );
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-storefront::components.skeleton' );
    }
}
