{{--
    Product gallery (#80). See Gallery.

    The root is both the gallery region and the design system's
    `apuiLightbox` (its `open` and `index` state); the gallery's own state
    (the image shown, the announcement) lives one scope in, so it can open
    the lightbox at the image shown. Layout in resources/css/storefront.css
    (ec-gallery): the square main image on the sunken surface with a
    hairline border, the "1 / 4" counter Badge at the top start and the
    Zoom Button (solid neutral on the raised surface) at the bottom end;
    thumbnails in a four-column grid below, or a column beside it from 640px
    up, with the current one ringed in the primary color.

    The lightbox markup follows the design system's ImageGallery contract
    (parts with ids under the root's id, teleported to the body).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( [] === $items )
    <div {{ $attributes->class( [ 'ec-gallery', 'ec-gallery--empty' ] ) }} data-gallery="empty">
        <x-artisanpack-icon name="image" aria-hidden="true" />
        <span class="apui-sr-only">{{ __( 'No image available' ) }}</span>
    </div>
@else
    @php
        $ecommerceBaseId = $baseId();
        $ecommerceCount  = count( $items );
        $ecommerceScope  = \Illuminate\Support\Js::from( $scope );
        $ecommerceAlt    = (string) ( $items[ $active ]['alt'] ?? '' );
    @endphp
    <div
        {{ $attributes->except( 'id' )->class( [ 'ec-gallery' ] ) }}
        id="{{ $ecommerceBaseId }}"
        @if ( $zoom ) x-data="{{ $lightboxData() }}" @endif
        role="region"
        aria-roledescription="{{ __( 'gallery' ) }}"
        aria-label="{{ __( ':name images', [ 'name' => $name ] ) }}"
        data-gallery
        data-gallery-thumbnails="{{ $thumbnails }}"
    >
        <div
            class="ec-gallery__layout"
            x-data="{
                images: @js( $items ),
                active: {{ $active }},
                announcement: '',
                show( index ) {
                    this.active       = ( index + this.images.length ) % this.images.length;
                    this.announcement = @js( __( 'Image :number of :total: :alt' ) ).replace( ':number', this.active + 1 ).replace( ':total', this.images.length ).replace( ':alt', this.images[ this.active ].alt );
                },
                showUrl( url ) {
                    const index = this.images.findIndex( ( image ) => image.url === url || image.full === url );

                    if ( index >= 0 ) {
                        this.show( index );
                    }
                },
                zoomLabel() {
                    const alt = this.images[ this.active ].alt;

                    return alt ? @js( __( 'Zoom image: :alt' ) ).replace( ':alt', alt ) : @js( __( 'Zoom image' ) );
                },
                counter() {
                    return @js( __( ':index / :count' ) ).replace( ':index', this.active + 1 ).replace( ':count', this.images.length );
                },
                @if ( $zoom )
                    zoomIn() {
                        this.index = this.active;
                        this.open  = true;
                    },
                @endif
            }"
            @if ( $zoom ) x-init="$watch( 'index', ( value ) => open && ( active = Math.min( Math.max( Number( value ) || 0, 0 ), images.length - 1 ) ) )" @endif
            x-on:ecommerce-gallery-show.window="( {{ $ecommerceScope }} === null || $event.detail.scope === {{ $ecommerceScope }} ) && showUrl( $event.detail.url )"
        >
            <div class="ec-gallery__stage" data-gallery-stage>
                @foreach ( $items as $index => $item )
                    @php( $ecommerceSize = \ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::dimensions( $item, 1200, 1200 ) )
                    <img
                        src="{{ $item['url'] }}"
                        @if ( null !== $item['srcset'] ) srcset="{{ $item['srcset'] }}" sizes="(min-width: 1024px) 50vw, 100vw" @endif
                        width="{{ $ecommerceSize['width'] }}"
                        height="{{ $ecommerceSize['height'] }}"
                        alt="{{ $item['alt'] }}"
                        @if ( $index !== $active ) loading="lazy" style="display: none" @endif
                        decoding="async"
                        class="ec-gallery__image"
                        x-show="active === {{ $index }}"
                        @if ( $zoom ) x-on:click="zoomIn()" @endif
                        data-gallery-image="{{ $index }}"
                    >
                @endforeach

                @if ( $ecommerceCount > 1 )
                    <x-artisanpack-badge variant="solid" color="neutral" class="ec-gallery__counter" aria-hidden="true" data-gallery-counter>
                        <span x-text="counter()">{{ __( ':index / :count', [ 'index' => $active + 1, 'count' => $ecommerceCount ] ) }}</span>
                    </x-artisanpack-badge>
                @endif

                @if ( $zoom )
                    <x-artisanpack-button
                        variant="solid"
                        color="neutral"
                        size="sm"
                        leading-icon="zoom-in"
                        class="ec-gallery__zoom"
                        aria-haspopup="dialog"
                        :aria-label="'' !== $ecommerceAlt ? __( 'Zoom image: :alt', [ 'alt' => $ecommerceAlt ] ) : __( 'Zoom image' )"
                        x-bind:aria-label="zoomLabel()"
                        x-on:click="zoomIn()"
                        data-gallery-zoom
                    >{{ __( 'Zoom' ) }}</x-artisanpack-button>
                @endif
            </div>

            {{-- Announces the image a thumbnail or a variant change brings up. --}}
            <p class="apui-sr-only" aria-live="polite" aria-atomic="true" x-text="announcement" data-gallery-announcement></p>

            @if ( $ecommerceCount > 1 && 'none' !== $thumbnails )
                <ul role="list" class="ec-gallery__thumbnails" aria-label="{{ __( 'Thumbnails' ) }}">
                    @foreach ( $items as $index => $item )
                        <li>
                            <button
                                type="button"
                                class="ec-gallery__thumbnail"
                                @if ( $index === $active ) aria-current="true" @endif
                                x-bind:aria-current="active === {{ $index }} ? 'true' : null"
                                x-on:click="show( {{ $index }} )"
                                aria-label="{{ __( 'Image :number of :total: :alt', [ 'number' => $index + 1, 'total' => $ecommerceCount, 'alt' => $item['alt'] ] ) }}"
                                data-gallery-thumbnail="{{ $index }}"
                            >
                                <img src="{{ $item['url'] }}" @if ( null !== $item['srcset'] ) srcset="{{ $item['srcset'] }}" sizes="128px" @endif width="128" height="128" alt="" loading="lazy" decoding="async">
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ( $zoom )
            <template x-teleport="body">
                <div id="{{ $ecommerceBaseId }}-layer" class="ec-gallery__layer" data-gallery-lightbox>
                    <div data-scope="image-gallery" data-part="backdrop" id="{{ $ecommerceBaseId }}-backdrop" data-state="closed" x-apui:backdrop hidden></div>
                    <div data-scope="image-gallery" data-part="positioner" id="{{ $ecommerceBaseId }}-positioner" x-apui:positioner>
                        <div
                            class="apui-lightbox"
                            data-scope="image-gallery"
                            data-part="content"
                            id="{{ $ecommerceBaseId }}-content"
                            role="dialog"
                            tabindex="-1"
                            data-state="closed"
                            aria-labelledby="{{ $ecommerceBaseId }}-title"
                            aria-modal="true"
                            hidden
                            x-apui:content
                        >
                            <div data-scope="image-gallery" data-part="bar" id="{{ $ecommerceBaseId }}-bar">
                                <h2 data-scope="image-gallery" data-part="title" id="{{ $ecommerceBaseId }}-title" aria-live="polite" x-apui:title x-text="title">{{ __( ':index / :count', [ 'index' => $active + 1, 'count' => $ecommerceCount ] ) }} · {{ $name }}</h2>
                                <x-artisanpack-button
                                    variant="ghost"
                                    color="neutral"
                                    size="sm"
                                    icon-only
                                    leading-icon="x"
                                    :aria-label="__( 'Close' )"
                                    :id="$ecommerceBaseId . '-close-trigger'"
                                    data-scope="image-gallery"
                                    data-part="close-trigger"
                                    x-apui:close-trigger=""
                                />
                            </div>
                            <div data-scope="image-gallery" data-part="stage" id="{{ $ecommerceBaseId }}-stage">
                                <img data-scope="image-gallery" data-part="image" id="{{ $ecommerceBaseId }}-stage-image" src="{{ $items[ $active ]['full'] }}" alt="{{ $items[ $active ]['alt'] }}" x-apui:image>
                                <x-artisanpack-button
                                    variant="soft"
                                    color="neutral"
                                    icon-only
                                    leading-icon="chevron-left"
                                    :aria-label="__( 'Previous image' )"
                                    :id="$ecommerceBaseId . '-prev-trigger'"
                                    :disabled="$ecommerceCount < 2"
                                    data-scope="image-gallery"
                                    data-part="prev-trigger"
                                    x-apui:prev-trigger=""
                                />
                                <x-artisanpack-button
                                    variant="soft"
                                    color="neutral"
                                    icon-only
                                    leading-icon="chevron-right"
                                    :aria-label="__( 'Next image' )"
                                    :id="$ecommerceBaseId . '-next-trigger'"
                                    :disabled="$ecommerceCount < 2"
                                    data-scope="image-gallery"
                                    data-part="next-trigger"
                                    x-apui:next-trigger=""
                                />
                            </div>
                            @if ( $ecommerceCount > 1 )
                                <div data-scope="image-gallery" data-part="thumbs" id="{{ $ecommerceBaseId }}-thumbs" role="group" aria-label="{{ __( 'Thumbnails' ) }}">
                                    @foreach ( $items as $index => $item )
                                        <button
                                            type="button"
                                            data-scope="image-gallery"
                                            data-part="thumb"
                                            id="{{ $ecommerceBaseId }}-thumb-{{ $index }}"
                                            @if ( $index === $active ) aria-current="true" @endif
                                            aria-label="{{ __( 'Image :number of :total: :alt', [ 'number' => $index + 1, 'total' => $ecommerceCount, 'alt' => $item['alt'] ] ) }}"
                                            x-apui:thumb="{ index: {{ $index }} }"
                                        ><img src="{{ $item['url'] }}" alt="" loading="lazy" decoding="async"></button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </template>
        @endif
    </div>
@endif
