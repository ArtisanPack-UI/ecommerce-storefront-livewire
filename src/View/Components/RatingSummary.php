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
 * A star rating with the average and the review count, linked to the
 * reviews when `href` is given. The stars (the design system's read-only
 * Rating, to the nearest half) and the visible numbers are decorative;
 * screen readers hear "Rated 4.5 out of 5, 31 reviews" as one phrase (the
 * link's name, or hidden text). `compact` draws one star and "4.8 (126)"
 * for product cards. With no reviews it says so (or renders nothing when
 * `hide-empty` is set).
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
     * @param  bool            $compact    One star and "4.8 (126)", for product cards.
     */
    public function __construct(
        public float|int|null $rating = 0,
        public ?int $count = 0,
        public ?string $href = null,
        public bool $hideEmpty = false,
        public bool $compact = false,
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
     * The rating to the nearest half star, for the drawn stars.
     *
     * @since 2.0.0
     *
     * @return float
     */
    public function starValue(): float
    {
        return round( (float) $this->rating * 2 ) / 2;
    }

    /**
     * The whole summary as one phrase: "Rated 4.5 out of 5, 31 reviews".
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function spokenLabel(): string
    {
        return __( 'Rated :rating out of 5, :reviews', [
            'rating'  => $this->formattedRating(),
            'reviews' => $this->countText(),
        ] );
    }

    /**
     * The review count in words: "31 reviews".
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function countText(): string
    {
        return trans_choice( ':count review|:count reviews', (int) $this->count, [ 'count' => $this->count ] );
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
