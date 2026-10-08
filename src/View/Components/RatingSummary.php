<?php

/**
 * Rating summary component.
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
use NumberFormatter;

/**
 * `<x-artisanpack-ec-rating-summary :rating="$product->avg_rating" :count="$product->reviews_count" :href="$reviewsUrl" />`
 *
 * A star rating with the review count, linked to the reviews when `href`
 * is given. The stars are decorative; screen readers hear "Rated 4.5
 * out of 5" and the count. With no reviews it says so (or renders nothing
 * when `hide-empty` is set).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class RatingSummary extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  float|int|null  $rating     The average rating, 0–5.
     * @param  int|null        $count      How many reviews it averages.
     * @param  string|null     $href       Where the review count links to.
     * @param  bool            $hideEmpty  Render nothing when there are no reviews.
     */
    public function __construct(
        public float|int|null $rating = 0,
        public ?int $count = 0,
        public ?string $href = null,
        public bool $hideEmpty = false,
    ) {
        $this->rating = max( 0.0, min( 5.0, (float) $this->rating ) );
        $this->count  = max( 0, (int) $this->count );
    }

    /**
     * The rating to one decimal in the app locale ("4.5", "4,5").
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function formattedRating(): string
    {
        $formatter = new NumberFormatter( app()->getLocale(), NumberFormatter::DECIMAL );
        $formatter->setAttribute( NumberFormatter::MIN_FRACTION_DIGITS, 0 );
        $formatter->setAttribute( NumberFormatter::MAX_FRACTION_DIGITS, 1 );

        $formatted = $formatter->format( round( (float) $this->rating, 1 ) );

        return false === $formatted ? (string) round( (float) $this->rating, 1 ) : $formatted;
    }

    /**
     * The whole stars to fill.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function stars(): int
    {
        return (int) round( (float) $this->rating );
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
        return view( 'ecommerce-storefront::components.rating-summary' );
    }
}
