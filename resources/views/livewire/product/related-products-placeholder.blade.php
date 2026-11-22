{{--
    Product suggestions while they load (#90). See Product\RelatedProducts.

    The section's heading, then `$count` (1–4) skeleton cards the size of a
    product card in the same grid: a square image, three text lines and a
    pill for the button. The list is aria-busy, the cards are aria-hidden,
    and one status line names what is loading. The design-system Skeleton's
    shimmer runs in the reading direction and stops under reduced motion.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceCount = max( 1, min( 4, (int) ( $count ?? 4 ) ) ) )
{{-- The root is a <div>, like the component's, so Livewire can swap it in. --}}
<div data-related-placeholder>
    <section class="ec-suggestions">
        <h2 class="ec-suggestions__title">{{ $title }}</h2>

        <span class="apui-sr-only" role="status">{{ __( 'Loading suggestions…' ) }}</span>

        <ul class="ec-suggestions__grid" data-columns="{{ $ecommerceCount }}" aria-busy="true" data-skeleton="card">
            @for ( $i = 0; $i < $ecommerceCount; $i++ )
                <li class="ec-suggestions__item" aria-hidden="true">
                    <x-artisanpack-card class="ec-skeleton-card">
                        <x-slot:media>
                            <x-artisanpack-skeleton class="ec-skeleton-card__media" />
                        </x-slot:media>

                        <x-artisanpack-skeleton shape="text" class="ec-skeleton-card__line" data-length="medium" />
                        <x-artisanpack-skeleton shape="text" class="ec-skeleton-card__line" data-length="long" />
                        <x-artisanpack-skeleton shape="text" class="ec-skeleton-card__line" data-length="short" />
                        <x-artisanpack-skeleton class="ec-skeleton-card__button" />
                    </x-artisanpack-card>
                </li>
            @endfor
        </ul>
    </section>
</div>
