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
 * `<x-artisanpack-ec-empty-state icon="o-shopping-bag" :title="…" :description="…">actions</x-artisanpack-ec-empty-state>`
 *
 * livewire-ui-components has no empty-state component yet (spec §8.1,
 * livewire-ui-components#111), so this composes one from an icon and text.
 * When the library ships one, this is the one file to swap. The admin ships
 * the same component with the same props, so either copy can win the alias
 * when both packages are installed.
 *
 * The block is a `status` region so a screen reader announces it when
 * filtering empties a listing.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class EmptyState extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  string       $title        The headline.
     * @param  string|null  $description  The supporting text.
     * @param  string       $icon         The icon name.
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public string $icon = 'o-inbox',
    ) {
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
