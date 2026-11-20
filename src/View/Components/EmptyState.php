<?php

/**
 * Empty-state component.
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
 * `<x-artisanpack-ec-empty-state icon="shopping-bag" :title="…" :description="…" card>actions</x-artisanpack-ec-empty-state>`
 *
 * The design-system EmptyState (#84): an icon tile, a title, one line of
 * help, and the actions passed in the slot (at most two: one solid primary
 * when there is a clear next step, else outline neutral). It is a `status`
 * region, so a screen reader announces it when filtering empties a listing.
 *
 * `card` puts it in a Card, for when it replaces a list; `size="sm"` is
 * the compact form for inside other cards. The admin ships the same
 * component with the same props, so either copy can win the alias when
 * both packages are installed.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class EmptyState extends Component
{
    /**
     * The sizes the design system draws.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const SIZES = [ 'md', 'sm' ];

    /**
     * @since 1.0.0
     * @since 2.0.0 Added `size`, `headingLevel` and `card`; the default icon is `inbox`.
     *
     * @param  string       $title         The headline.
     * @param  string|null  $description   The supporting text.
     * @param  string       $icon          The icon name (a design-system icon, or a blade-icons name such as `o-inbox`).
     * @param  string       $size          `md`, or `sm` inside another card.
     * @param  int          $headingLevel  The title's heading level (2 to 6).
     * @param  bool         $card          Draw it in a Card, for when it replaces a list.
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public string $icon = 'inbox',
        public string $size = 'md',
        public int $headingLevel = 2,
        public bool $card = false,
    ) {
        $this->size = in_array( $this->size, self::SIZES, true ) ? $this->size : 'md';
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
        return view( 'ecommerce-storefront::components.empty-state' );
    }
}
