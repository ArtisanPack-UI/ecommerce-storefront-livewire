{{--
    The product grid. See Catalog\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<section class="flex flex-col gap-6" data-ecommerce-catalog @if ( null !== $heading ) aria-labelledby="ecommerce-catalog-heading" @else aria-label="{{ __( 'Products' ) }}" @endif>
    @if ( null !== $heading )
        <h2 id="ecommerce-catalog-heading" class="text-2xl font-bold">{{ $heading }}</h2>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        {{-- Announced politely whenever sorting or paging changes the results. --}}
        <p class="text-sm text-base-content/70" role="status" aria-live="polite" aria-atomic="true" data-results-count>
            {{ trans_choice( ':count product|:count products', $products->total(), [ 'count' => $products->total() ] ) }}
        </p>

        @if ( $products->total() > 0 )
            <div class="flex flex-wrap items-end gap-3">
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
            </div>
        @endif
    </div>

    @if ( $products->isEmpty() )
        <x-artisanpack-ec-empty-state
            icon="o-shopping-bag"
            :title="__( 'No products to show' )"
            :description="__( 'There are no products here yet. Check back soon.' )"
        />
    @else
        <ul role="list" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4" wire:loading.class="opacity-60" wire:target="sort, perPage, gotoPage, nextPage, previousPage, setPage">
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
</section>
