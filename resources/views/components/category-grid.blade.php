{{--
    Category tiles (#115). See CategoryGrid.

    An optional heading over a grid of up to `columns` tiles to a row
    (`data-columns`; two to a row on small screens). Each tile is a
    design-system Card (written out, so the name can be a heading with one
    link): the 4:3 image on the sunken surface (a grid icon when the
    category has none), then the name, the product count and an arrow that
    points forward in either direction. The name's link stretches over the
    tile, so the whole tile is clickable while there is one link per
    category; hover raises its edge and focus inside draws the ring around
    it.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( [] !== $tiles )
    <section {{ $attributes->class( [ 'ec-category-grid' ] ) }} @if ( null !== $heading ) aria-labelledby="{{ $headingId }}" @else aria-label="{{ __( 'Categories' ) }}" @endif data-category-grid>
        @if ( null !== $heading )
            <h2 id="{{ $headingId }}" class="ec-category-grid__title">{{ $heading }}</h2>
        @endif

        <ul role="list" class="ec-category-grid__list" data-columns="{{ $columns }}">
            @foreach ( $tiles as $tile )
                <li class="ec-category-grid__item" data-category-tile="{{ $tile['id'] }}">
                    <article class="apui-card ec-category-tile" data-surface="default" @if ( null !== $tile['url'] ) data-interactive @endif>
                        @if ( $showImages )
                            <div class="ec-category-tile__media" data-scope="card" data-part="media" aria-hidden="true">
                                @if ( null !== $tile['image'] )
                                    @php( $ecommerceSize = \ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::dimensions( $tile['image'], 800, 600 ) )
                                    <img
                                        src="{{ $tile['image']['url'] }}"
                                        @if ( null !== $tile['image']['srcset'] ) srcset="{{ $tile['image']['srcset'] }}" sizes="(min-width: 1024px) {{ (int) ceil( 100 / $columns ) }}vw, (min-width: 640px) 33vw, 50vw" @endif
                                        width="{{ $ecommerceSize['width'] }}"
                                        height="{{ $ecommerceSize['height'] }}"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                    >
                                @else
                                    <x-artisanpack-icon name="layout-grid" class="ec-category-tile__placeholder" />
                                @endif
                            </div>
                        @endif

                        <div class="ec-category-tile__body">
                            <div class="ec-category-tile__text">
                                <h3 class="ec-category-tile__name">
                                    @if ( null !== $tile['url'] )
                                        <a href="{{ $tile['url'] }}">{{ $tile['name'] }}</a>
                                    @else
                                        {{ $tile['name'] }}
                                    @endif
                                </h3>

                                @if ( $showCounts )
                                    <p class="ec-category-tile__count" data-category-count>{{ trans_choice( ':count product|:count products', $tile['count'], [ 'count' => $tile['count'] ] ) }}</p>
                                @endif
                            </div>

                            @if ( null !== $tile['url'] )
                                <x-artisanpack-icon name="arrow-right" class="ec-category-tile__arrow" />
                            @endif
                        </div>
                    </article>
                </li>
            @endforeach
        </ul>
    </section>
@endif
