{{--
    Product reviews. See Product\Reviews.

    On the product page (#105): "Customer reviews" with the outline "Write a
    review" button at the end of the heading row. Below, two columns from
    1024px (stacked under it): a 320px summary Card (the big average, the
    read-only Rating, the count, and a 5→1 breakdown whose rows filter the
    list) and the list. Each review is its stars and title on one line, the
    body (at most 68ch), then the name, a "Verified purchase" success badge
    and the date. The form opens in a Card above the list.

    States: no reviews yet and no reviews with the chosen rating are
    design-system EmptyStates; a submitted review is a success Alert
    (awaiting moderation, or published).

    Each breakdown row is a button (it filters in place) whose name says
    the whole row: "5 stars: 98 reviews (78%)"; it's pressed while it
    filters. The bars are decorative.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use Illuminate\Support\Number;

    $ecommerceFormId    = 'ec-review-form-' . $product->id;
    $ecommerceHeadingId = 'ec-reviews-' . $product->id . '-heading';
    $ecommerceCanWrite  = $allowForm && ! $submitted && $eligibility->allowed;
@endphp
<section id="reviews" class="ec-reviews" aria-labelledby="{{ $ecommerceHeadingId }}" data-product-reviews="{{ $product->id }}">
    <div class="ec-reviews__header">
        <h2 id="{{ $ecommerceHeadingId }}" class="ec-reviews__title">{{ __( 'Customer reviews' ) }}</h2>

        @if ( $ecommerceCanWrite )
            {{-- Stays while the form is open, so it can report (and toggle) that state and take focus back on Cancel. --}}
            <x-artisanpack-button
                variant="outline"
                color="neutral"
                leading-icon="pencil"
                x-on:click="$wire.showForm ? $wire.closeForm() : $wire.openForm()"
                aria-controls="{{ $ecommerceFormId }}"
                :aria-expanded="$showForm ? 'true' : 'false'"
                data-review-cta
                data-review-open>{{ __( 'Write a review' ) }}</x-artisanpack-button>
        @endif
    </div>

    @if ( $allowForm && $submitted )
        {{-- Takes focus from the form it replaces. --}}
        <x-artisanpack-alert
            color="success"
            icon="circle-check"
            :title="$published ? __( 'Thanks — your review is published' ) : __( 'Thanks — your review is awaiting moderation' )"
            role="status"
            tabindex="-1"
            x-data
            x-init="$el.focus()"
            data-review-cta
            data-review-submitted
        />
    @elseif ( $allowForm && ! $eligibility->allowed )
        <p class="ec-reviews__ineligible" data-review-cta data-review-ineligible="{{ $eligibility->reason }}">
            @if ( null !== $loginUrl && in_array( $eligibility->reason, [ \ArtisanPackUI\Ecommerce\Reviews\ReviewEligibility::GUESTS_NOT_ALLOWED, \ArtisanPackUI\Ecommerce\Reviews\ReviewEligibility::PURCHASE_REQUIRED ], true ) )
                <x-artisanpack-link :href="$loginUrl" :navigate="false">{{ $reasonMessage }}</x-artisanpack-link>
            @else
                {{ $reasonMessage }}
            @endif
        </p>
    @endif

    <div @class( [ 'ec-reviews__layout', 'ec-reviews__layout--single' => 0 === $total ] )>
        @if ( $total > 0 )
            <x-artisanpack-card class="ec-reviews__summary" data-review-summary>
                <div class="ec-reviews__overall">
                    <span class="ec-reviews__average" aria-hidden="true">{{ Number::format( $average, precision: 1, locale: app()->getLocale() ) }}</span>
                    <span class="ec-reviews__overall-detail">
                        <x-artisanpack-rating :value="$average" readonly size="md" :value-label="__( ':rating out of 5', [ 'rating' => Number::format( $average, maxPrecision: 1, locale: app()->getLocale() ) ] )" />
                        <span class="ec-reviews__count">{{ trans_choice( ':count review|:count reviews', $total, [ 'count' => $total ] ) }}</span>
                    </span>
                </div>

                <ul role="list" class="ec-reviews__breakdown" aria-label="{{ __( 'Rating breakdown' ) }}" data-review-histogram>
                    @foreach ( $histogram as $stars => $count )
                        @php( $ecommercePercent = (int) round( $count / $total * 100 ) )
                        <li>
                            <button
                                type="button"
                                class="ec-reviews__bar"
                                wire:click="filterRating( {{ $stars }} )"
                                aria-pressed="{{ $ratingFilter === $stars ? 'true' : 'false' }}"
                                @disabled( 0 === $count && $ratingFilter !== $stars )
                                data-histogram-row="{{ $stars }}"
                            >
                                <span class="ec-reviews__bar-stars" aria-hidden="true">{{ Number::format( $stars, locale: app()->getLocale() ) }}<x-artisanpack-icon name="star" /></span>
                                <x-artisanpack-progress :value="$ecommercePercent" size="sm" color="accent" class="ec-reviews__bar-track" aria-hidden="true" />
                                <span class="ec-reviews__bar-count" aria-hidden="true">{{ Number::format( $count, locale: app()->getLocale() ) }}</span>
                                <span class="apui-sr-only">
                                    {{ __( ':stars: :reviews (:percent%)', [
                                        'stars'   => trans_choice( ':count star|:count stars', $stars, [ 'count' => $stars ] ),
                                        'reviews' => trans_choice( ':count review|:count reviews', $count, [ 'count' => $count ] ),
                                        'percent' => $ecommercePercent,
                                    ] ) }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </x-artisanpack-card>
        @endif

        <div class="ec-reviews__main">
            @if ( $showForm )
                <x-artisanpack-card class="ec-review-form-card">
                    <form id="{{ $ecommerceFormId }}" wire:submit="submit" class="ec-review-form" aria-labelledby="{{ $ecommerceFormId }}-heading" novalidate data-review-form>
                        <h3 id="{{ $ecommerceFormId }}-heading" tabindex="-1" class="ec-review-form__title" x-data x-init="$el.focus()">{{ __( 'Write a review' ) }}</h3>

                        @include( 'ecommerce-storefront::partials.error-summary', [ 'summaryId' => $ecommerceFormId, 'summaryExcept' => [ 'review' ] ] )

                        @error( 'review' )
                            <x-artisanpack-alert color="error" icon="circle-alert" :title="$message" data-review-error />
                        @enderror

                        <x-artisanpack-ec-rating-input :id="$ecommerceFormId . '-rating'" :label="__( 'Your rating' )" wire:model="rating" :error="$errors->first( 'rating' )" required data-field-key="rating" />

                        <x-artisanpack-ec-field :id="$ecommerceFormId . '-title'" :label="__( 'Title' )" wire:model="title" maxlength="255" error-field="title" />

                        <x-artisanpack-ec-field control="textarea" :id="$ecommerceFormId . '-body'" :label="__( 'Your review' )" wire:model="body" rows="5" maxlength="10000" error-field="body" />

                        @if ( $guest )
                            <div class="ec-review-form__pair">
                                <x-artisanpack-ec-field :id="$ecommerceFormId . '-name'" :label="__( 'Your name' )" wire:model="authorName" autocomplete="name" required error-field="authorName" />
                                <x-artisanpack-ec-field :id="$ecommerceFormId . '-email'" type="email" :label="__( 'Your email' )" :hint="__( 'Not shown with your review.' )" wire:model="authorEmail" autocomplete="email" required error-field="authorEmail" />
                            </div>
                        @endif

                        {{-- Honeypot: hidden from people and assistive technology; bots that fill it in are filed as spam. --}}
                        <div class="apui-sr-only" aria-hidden="true">
                            <label for="{{ $ecommerceFormId }}-{{ $honeypotName }}">{{ __( 'Leave this field empty' ) }}</label>
                            <input id="{{ $ecommerceFormId }}-{{ $honeypotName }}" type="text" name="{{ $honeypotName }}" wire:model="honeypot" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="ec-review-form__actions">
                            <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" wire:target="submit" data-review-submit>{{ __( 'Submit review' ) }}</x-artisanpack-button>
                            <x-artisanpack-button variant="ghost" color="neutral" x-on:click="$wire.closeForm().then( () => $nextTick( () => document.querySelector( '[data-product-reviews=&quot;{{ $product->id }}&quot;] [data-review-open]' )?.focus() ) )" data-review-cancel>{{ __( 'Cancel' ) }}</x-artisanpack-button>
                        </div>
                    </form>
                </x-artisanpack-card>
            @endif

            @if ( null !== $ratingFilter )
                <div class="ec-reviews__filter" data-review-filter="{{ $ratingFilter }}">
                    <span>{{ trans_choice( 'Showing reviews rated :count star|Showing reviews rated :count stars', $ratingFilter, [ 'count' => $ratingFilter ] ) }}</span>
                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="filterRating">{{ __( 'Show all reviews' ) }}</x-artisanpack-button>
                </div>
            @endif

            <p class="apui-sr-only" role="status" aria-live="polite">
                @if ( null !== $ratingFilter )
                    {{ trans_choice( ':count review shown|:count reviews shown', $reviews->total(), [ 'count' => $reviews->total() ] ) }}
                @endif
            </p>

            @if ( $reviews->isNotEmpty() )
                <ol role="list" class="ec-reviews__list" data-review-list>
                    @foreach ( $reviews as $review )
                        <li class="ec-review" wire:key="review-{{ $review->id }}" data-review="{{ $review->id }}">
                            <article class="ec-review__article" aria-labelledby="ec-review-{{ $review->id }}-author">
                                <div class="ec-review__heading">
                                    <x-artisanpack-rating :value="(int) $review->rating" readonly size="sm" :value-label="__( 'Rated :rating out of 5', [ 'rating' => (int) $review->rating ] )" />

                                    @if ( null !== $review->title && '' !== $review->title )
                                        <h3 class="ec-review__title">{{ $review->title }}</h3>
                                    @endif
                                </div>

                                @if ( null !== $review->body && '' !== $review->body )
                                    <p class="ec-review__body">{{ $review->body }}</p>
                                @endif

                                <p class="ec-review__meta">
                                    <span id="ec-review-{{ $review->id }}-author" class="ec-review__author">{{ $review->author_name }}</span>
                                    @if ( $review->is_verified_purchase )
                                        <x-artisanpack-badge variant="soft" color="success" leading-icon="check" data-review-verified>{{ __( 'Verified purchase' ) }}</x-artisanpack-badge>
                                    @endif
                                    <time datetime="{{ ( $review->approved_at ?? $review->created_at )?->toIso8601String() }}">{{ LocalizedDate::format( $review->approved_at ?? $review->created_at ) }}</time>
                                </p>
                            </article>
                        </li>
                    @endforeach
                </ol>

                <x-artisanpack-pagination :rows="$reviews" summary :summary-label="__( 'Showing {from} to {to} of {total} reviews' )" />
            @elseif ( null !== $ratingFilter )
                <x-artisanpack-ec-empty-state icon="search-x" :title="__( 'No reviews with this rating' )" :heading-level="3" size="sm" data-reviews-filtered-empty>
                    <x-artisanpack-button variant="outline" color="neutral" wire:click="filterRating">{{ __( 'Show all reviews' ) }}</x-artisanpack-button>
                </x-artisanpack-ec-empty-state>
            @elseif ( ! $showForm )
                <x-artisanpack-ec-empty-state icon="message-square" :title="__( 'No reviews yet' )" :description="__( 'Be the first to review this product.' )" :heading-level="3" data-reviews-empty />
            @endif
        </div>
    </div>
</section>
