{{--
    Recently viewed products (#107). See Product\RecentlyViewed.

    Compact rows: each product is a design-system Card (written out, as on
    the product card) holding a 64px thumbnail, the name and the price. The
    name's link stretches over the row, so there is one link per product.
    The rows sit in an auto-fill grid of cards at least 240px wide, at most
    `columns` to a row. Nothing renders when there is nothing to show.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-recently-viewed>
    @if ( [] !== $rows )
        <section class="ec-recent" aria-labelledby="{{ $headingId }}">
            <h2 id="{{ $headingId }}" class="ec-recent__title">{{ $title }}</h2>

            <ul role="list" class="ec-recent__list" data-columns="{{ $columns }}">
                @foreach ( $rows as $row )
                    <li class="ec-recent__item" wire:key="recently-viewed-{{ $row['id'] }}">
                        <article class="apui-card ec-recent__card" data-surface="default" @if ( null !== $row['url'] ) data-interactive @endif data-recently-viewed-product="{{ $row['id'] }}">
                            <div class="ec-recent__media" aria-hidden="true">
                                @if ( null !== $row['image'] )
                                    <img
                                        src="{{ $row['image']['url'] }}"
                                        @if ( null !== $row['image']['srcset'] ) srcset="{{ $row['image']['srcset'] }}" sizes="64px" @endif
                                        width="64"
                                        height="64"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                    >
                                @else
                                    <x-artisanpack-icon name="image" />
                                @endif
                            </div>

                            <div class="ec-recent__body">
                                <h3 class="ec-recent__name">
                                    @if ( null !== $row['url'] )
                                        <a href="{{ $row['url'] }}" data-product-link>{{ $row['name'] }}</a>
                                    @else
                                        {{ $row['name'] }}
                                    @endif
                                </h3>

                                @if ( null !== $row['price'] )
                                    <x-artisanpack-ec-price :price="$row['price']" :sale-badge="false" :show-tax="false" size="sm" class="ec-recent__price" />
                                @endif
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
