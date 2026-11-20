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

use ArtisanPack\LivewireUiComponents\Support\RenderedIds;
use ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Js;
use Illuminate\View\Component;
use Livewire\Livewire;
use Throwable;

/**
 * `<x-artisanpack-ec-gallery :images="ProductImages::gallery( $product )" :name="$product->name" />`
 *
 * The product page gallery (spec §7.2): the square main image with a
 * "1 / 4" counter and a Zoom button, thumbnails that switch it, and the
 * design system's Lightbox (`apuiLightbox`: a modal dialog with previous,
 * next, arrow keys that follow the text direction, Escape and focus return)
 * for the full-size images. Another component switches the image by
 * dispatching the browser event `ecommerce-gallery-show` with the image
 * `url` and, when the gallery has one, its `scope` (the product page does
 * this when the chosen variant has its own image).
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
     * The claimed id, once worked out.
     *
     * @since 2.0.0
     *
     * @var string|null
     */
    private ?string $resolvedBaseId = null;

    /**
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $images  Images (`url`, `srcset`, `full`, `alt`), as from `ProductImages::gallery()`.
     * @param  string                            $name    The product name (for labels and fallback alt text).
     * @param  int                               $active  The index shown first.
     * @param  string|null                       $scope       Only follow `ecommerce-gallery-show` events with this `scope`.
     * @param  string                            $thumbnails  Thumbnail position ({@see self::THUMBNAIL_POSITIONS}).
     * @param  bool                              $zoom        Open the image in the design system's lightbox on click.
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
     * The id the gallery's parts hang off: the caller's `id`, else one
     * derived from the images, so it stays the same across Livewire renders.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function baseId(): string
    {
        if ( null !== $this->resolvedBaseId ) {
            return $this->resolvedBaseId;
        }

        $id = $this->attributes?->get( 'id' );

        if ( is_string( $id ) && '' !== $id ) {
            return $this->resolvedBaseId = $id;
        }

        $id = 'ec-gallery-' . substr( hash( 'xxh128', implode( '|', [
            $this->name,
            $this->scope ?? '',
            $this->thumbnails,
            $this->zoom ? 'zoom' : '',
            ...array_column( $this->items, 'url' ),
        ] ) ), 0, 12 );

        // Claimed like the library's own ids: a second gallery with the same
        // images on the page gets "-2", so the lightboxes never share parts.
        return $this->resolvedBaseId = app( RenderedIds::class )->claim( self::livewireScope(), $id );
    }

    /**
     * The design system's `apuiLightbox` Alpine component for the zoom: the
     * full-size images, with the counter in the store's words.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function lightboxData(): string
    {
        $images = array_map(
            fn ( array $item ): array => [ 'src' => $item['full'], 'alt' => $item['alt'], 'caption' => $this->name ],
            $this->items,
        );

        return sprintf(
            'apuiLightbox({ id: %s, images: %s, open: false, index: %d, modal: true, labels: { counter: %s } })',
            Js::from( $this->baseId() )->toHtml(),
            Js::from( $images )->toHtml(),
            $this->active,
            Js::from( __( ':index / :count', [ 'index' => '{index}', 'count' => '{count}' ] ) )->toHtml(),
        );
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

    /**
     * The id scope: the rendering Livewire component's id, or '' outside one.
     *
     * @since 2.0.0
     *
     * @return string
     */
    private static function livewireScope(): string
    {
        try {
            $component = Livewire::current();
        } catch ( Throwable ) {
            return '';
        }

        return is_object( $component ) && method_exists( $component, 'getId' ) ? (string) $component->getId() : '';
    }
}
