<?php

/**
 * Product reviews component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Product;

use ArtisanPackUI\Ecommerce\Exceptions\ReviewNotAllowedException;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductReview;
use ArtisanPackUI\Ecommerce\Reviews\ProductRatingAggregator;
use ArtisanPackUI\Ecommerce\Reviews\ReviewEligibility;
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\Ecommerce\Services\ReviewService;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\RateLimitsStorefront;
use ArtisanPackUI\EcommerceStorefrontLivewire\Livewire\Concerns\SendsToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * `<livewire:artisanpack-ecommerce-storefront-product-reviews :product="$product" />`
 *
 * A product's reviews (spec §7.2, S12), rendered on the product page under
 * `#reviews`:
 *
 * - **Histogram** — the average and, for 5★ to 1★, the number of approved
 *   reviews with a bar. Each row is a button that filters the list to that
 *   rating (`?review-rating=1`); the bars are decorative and each row says
 *   its count and share in text.
 * - **Reviews** — approved reviews, newest first, paginated
 *   (`reviews.per_page`, `?reviews-page=`), with the author, date, rating,
 *   title, text, and a "Verified purchase" badge.
 * - **Write a review** — offered when the engine's
 *   `ReviewService::eligibility()` allows it; otherwise the reason ("Sign in
 *   to review this product.", "You've already reviewed this product."). The
 *   form takes a rating, title, and text, plus a name and email from guests.
 *   Title and text pass through `sanitizeText()` (artisanpack-ui/security)
 *   first; submissions count against `ecommerce.review.submit`; a filled-in
 *   honeypot field files the review as spam without telling the sender.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceStorefrontLivewire
 *
 * @since      1.0.0
 */
class Reviews extends Component
{
    use RateLimitsStorefront;
    use SendsToasts;
    use WithPagination;

    /**
     * The pagination query-string key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PAGE_NAME = 'reviews-page';

    /**
     * The product reviewed.
     *
     * @since 1.0.0
     *
     * @var Product
     */
    #[Locked]
    public Product $product;

    /**
     * Reviews per page; null uses `reviews.per_page` (1–50).
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $reviewsPerPage = null;

    /**
     * Offer the "Write a review" form. The Reviews block can turn it off.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $allowForm = true;

    /**
     * The star rating the list is filtered to, or null for all.
     *
     * Untyped because it comes straight from `?review-rating=`, which can
     * hold anything (`abc`, `[]`); {@see self::normalizedRating()} turns it
     * into 1–5 or null before it is used.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'review-rating', except: null )]
    public mixed $ratingFilter = null;

    /**
     * Whether the review form is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $showForm = false;

    /**
     * The rating given, 1–5.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $rating = null;

    /**
     * The review title.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $title = '';

    /**
     * The review text.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $body = '';

    /**
     * A guest reviewer's name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $authorName = '';

    /**
     * A guest reviewer's email.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $authorEmail = '';

    /**
     * The honeypot field real shoppers never see or fill in.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $honeypot = '';

    /**
     * Whether the shopper just submitted a review.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $submitted = false;

    /**
     * Whether the submitted review was published straight away (an
     * automatic moderator approved it).
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $published = false;

    /**
     * Filters the list to one star rating, or back to all when that rating
     * is already chosen (or `$stars` is null).
     *
     * @since 1.0.0
     *
     * @param  mixed  $stars  1–5, or null for all (anything else counts as null).
     *
     * @return void
     */
    public function filterRating( mixed $stars = null ): void
    {
        $stars              = self::normalizedRating( $stars );
        $this->ratingFilter = $stars === self::normalizedRating( $this->ratingFilter ) ? null : $stars;

        $this->resetPage( self::PAGE_NAME );
    }

