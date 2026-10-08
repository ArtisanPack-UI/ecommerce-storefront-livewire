{{--
    Related products, upsells, or cross-sells. See Product\RelatedProducts.

    Below `md` the list scrolls sideways (with scroll snapping) and the
    "Previous" / "Next" buttons scroll it by a screenful; from `md` up it is
    a grid and the buttons are hidden.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-related-products="{{ $type }}">
    @if ( $products->isNotEmpty() )
        <section
            class="flex flex-col gap-4"
            aria-labelledby="{{ $headingId }}"
            x-data="{
                scrollBy( direction ) {
                    const list = this.$refs.list;
                    list.scrollBy( { left: direction * list.clientWidth * 0.8, behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
                },
            }"
        >
            <div class="flex items-center justify-between gap-4">
                <h2 id="{{ $headingId }}" class="text-2xl font-bold">{{ $title }}</h2>

                @if ( $products->count() > 1 )
                    <div class="flex gap-2 md:hidden">
                        <x-artisanpack-button
                            icon="o-chevron-left"
                            class="btn-circle btn-sm"
                            :aria-label="__( 'Previous products' )"
                            aria-controls="{{ $headingId }}-list"
                            x-on:click="scrollBy( -1 )"
                            data-related-previous
                        />
                        <x-artisanpack-button
                            icon="o-chevron-right"
                            class="btn-circle btn-sm"
                            :aria-label="__( 'Next products' )"
                            aria-controls="{{ $headingId }}-list"
                            x-on:click="scrollBy( 1 )"
                            data-related-next
                        />
                    </div>
                @endif
            </div>

            <ul
                id="{{ $headingId }}-list"
                x-ref="list"
                class="-mx-1 flex snap-x snap-mandatory gap-4 overflow-x-auto px-1 pb-2 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 md:pb-0 lg:grid-cols-4"
                aria-labelledby="{{ $headingId }}"
                data-related-list
            >
                @foreach ( $products as $related )
                    <li class="w-3/4 shrink-0 snap-start sm:w-5/12 md:w-auto" wire:key="related-{{ $type }}-{{ $related->id }}">
                        <x-artisanpack-ec-sf-product-card :product="$related" :currency="$currency" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
