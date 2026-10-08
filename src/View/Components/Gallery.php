<?php

/**
 * Product gallery component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\View\Components;

use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-gallery :images="ProductImages::gallery( $product )" :name="$product->name" />`
 *
 * The product page gallery (spec §7.2): the main image, thumbnails that
 * switch it, zoom on the main image, and a lightbox (a native modal
 * `<dialog>`, so Escape and focus return work) with previous/next and arrow
 * keys. Another component switches the image by dispatching the browser
 * event `ecommerce-gallery-show` with the image `url` and, when the gallery
 * has one, its `scope` (the product page does this when the chosen variant
 * has its own image).
 *
 * Local stand-in for the library's image slider thumbnails, external
 * control, and zoom (livewire-ui-components#123, spec §10 U4); when they
 * ship, this is the one file to swap.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Gallery extends Component
{
    /**
     * Where the thumbnails can go: below the image, beside it (start or
     * end, from `sm` up), or nowhere.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const THUMBNAIL_POSITIONS = [ 'bottom', 'start', 'end', 'none' ];

    /**
     * The images, normalised.
     *
     * @since 1.0.0
     *
     * @var array<int, array{url: string, srcset: string|null, full: string, alt: string}>
     */
    public array $items;

    /**
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $images  Images (`url`, `srcset`, `full`, `alt`), as from `ProductImages::gallery()`.
     * @param  string                            $name    The product name (for labels and fallback alt text).
     * @param  int                               $active  The index shown first.
     * @param  string|null                       $scope       Only follow `ecommerce-gallery-show` events with this `scope`.
     * @param  string                            $thumbnails  Thumbnail position ({@see self::THUMBNAIL_POSITIONS}).
     * @param  bool                              $zoom        Zoom the image in place on click.
     */
    public function __construct(
        public array $images,
        public string $name,
        public int $active = 0,
        public ?string $scope = null,
        public string $thumbnails = 'bottom',
        public bool $zoom = true,
    ) {
        $this->items      = [];
        $this->thumbnails = in_array( $thumbnails, self::THUMBNAIL_POSITIONS, true ) ? $thumbnails : 'bottom';

        foreach ( $images as $image ) {
            $url = is_array( $image ) ? ProductImages::safeUrl( $image['url'] ?? null ) : null;

            if ( null === $url ) {
                continue;
            }

            $this->items[] = [
                'url'    => $url,
                'srcset' => is_string( $image['srcset'] ?? null ) ? $image['srcset'] : null,
                'full'   => ProductImages::safeUrl( $image['full'] ?? null ) ?? $url,
                'alt'    => is_string( $image['alt'] ?? null ) && '' !== $image['alt'] ? $image['alt'] : $name,
            ];
        }

        $this->active = max( 0, min( $this->active, count( $this->items ) - 1 ) );
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
        return view( 'ecommerce-storefront::components.gallery' );
    }
}