    /**
     * Opens the review form when the shopper may review the product.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function openForm(): void
    {
        $this->submitted = false;
        $this->showForm  = $this->allowForm && $this->eligibility()->allowed;
    }

    /**
     * Closes the review form.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    /**
     * Validates the review and submits it through the engine.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function submit(): void
    {
        $guest = null === auth()->user();

        if ( ! $this->allowForm || ! $this->eligibility()->allowed ) {
            $this->showForm = false;

            return;
        }

        $this->validate( $this->reviewRules( $guest ), $this->reviewMessages() );

        $customer = $guest ? null : app( CustomerService::class )->customerForUser( auth()->user(), true );
        $isSpam   = '' !== trim( $this->honeypot );

        try {
            $review = $this->rateLimited(
                'ecommerce.review.submit',
                fn (): ?ProductReview => app( ReviewService::class )->submit( $this->product, $this->reviewAttributes( $guest ), $customer, $isSpam ),
            );
        } catch ( ReviewNotAllowedException $exception ) {
            $this->addError( 'review', $this->reasonMessage( $exception->reason ) );

            return;
        }

        if ( $this->wasThrottled() ) {
            return;
        }

        if ( ! $review instanceof ProductReview ) {
            $this->addError( 'review', __( 'Your review could not be submitted. Please try again later.' ) );

            return;
        }

        $this->published = ! $isSpam && $review->isApproved();
        $this->submitted = true;
        $this->showForm  = false;

        $this->reset( 'rating', 'title', 'body', 'authorName', 'authorEmail', 'honeypot' );
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
        $this->ratingFilter = self::normalizedRating( $this->ratingFilter );

        $histogram   = app( ProductRatingAggregator::class )->histogram( $this->product );
        $total       = array_sum( $histogram );
        $eligibility = $this->eligibility();

        return view( 'ecommerce-storefront::livewire.product.reviews', [
            'histogram'     => $histogram,
            'total'         => $total,
            'average'       => $this->average( $histogram, $total ),
            'reviews'       => $this->reviews(),
            'eligibility'   => $eligibility,
            'reasonMessage' => $eligibility->allowed ? null : $this->reasonMessage( (string) $eligibility->reason ),
            'loginUrl'      => null === auth()->user() ? $this->loginUrl() : null,
            'guest'         => null === auth()->user(),
            'honeypotName'  => ReviewService::honeypotField(),
        ] );
    }

    /**
     * A star rating from 1 to 5, or null for anything else.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  A rating from the query string or an action.
     *
     * @return int|null
     */
    protected static function normalizedRating( mixed $value ): ?int
    {
        $rating = is_int( $value ) || is_string( $value ) ? filter_var( $value, FILTER_VALIDATE_INT ) : false;

        return is_int( $rating ) && $rating >= 1 && $rating <= 5 ? $rating : null;
    }

    /**
     * Whether the shopper may review the product.
     *
     * A signed-in shopper with no customer record yet (one is created on
     * submit) has no purchases and no reviews, so only `require_purchase`
     * can stop them.
     *
     * @since 1.0.0
     *
     * @return ReviewEligibility
     */
    protected function eligibility(): ReviewEligibility
    {
        $user     = auth()->user();
        $customer = Customer::forUser( $user );

        if ( null !== $user && null === $customer ) {
            return (bool) config( 'artisanpack.ecommerce.reviews.require_purchase', false )
                ? new ReviewEligibility( false, ReviewEligibility::PURCHASE_REQUIRED )
                : new ReviewEligibility( true );
        }

        return app( ReviewService::class )->eligibility( $this->product, $customer );
    }

    /**
     * The approved reviews on this page of the list.
     *
     * @since 1.0.0
     *
     * @return LengthAwarePaginator<int, ProductReview>
     */
    protected function reviews(): LengthAwarePaginator
    {
        return ProductReview::query()
            ->where( 'product_id', $this->product->id )
            ->approved()
            ->when( null !== $this->ratingFilter, fn ( $query ) => $query->where( 'rating', $this->ratingFilter ) )
            ->orderByDesc( 'approved_at' )
            ->orderByDesc( 'id' )
            ->paginate( $this->perPage(), [ '*' ], self::PAGE_NAME );
    }

