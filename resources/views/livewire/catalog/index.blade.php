{{--
    The product listing (#130). See Catalog\Index.

    From 1024px, the filters sit in a 240px sidebar (a "Filters" heading,
    "Clear all" while filters are on, then the groups between dividers)
    beside the results; below that, a "Filters (2)" button in the toolbar
    opens them in a start-side Drawer. The results: the toolbar (the live
    results count, then "Sort by" and "Per page"; below 768px the sort's
    label is visually hidden and "Per page" is left out), the active filter
    chips with "Clear all", then the product cards (two to a row on small
    screens, three from 640px, and four from 1024px without filters) and the
    pagination. While a filter, sort or page loads, skeleton cards stand in
    for the cards and the results are aria-busy. With no products: the
    no-match card (with "Clear filters") or the empty catalog.

    The search page (Search\Index) reuses it, passing `$resultsLabel` (the
    live results count) and `$emptyState` (icon, title, description, and
    tips) in place of the catalog's own.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceHasFilters  = [] !== $groups;
    $ecommerceActiveCount = count( $activeFilters );
    $ecommerceLoadTargets = 'q, sort, perPage, gotoPage, nextPage, previousPage, setPage, categoryFilter, tagFilter, attributeFilters, priceMin, priceMax, inStock, onSale, minRating, extraFilters, removeFilter, clearFilters, $refresh';
@endphp
<section class="ec-catalog" data-ecommerce-catalog @if ( null !== $heading ) aria-labelledby="ecommerce-catalog-heading" @else aria-label="{{ __( 'Products' ) }}" @endif>
    @if ( null !== $heading )
        <h2 id="ecommerce-catalog-heading" class="ec-catalog__title">{{ $heading }}</h2>
    @endif

    <div class="ec-catalog__layout" @if ( $ecommerceHasFilters ) data-with-filters @endif>
        @if ( $ecommerceHasFilters )
            {{-- From 1024px: the filters in a sidebar. --}}
            <section class="ec-catalog__sidebar" aria-labelledby="ecommerce-catalog-filters-heading" data-filters-sidebar>
                <div class="ec-catalog__sidebar-header">
                    <h2 id="ecommerce-catalog-filters-heading" class="ec-catalog__sidebar-title">{{ __( 'Filters' ) }}</h2>

                    @if ( 0 !== $ecommerceActiveCount )
                        <x-artisanpack-link wire:click="clearFilters" data-sidebar-clear-filters>{{ __( 'Clear all' ) }}</x-artisanpack-link>
                    @endif
                </div>

                @include( 'ecommerce-storefront::livewire.catalog.partials.filters', [ 'idPrefix' => 'ecommerce-filters-sidebar' ] )
            </section>
        @endif

        <div class="ec-catalog__main">
            <div class="ec-catalog__toolbar">
                {{-- Announced politely whenever filtering, sorting, or paging changes the results. --}}
                <p class="ec-catalog__count" role="status" aria-live="polite" aria-atomic="true" data-results-count>
                    {{ $resultsLabel ?? trans_choice( ':count product|:count products', $products->total(), [ 'count' => $products->total() ] ) }}
                </p>

                <div class="ec-catalog__controls">
                    @if ( $ecommerceHasFilters )
                        {{-- Below 1024px: the filters in a drawer. Focus returns to this button when it closes. --}}
                        <div class="ec-catalog__filters-button" x-data x-on:ecommerce-filters-closed.window="$refs.trigger.focus()">
                            <x-artisanpack-button
                                variant="outline"
                                color="neutral"
                                size="sm"
                                x-ref="trigger"
                                leading-icon="sliders-horizontal"
                                aria-haspopup="dialog"
                                aria-controls="ecommerce-catalog-filters-drawer"
                                x-on:click="$dispatch( 'ecommerce-filters-open' )"
                                data-filters-open>{{ 0 === $ecommerceActiveCount ? __( 'Filters' ) : __( 'Filters (:count)', [ 'count' => $ecommerceActiveCount ] ) }}</x-artisanpack-button>
                        </div>
                    @endif

                    @if ( $products->total() > 0 )
                        <x-artisanpack-select
                            placeholder=""
                            id="ecommerce-catalog-sort"
                            :label="__( 'Sort by' )"
                            :options="collect( $sorts )->map( fn ( string $label, string $key ): array => [ 'value' => $key, 'label' => $label ] )->values()->all()"
                            wire:model.live="sort"
                            size="sm"
                            class="ec-catalog__select ec-catalog__sort"
                        />

                        <x-artisanpack-select
                            placeholder=""
                            id="ecommerce-catalog-per-page"
                            :label="__( 'Per page' )"
                            :options="collect( $perPageValues )->map( fn ( int $value ): array => [ 'value' => $value, 'label' => (string) $value ] )->all()"
                            wire:model.live="perPage"
                            size="sm"
                            class="ec-catalog__select ec-catalog__per-page"
                        />
                    @endif
                </div>
            </div>

            @if ( 0 !== $ecommerceActiveCount )
                <div class="ec-catalog__chips" role="group" aria-label="{{ __( 'Active filters' ) }}" data-active-filters>
                    @foreach ( $activeFilters as $filter )
                        <button
                            type="button"
                            class="ec-chip"
                            data-variant="filter"
                            aria-label="{{ __( 'Remove filter: :filter', [ 'filter' => $filter['label'] ] ) }}"
                            wire:click="removeFilter( {{ \Illuminate\Support\Js::from( $filter['group'] ) }}, {{ \Illuminate\Support\Js::from( $filter['value'] ) }} )"
                            wire:key="active-filter-{{ $filter['group'] }}-{{ $filter['value'] }}"
                            data-active-filter="{{ $filter['group'] }}"
                        >
                            <span>{{ $filter['label'] }}</span>
                            <x-artisanpack-icon name="x" class="ec-chip__icon" aria-hidden="true" />
                        </button>
                    @endforeach

                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="clearFilters" data-clear-filters>{{ __( 'Clear all' ) }}</x-artisanpack-button>
                </div>
            @endif

            @if ( $products->isEmpty() )
                {{-- From the filter state, not the chips, so "Clear filters" stays reachable when the panel can't be built. --}}
                @if ( null !== ( $emptyState ?? null ) )
                    <x-artisanpack-ec-empty-state :icon="$emptyState['icon']" :title="$emptyState['title']" :description="$emptyState['description']" data-empty-reason="{{ $emptyState['key'] }}">
                        @if ( [] !== $emptyState['tips'] )
                            <ul role="list" class="ec-catalog__tips" data-search-tips>
                                @foreach ( $emptyState['tips'] as $tip )
                                    <li>{{ $tip }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ( $filtered )
                            <x-artisanpack-button color="primary" wire:click="clearFilters">{{ __( 'Clear filters' ) }}</x-artisanpack-button>
                        @endif
                    </x-artisanpack-ec-empty-state>
                @elseif ( $filtered )
                    <x-artisanpack-ec-empty-state
                        icon="funnel"
                        :title="__( 'No products match your filters' )"
                        :description="__( 'Try removing a filter or two.' )"
                        card
                        data-empty-reason="no-match"
                    >
                        <x-artisanpack-button color="primary" wire:click="clearFilters" data-empty-clear-filters>{{ __( 'Clear filters' ) }}</x-artisanpack-button>
                    </x-artisanpack-ec-empty-state>
                @else
                    <x-artisanpack-ec-empty-state
                        icon="shopping-bag"
                        :title="__( 'No products to show' )"
                        :description="__( 'There are no products here yet. Check back soon.' )"
                        card
                        data-empty-reason="empty"
                    />
                @endif
            @else
                {{-- While a filter, sort or page loads, the skeleton cards stand in for the cards (aria-busy). --}}
                <div class="ec-catalog__results" wire:loading.delay.attr="aria-busy" wire:target="{{ $ecommerceLoadTargets }}" data-catalog-results>
                    <ul role="list" class="ec-catalog__grid" @if ( ! $ecommerceHasFilters ) data-wide @endif data-catalog-grid>
                        @foreach ( $products as $product )
                            <li class="ec-catalog__item" wire:key="catalog-product-{{ $product->id }}">
                                <x-artisanpack-ec-sf-product-card :product="$product" :currency="$currency" :heading-level="null !== $heading ? 3 : 2" />
                            </li>
                        @endforeach
                    </ul>

                    <ul role="list" class="ec-catalog__grid ec-catalog__skeleton" @if ( ! $ecommerceHasFilters ) data-wide @endif aria-hidden="true" data-skeleton="card" data-catalog-skeleton>
                        @for ( $i = 0; $i < min( 6, $products->count() ); $i++ )
                            <li class="ec-catalog__item">
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
                </div>

                <div class="ec-catalog__pagination">
                    <x-artisanpack-pagination
                        :rows="$products"
                        summary
                        :summary-label="__( 'Showing {from} to {to} of {total} results' )"
                    />
                </div>
            @endif
        </div>
    </div>

    @if ( $ecommerceHasFilters )
        {{--
            The design system's Drawer on the start side, below 1024px:
            opened by the Filters button (`ecommerce-filters-open`), closed by
            its close button, Escape, the backdrop or "Show N products"
            (`ecommerce-filters-close`). `ecommerce-filters-closed` sends
            focus back to the Filters button. Its description says how many
            filters are on.
        --}}
        <x-artisanpack-drawer
            side="start"
            id="ecommerce-catalog-filters-drawer"
            size="sm"
            :title="__( 'Filters' )"
            :description="0 === $ecommerceActiveCount ? null : trans_choice( ':count applied|:count applied', $ecommerceActiveCount, [ 'count' => $ecommerceActiveCount ] )"
            :close-label="__( 'Close filters' )"
            x-on:ecommerce-filters-open.window="open = true"
            x-on:ecommerce-filters-close.window="open = false"
            x-init="$watch( 'open', ( isOpen ) => { if ( ! isOpen ) { $dispatch( 'ecommerce-filters-closed' ) } } )"
            data-filters-drawer
        >
            @include( 'ecommerce-storefront::livewire.catalog.partials.filters', [ 'idPrefix' => 'ecommerce-filters-drawer' ] )

            <x-slot:footer>
                @if ( 0 !== $ecommerceActiveCount )
                    <x-artisanpack-button variant="ghost" color="neutral" wire:click="clearFilters">{{ __( 'Clear all' ) }}</x-artisanpack-button>
                @endif

                <x-artisanpack-button
                    color="primary"
                    x-on:click="$dispatch( 'ecommerce-filters-close' )"
                    data-filters-apply>{{ trans_choice( 'Show :count product|Show :count products', $products->total(), [ 'count' => $products->total() ] ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-drawer>
    @endif
</section>
