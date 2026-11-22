{{--
    Related products, upsells, or cross-sells. See Product\RelatedProducts.

    On the product page (#106): the heading (per type, or the block's
    own), then the product cards. Below 768px the list scrolls sideways
    with scroll snapping, two cards to a screen, and the outline icon-only
    "Previous" / "Next" buttons at the end of the heading row scroll it by
    a screenful (mirrored in right-to-left layouts). From 768px it is a grid
    (`columns` from 1024px, at most 2 between 768px and 1024px) and the
    buttons are hidden.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-related-products="{{ $type }}">
    @if ( $products->isNotEmpty() )
        <section
            class="ec-related"
            aria-labelledby="{{ $headingId }}"
            x-data="{
                scrollBy( direction ) {
                    const list = this.$refs.list;
                    const sign = 'rtl' === getComputedStyle( list ).direction ? -1 : 1;

                    list.scrollBy( { left: sign * direction * list.clientWidth * 0.8, behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
                },
            }"
        >
            <div class="ec-related__header">
                <h2 id="{{ $headingId }}" class="ec-related__title">{{ $title }}</h2>

                @if ( $products->count() > 1 )
                    <div class="ec-related__controls">
                        <x-artisanpack-button
                            variant="outline"
                            color="neutral"
                            size="sm"
                            icon-only
                            leading-icon="chevron-left"
                            class="ec-related__control"
                            :aria-label="__( 'Previous products' )"
                            aria-controls="{{ $headingId }}-list"
                            x-on:click="scrollBy( -1 )"
                            data-related-previous
                        />
                        <x-artisanpack-button
                            variant="outline"
                            color="neutral"
                            size="sm"
                            icon-only
                            leading-icon="chevron-right"
                            class="ec-related__control"
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
                role="list"
                class="ec-related__list"
                data-columns="{{ $gridColumns }}"
                aria-labelledby="{{ $headingId }}"
                data-related-list
            >
                @foreach ( $products as $related )
                    <li class="ec-related__item" wire:key="related-{{ $type }}-{{ $related->id }}">
                        <x-artisanpack-ec-sf-product-card :product="$related" :currency="$currency" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
