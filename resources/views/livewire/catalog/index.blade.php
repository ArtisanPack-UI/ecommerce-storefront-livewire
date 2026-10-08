{{--
    The product grid. See Catalog\Index.

    The search page (Search\Index) reuses it, passing `$resultsLabel` (the
    live results count) and `$emptyState` (icon, title, description, and
    tips) in place of the catalog's own.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceHasFilters = [] !== $groups )
<section class="flex flex-col gap-6" data-ecommerce-catalog @if ( null !== $heading ) aria-labelledby="ecommerce-catalog-heading" @else aria-label="{{ __( 'Products' ) }}" @endif>
    @if ( null !== $heading )
        <h2 id="ecommerce-catalog-heading" class="text-2xl font-bold">{{ $heading }}</h2>
    @endif

    <div @class( [ 'flex flex-col gap-6', 'lg:grid lg:grid-cols-[16rem_1fr] lg:items-start lg:gap-8' => $ecommerceHasFilters ] )>
        @if ( $ecommerceHasFilters )
            {{-- Desktop: the filters in a sidebar. --}}
            <aside class="hidden lg:block" aria-labelledby="ecommerce-catalog-filters-heading" data-filters-sidebar>
                <h2 id="ecommerce-catalog-filters-heading" class="mb-4 text-lg font-semibold">{{ __( 'Filters' ) }}</h2>

                @include( 'ecommerce-storefront::livewire.catalog.partials.filters', [ 'idPrefix' => 'ecommerce-filters-sidebar' ] )
            </aside>
        @endif

        <div class="flex min-w-0 flex-col gap-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                {{-- Announced politely whenever filtering, sorting, or paging changes the results. --}}
                <p class="text-sm text-base-content/70" role="status" aria-live="polite" aria-atomic="true" data-results-count>
                    {{ $resultsLabel ?? trans_choice( ':count product|:count products', $products->total(), [ 'count' => $products->total() ] ) }}
                </p>

                <div class="flex flex-wrap items-end gap-3">
                    @if ( $ecommerceHasFilters )
                        {{-- Mobile: the filters in a drawer. Focus returns to this button when it closes. --}}
                        <div class="lg:hidden" x-data x-on:ecommerce-filters-closed.window="$refs.trigger.focus()">
                            <x-artisanpack-button
                                x-ref="trigger"
                                :label="[] === $activeFilters ? __( 'Filters' ) : __( 'Filters (:count)', [ 'count' => count( $activeFilters ) ] )"
                                icon="o-adjustments-horizontal"
                                class="btn-sm"
                                aria-haspopup="dialog"
                                aria-controls="ecommerce-catalog-filters-drawer"
                                x-on:click="$dispatch( 'ecommerce-filters-open' )"
                                data-filters-open
                            />
                        </div>
                    @endif

                    @if ( $products->total() > 0 )
                        <x-artisanpack-select
                            id="ecommerce-catalog-sort"
                            :label="__( 'Sort by' )"
                            :options="collect( $sorts )->map( fn ( string $label, string $key ): array => [ 'id' => $key, 'name' => $label ] )->values()->all()"
                            wire:model.live="sort"
                            class="select-sm"
                        />

                        <x-artisanpack-select
                            id="ecommerce-catalog-per-page"
                            :label="__( 'Per page' )"
                            :options="collect( $perPageValues )->map( fn ( int $value ): array => [ 'id' => $value, 'name' => (string) $value ] )->all()"
                            wire:model.live="perPage"
                            class="select-sm"
                        />
                    @endif
                </div>
            </div>

            @if ( [] !== $activeFilters )
                <div class="flex flex-wrap items-center gap-2" aria-label="{{ __( 'Active filters' ) }}" role="group" data-active-filters>
                    @foreach ( $activeFilters as $filter )
                        <x-artisanpack-button
                            :label="$filter['label']"
                            icon-right="o-x-mark"
                            class="btn-xs btn-outline rounded-full"
                            :aria-label="__( 'Remove filter: :filter', [ 'filter' => $filter['label'] ] )"
                            wire:click="removeFilter( {{ \Illuminate\Support\Js::from( $filter['group'] ) }}, {{ \Illuminate\Support\Js::from( $filter['value'] ) }} )"
                            wire:key="active-filter-{{ $filter['group'] }}-{{ $filter['value'] }}"
                            data-active-filter="{{ $filter['group'] }}"
                        />
                    @endforeach

                    <x-artisanpack-button :label="__( 'Clear all' )" class="btn-xs btn-ghost" wire:click="clearFilters" data-clear-filters />
                </div>
            @endif

            @if ( $products->isEmpty() )
                {{-- From the filter state, not the badges, so "Clear filters" stays reachable when the panel can't be built. --}}
                @if ( null !== ( $emptyState ?? null ) )
                    <x-artisanpack-ec-empty-state :icon="$emptyState['icon']" :title="$emptyState['title']" :description="$emptyState['description']" data-empty-reason="{{ $emptyState['key'] }}">
                        @if ( [] !== $emptyState['tips'] )
                            <ul role="list" class="list-inside list-disc text-start text-sm text-base-content/70" data-search-tips>
                                @foreach ( $emptyState['tips'] as $tip )
                                    <li>{{ $tip }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ( $filtered )
                            <x-artisanpack-button :label="__( 'Clear filters' )" color="primary" wire:click="clearFilters" />
                        @endif
                    </x-artisanpack-ec-empty-state>
                @elseif ( $filtered )
                    <x-artisanpack-ec-empty-state
                        icon="o-funnel"
                        :title="__( 'No products match your filters' )"
                        :description="__( 'Try removing a filter or two.' )"
                    >
                        <x-artisanpack-button :label="__( 'Clear filters' )" color="primary" wire:click="clearFilters" />
                    </x-artisanpack-ec-empty-state>
                @else
                    <x-artisanpack-ec-empty-state
                        icon="o-shopping-bag"
                        :title="__( 'No products to show' )"
                        :description="__( 'There are no products here yet. Check back soon.' )"
                    />
                @endif
            @else
                <ul role="list" @class( [ 'grid grid-cols-2 gap-4 sm:grid-cols-3', 'lg:grid-cols-4' => ! $ecommerceHasFilters, 'xl:grid-cols-4' => $ecommerceHasFilters ] ) wire:loading.class="opacity-60" wire:target="q, sort, perPage, gotoPage, nextPage, previousPage, setPage, categoryFilter, tagFilter, attributeFilters, inStock, onSale, minRating, extraFilters, removeFilter, clearFilters, $refresh">
                    @foreach ( $products as $product )
                        <li wire:key="catalog-product-{{ $product->id }}">
                            <x-artisanpack-ec-sf-product-card :product="$product" :currency="$currency" :heading-level="null !== $heading ? 3 : 2" />
                        </li>
                    @endforeach
                </ul>

                <x-artisanpack-pagination
                    :rows="$products"
                    hide-per-page
                    :page-info-template="__( 'Showing {from} to {to} of {total} results' )"
                />
            @endif
        </div>
    </div>

    @if ( $ecommerceHasFilters )
        <div class="lg:hidden" x-data x-on:close="$dispatch( 'ecommerce-filters-closed' )">
            <x-artisanpack-drawer
                id="ecommerce-catalog-filters-drawer"
                :title="__( 'Filters' )"
                open-on="ecommerce-filters-open"
                close-on="ecommerce-filters-close"
                close-on-escape
                with-close-button
                class="w-80 max-w-full"
                role="dialog"
                aria-modal="true"
                :aria-label="__( 'Filters' )"
                data-filters-drawer
            >
                @include( 'ecommerce-storefront::livewire.catalog.partials.filters', [ 'idPrefix' => 'ecommerce-filters-drawer' ] )

                <x-slot:actions>
                    @if ( [] !== $activeFilters )
                        <x-artisanpack-button :label="__( 'Clear all' )" class="btn-ghost" wire:click="clearFilters" />
                    @endif

                    <x-artisanpack-button
                        :label="trans_choice( 'Show :count result|Show :count results', $products->total(), [ 'count' => $products->total() ] )"
                        color="primary"
                        x-on:click="$dispatch( 'ecommerce-filters-close' )"
                        data-filters-apply
                    />
                </x-slot:actions>
            </x-artisanpack-drawer>
        </div>
    @endif
</section>