    /**
     * Reviews per page (the prop, else `reviews.per_page`; 1–50).
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected function perPage(): int
    {
        return max( 1, min( 50, $this->reviewsPerPage ?? (int) config( 'artisanpack.ecommerce-storefront-livewire.reviews.per_page', 5 ) ) );
    }

    /**
     * The average of the histogram, 0 when there are no reviews.
     *
     * @since 1.0.0
     *
     * @param  array<int, int>  $histogram  Reviews per star rating.
     * @param  int              $total      All reviews.
     *
     * @return float
     */
    protected function average( array $histogram, int $total ): float
    {
        if ( 0 === $total ) {
            return 0.0;
        }

        $sum = 0;

        foreach ( $histogram as $stars => $count ) {
            $sum += $stars * $count;
        }

        return round( $sum / $total, 1 );
    }

    /**
     * The form's validation rules. Guests give a name and email.
     *
     * @since 1.0.0
     *
     * @param  bool  $guest  Whether the reviewer is signed out.
     *
     * @return array<string, array<int, string>>
     */
    protected function reviewRules( bool $guest ): array
    {
        $rules = [
            'rating' => [ 'required', 'integer', 'min:1', 'max:5' ],
            'title'  => [ 'nullable', 'string', 'max:255' ],
            'body'   => [ 'nullable', 'string', 'max:10000' ],
        ];

        if ( $guest ) {
            $rules['authorName']  = [ 'required', 'string', 'max:255' ];
            $rules['authorEmail'] = [ 'required', 'string', 'email', 'max:255' ];
        }

        return $rules;
    }

    /**
     * The form's validation messages.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function reviewMessages(): array
    {
        return [
            'rating.required'      => __( 'Choose a rating.' ),
            'rating.integer'       => __( 'Choose a rating from 1 to 5 stars.' ),
            'rating.min'           => __( 'Choose a rating from 1 to 5 stars.' ),
            'rating.max'           => __( 'Choose a rating from 1 to 5 stars.' ),
            'title.max'            => __( 'Keep the title under :max characters.', [ 'max' => 255 ] ),
            'body.max'             => __( 'Keep your review under :max characters.', [ 'max' => 10000 ] ),
            'authorName.required'  => __( 'Enter your name.' ),
            'authorName.max'       => __( 'Keep your name under :max characters.', [ 'max' => 255 ] ),
            'authorEmail.required' => __( 'Enter your email address.' ),
            'authorEmail.email'    => __( 'Enter a valid email address.' ),
            'authorEmail.max'      => __( 'Enter a valid email address.' ),
        ];
    }

    /**
     * The review as the engine takes it, with the text sanitized.
     *
     * @since 1.0.0
     *
     * @param  bool  $guest  Whether the reviewer is signed out.
     *
     * @return array<string, mixed>
     */
    protected function reviewAttributes( bool $guest ): array
    {
        $title = trim( sanitizeText( $this->title ) );
        $body  = trim( sanitizeText( $this->body ) );

        $attributes = [
            'rating' => (int) $this->rating,
            'title'  => '' === $title ? null : $title,
            'body'   => '' === $body ? null : $body,
        ];

        if ( $guest ) {
            $attributes['author_name']  = trim( sanitizeText( $this->authorName ) );
            $attributes['author_email'] = mb_strtolower( trim( $this->authorEmail ) );
        }

        return $attributes;
    }

    /**
     * Why the shopper can't review the product, in words.
     *
     * @since 1.0.0
     *
     * @param  string  $reason  A {@see ReviewEligibility} reason.
     *
     * @return string
     */
    protected function reasonMessage( string $reason ): string
    {
        return match ( $reason ) {
            ReviewEligibility::GUESTS_NOT_ALLOWED => __( 'Sign in to review this product.' ),
            ReviewEligibility::ALREADY_REVIEWED   => __( 'You\'ve already reviewed this product.' ),
            ReviewEligibility::PURCHASE_REQUIRED  => __( 'Only customers who bought this product can review it.' ),
            default                               => __( 'You can\'t review this product.' ),
        };
    }

    /**
     * The host's sign-in page (`auth.login_route`), if it has one.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    protected function loginUrl(): ?string
    {
        $route = (string) config( 'artisanpack.ecommerce-storefront-livewire.auth.login_route', 'login' );

        return '' !== $route && Route::has( $route ) ? route( $route ) : null;
    }
}
