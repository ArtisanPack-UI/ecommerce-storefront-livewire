<?php

/**
 * Storefront toaster component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPack\LivewireUiComponents\View\Components\Toaster as BaseToaster;

/**
 * `<x-artisanpack-ec-toaster />`
 *
 * The library's toaster, placed as the storefront design asks (#97): toasts
 * stack at the top end of the page from 768px and along the bottom on a
 * phone, where the thumb is.
 *
 * The toaster's placement is set once, when Alpine starts it, and its
 * stacking depends on it, so a stylesheet can't move the stack. This
 * component picks the placement in the browser instead, from the viewport
 * width when the page loads; everything else, including the markup, is the
 * library's.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      2.0.0
 */
class Toaster extends BaseToaster
{
    /**
     * The media query that selects the desktop placement.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const DESKTOP_QUERY = '(min-width: 48rem)';

    /**
     * Creates the toaster.
     *
     * @since 2.0.0
     *
     * @param  string       $placement        The placement from 768px.
     * @param  string       $mobilePlacement  The placement below 768px.
     * @param  int          $max              The most toasts shown at once.
     * @param  int          $gap              The gap between toasts, in pixels.
     * @param  int          $duration         How long a toast stays, in milliseconds.
     * @param  string|null  $label            The region's accessible name.
     * @param  string|null  $closeLabel       The Dismiss buttons' accessible name.
     */
    public function __construct(
        string $placement = 'top-end',
        public string $mobilePlacement = 'bottom',
        int $max = 5,
        int $gap = 8,
        int $duration = 5000,
        ?string $label = null,
        ?string $closeLabel = null,
    ) {
        parent::__construct( $placement, $max, $gap, $duration, $label, $closeLabel );
    }

    /**
     * The toaster's Alpine data, with the placement chosen from the
     * viewport.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function alpineData(): string
    {
        $data    = parent::alpineData();
        $desktop = $this->validPlacement( $this->placement, 'top-end' );
        $mobile  = $this->validPlacement( $this->mobilePlacement, 'bottom' );

        // apuiToaster( {...} ) → apuiToaster( Object.assign( {...}, { placement: … } ) ).
        // Should the library change that shape, keep its toaster as it is.
        if ( ! str_starts_with( $data, 'apuiToaster(' ) || ! str_ends_with( $data, ')' ) ) {
            return $data;
        }

        $config = substr( $data, strlen( 'apuiToaster(' ), -1 );

        return 'apuiToaster(Object.assign(' . $config . ', { placement: window.matchMedia(' . json_encode( self::DESKTOP_QUERY ) . ').matches ? ' . json_encode( $desktop ) . ' : ' . json_encode( $mobile ) . ' }))';
    }

    /**
     * A placement the toaster knows, or the fallback.
     *
     * @since 2.0.0
     *
     * @param  string  $placement  The placement.
     * @param  string  $fallback   The fallback.
     *
     * @return string
     */
    protected function validPlacement( string $placement, string $fallback ): string
    {
        return in_array( $placement, self::PLACEMENTS, true ) ? $placement : $fallback;
    }
}
