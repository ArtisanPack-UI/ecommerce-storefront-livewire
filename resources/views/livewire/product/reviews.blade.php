{{--
    Product reviews. See Product\Reviews.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;

    $ecommerceFormId = 'ec-review-form-' . $product->id;
@endphp
<section id="reviews" class="scroll-mt-24" aria-labelledby="ec-reviews-{{ $product->id }}-heading" data-product-reviews="{{ $product->id }}">
    <h2 id="ec-reviews-{{ $product->id }}-heading" class="mb-4 text-2xl font-bold">{{ __( 'Customer reviews' ) }}</h2>

    <div class="grid gap-8 lg:grid-cols-3">
        <div class="flex flex-col gap-4" data-review-summary>
            @if ( $total > 0 )
                <x-artisanpack-ec-rating-summary :rating="$average" :count="$total" class="text-base" />
                <p class="text-sm">{{ __( ':rating out of 5', [ 'rating' => \Illuminate\Support\Number::format( $average, maxPrecision: 1, locale: app()->getLocale() ) ] ) }}</p>

                <ul class="flex flex-col gap-1" aria-label="{{ __( 'Rating breakdown' ) }}" data-review-histogram>
                    @foreach ( $histogram as $stars => $count )
                        @php( $ecommercePercent = (int) round( $count / $total * 100 ) )
                        <li>
                            <button
                                type="button"
                                wire:click="filterRating( {{ $stars }} )"
                                @class( [ 'flex w-full items-center gap-3 rounded px-1 py-1 text-start text-sm hover:bg-base-200 focus-visible:outline-2', 'bg-base-200 font-semibold' => $ratingFilter === $stars ] )
                                aria-pressed="{{ $ratingFilter === $stars ? 'true' : 'false' }}"
                                @disabled( 0 === $count && $ratingFilter !== $stars )
                                data-histogram-row="{{ $stars }}"
                            >
                                <span class="w-14 shrink-0" aria-hidden="true">{{ trans_choice( ':count star|:count stars', $stars, [ 'count' => $stars ] ) }}</span>
                                <progress class="progress progress-warning h-2 grow" value="{{ $ecommercePercent }}" max="100" aria-hidden="true"></progress>
                                <span class="w-10 shrink-0 text-end tabular-nums" aria-hidden="true">{{ $count }}</span>
                                <span class="sr-only">
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
            @else
                <p class="text-base-content/70" data-reviews-empty>{{ __( 'No reviews yet. Be the first to review this product.' ) }}</p>
            @endif

            @if ( $allowForm )
                <div class="border-t border-base-content/10 pt-4" data-review-cta>
                    @if ( $submitted )
                        <x-artisanpack-alert
                            icon="o-check-circle"
                            class="alert-success"
                            :title="$published ? __( 'Thanks — your review is published' ) : __( 'Thanks — your review is awaiting moderation' )"
                            role="status"
                            data-review-submitted
                        />
                    @elseif ( $eligibility->allowed )
                        @unless ( $showForm )
                            <x-artisanpack-button
                                :label="__( 'Write a review' )"
                                icon="o-pencil-square"
                                wire:click="openForm"
                                aria-controls="{{ $ecommerceFormId }}"
                                aria-expanded="false"
                                data-review-open
                            />
                        @endunless
                    @else
                        <p class="text-sm" data-review-ineligible="{{ $eligibility->reason }}">
                            @if ( null !== $loginUrl && in_array( $eligibility->reason, [ \ArtisanPackUI\Ecommerce\Reviews\ReviewEligibility::GUESTS_NOT_ALLOWED, \ArtisanPackUI\Ecommerce\Reviews\ReviewEligibility::PURCHASE_REQUIRED ], true ) )
                                <a href="{{ $loginUrl }}" class="link">{{ $reasonMessage }}</a>
                            @else
                                {{ $reasonMessage }}
                            @endif
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-6 lg:col-span-2">
            @if ( $showForm )
                <form id="{{ $ecommerceFormId }}" wire:submit="submit" class="flex flex-col gap-4 rounded-box border border-base-content/10 p-4" aria-labelledby="{{ $ecommerceFormId }}-heading" novalidate data-review-form>
                    <h3 id="{{ $ecommerceFormId }}-heading" class="text-lg font-semibold">{{ __( 'Write a review' ) }}</h3>

                    @error( 'review' )
                        <x-artisanpack-alert icon="o-exclamation-triangle" class="alert-error" :title="$message" role="alert" data-review-error />
                    @enderror

                    <x-artisanpack-ec-rating-input :id="$ecommerceFormId . '-rating'" :label="__( 'Your rating' )" wire:model="rating" :error="$errors->first( 'rating' )" required />

                    <x-artisanpack-input :id="$ecommerceFormId . '-title'" :label="__( 'Title' )" wire:model="title" maxlength="255" error-field="title" />

                    <x-artisanpack-textarea :id="$ecommerceFormId . '-body'" :label="__( 'Your review' )" wire:model="body" rows="5" maxlength="10000" error-field="body" />

                    @if ( $guest )
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-artisanpack-input :id="$ecommerceFormId . '-name'" :label="__( 'Your name' )" wire:model="authorName" autocomplete="name" required error-field="authorName" />
                            <x-artisanpack-input :id="$ecommerceFormId . '-email'" type="email" :label="__( 'Your email' )" :hint="__( 'Not shown with your review.' )" wire:model="authorEmail" autocomplete="email" required error-field="authorEmail" />
                        </div>
                    @endif

                    {{-- Honeypot: hidden from people and assistive technology; bots that fill it in are filed as spam. --}}
                    <div class="sr-only" aria-hidden="true">
                        <label for="{{ $ecommerceFormId }}-{{ $honeypotName }}">{{ __( 'Leave this field empty' ) }}</label>
                        <input id="{{ $ecommerceFormId }}-{{ $honeypotName }}" type="text" name="{{ $honeypotName }}" wire:model="honeypot" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-artisanpack-button type="submit" :label="__( 'Submit review' )" color="primary" spinner="submit" wire:loading.attr="disabled" wire:target="submit" data-review-submit />
                        <x-artisanpack-button :label="__( 'Cancel' )" class="btn-ghost" wire:click="closeForm" />
                    </div>
                </form>
            @endif

            @if ( null !== $ratingFilter )
                <div class="flex flex-wrap items-center gap-2 text-sm" data-review-filter="{{ $ratingFilter }}">
                    <span>{{ __( 'Showing :count-star reviews', [ 'count' => $ratingFilter ] ) }}</span>
                    <x-artisanpack-button :label="__( 'Show all reviews' )" class="btn-ghost btn-xs" wire:click="filterRating" />
                </div>
            @endif

            <p class="sr-only" role="status" aria-live="polite">
                @if ( null !== $ratingFilter )
                    {{ trans_choice( ':count review shown|:count reviews shown', $reviews->total(), [ 'count' => $reviews->total() ] ) }}
                @endif
            </p>

            @if ( $reviews->isNotEmpty() )
                <ol class="flex flex-col divide-y divide-base-content/10" data-review-list>
                    @foreach ( $reviews as $review )
                        <li class="py-4 first:pt-0" wire:key="review-{{ $review->id }}" data-review="{{ $review->id }}">
                            <article class="flex flex-col gap-2" aria-labelledby="ec-review-{{ $review->id }}-author">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span aria-hidden="true" class="inline-flex">
                                        @for ( $i = 1; $i <= 5; $i++ )
                                            <x-artisanpack-icon name="s-star" @class( [ 'h-4 w-4', 'text-warning' => $i <= (int) $review->rating, 'text-base-content/20' => $i > (int) $review->rating ] ) />
                                        @endfor
                                    </span>
                                    <span class="sr-only">{{ __( 'Rated :rating out of 5', [ 'rating' => (int) $review->rating ] ) }}</span>

                                    @if ( null !== $review->title && '' !== $review->title )
                                        <h3 class="font-semibold">{{ $review->title }}</h3>
                                    @endif
                                </div>

                                <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-base-content/70">
                                    <span id="ec-review-{{ $review->id }}-author" class="font-medium text-base-content">{{ $review->author_name }}</span>
                                    <time datetime="{{ ( $review->approved_at ?? $review->created_at )?->toIso8601String() }}">{{ LocalizedDate::format( $review->approved_at ?? $review->created_at ) }}</time>
                                    @if ( $review->is_verified_purchase )
                                        <x-artisanpack-badge :value="__( 'Verified purchase' )" class="badge-success badge-sm" data-review-verified />
                                    @endif
                                </p>

                                @if ( null !== $review->body && '' !== $review->body )
                                    <p class="whitespace-pre-line">{{ $review->body }}</p>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ol>

                <x-artisanpack-pagination :rows="$reviews" hide-per-page :page-info-template="__( 'Showing {from} to {to} of {total} reviews' )" />
            @elseif ( null !== $ratingFilter )
                <p class="text-base-content/70" data-reviews-filtered-empty>{{ __( 'No reviews with this rating.' ) }}</p>
            @endif
        </div>
    </div>
</section>
