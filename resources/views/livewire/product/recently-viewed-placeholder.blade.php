{{--
    Recently viewed while it loads (#107, #110). See Product\RecentlyViewed.

    The section's heading, then `$count` (1–12) skeleton rows the size of a
    recently-viewed row in the same grid: a Card with a 64px block for the
    thumbnail and two text lines for the name and price, so nothing jumps
    when the rows arrive. The list is aria-busy and hidden from assistive
    tech; one status line names what is loading.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      2.0.0
--}}
@php
    $ecommerceCount   = max( 1, min( 12, (int) ( $count ?? 4 ) ) );
    $ecommerceColumns = max( 1, min( 6, (int) ( $columns ?? 4 ) ) );
@endphp
{{-- The root is a <div>, like the component's, so Livewire can swap it in. --}}
<div data-recently-viewed-placeholder>
    <section class="ec-recent">
        <h2 class="ec-recent__title">{{ $title }}</h2>

        <span class="apui-sr-only" role="status">{{ __( 'Loading recently viewed products…' ) }}</span>

        <ul class="ec-recent__list" data-columns="{{ $ecommerceColumns }}" aria-busy="true" aria-hidden="true" data-skeleton="recent">
            @for ( $i = 0; $i < $ecommerceCount; $i++ )
                <li class="ec-recent__item">
                    <div class="apui-card ec-recent__card" data-surface="default">
                        <x-artisanpack-skeleton class="ec-recent__skeleton-thumb" />

                        <div class="ec-recent__body ec-recent__body--skeleton">
                            <x-artisanpack-skeleton shape="text" class="ec-recent__skeleton-line" data-length="long" />
                            <x-artisanpack-skeleton shape="text" class="ec-recent__skeleton-line" data-length="short" />
                        </div>
                    </div>
                </li>
            @endfor
        </ul>
    </section>
</div>
