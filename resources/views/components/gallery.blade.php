{{--
    Product gallery. See Gallery.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( [] === $items )
    <div {{ $attributes->class( [ 'flex aspect-square w-full items-center justify-center rounded-box bg-base-200' ] ) }} data-gallery="empty">
        <x-artisanpack-icon name="o-photo" class="h-16 w-16 opacity-30" aria-hidden="true" />
        <span class="sr-only">{{ __( 'No image available' ) }}</span>
    </div>
@else
    @php( $ecommerceBeside = in_array( $thumbnails, [ 'start', 'end' ], true ) )
    <div
        {{ $attributes->class( [ 'flex flex-col gap-3', 'sm:flex-row' => 'end' === $thumbnails, 'sm:flex-row-reverse' => 'start' === $thumbnails ] ) }}
        x-data="{
            images: @js( $items ),
            active: {{ $active }},
            zoomed: false,
            origin: '50% 50%',
            show( index ) {
                this.active = ( index + this.images.length ) % this.images.length;
                this.zoomed = false;
            },
            showUrl( url ) {
                const index = this.images.findIndex( ( image ) => image.url === url || image.full === url );

                if ( index >= 0 ) {
                    this.show( index );
                }
            },
            pan( event ) {
                const box = event.currentTarget.getBoundingClientRect();
                this.origin = ( ( event.clientX - box.left ) / box.width * 100 ) + '% ' + ( ( event.clientY - box.top ) / box.height * 100 ) + '%';
            },
            openLightbox() {
                this.$refs.lightbox.showModal();
            },
        }"
        x-on:ecommerce-gallery-show.window="( {{ \Illuminate\Support\Js::from( $scope ) }} === null || $event.detail.scope === {{ \Illuminate\Support\Js::from( $scope ) }} ) && showUrl( $event.detail.url )"
        role="region"
        aria-roledescription="{{ __( 'gallery' ) }}"
        aria-label="{{ __( ':name images', [ 'name' => $name ] ) }}"
        data-gallery
        data-gallery-thumbnails="{{ $thumbnails }}"
    >
        <div @class( [ 'relative overflow-hidden rounded-box bg-base-200', 'min-w-0 grow' => $ecommerceBeside ] )>
            @if ( $zoom )
                <button
                    type="button"
                    class="block aspect-square w-full cursor-zoom-in overflow-hidden"
                    x-bind:class="zoomed && 'cursor-zoom-out'"
                    x-on:click="zoomed = ! zoomed"
                    x-on:mousemove="zoomed && pan( $event )"
                    x-bind:aria-pressed="zoomed.toString()"
                    aria-label="{{ __( 'Zoom image' ) }}"
                    data-gallery-zoom
                >
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
                            class="h-full w-full object-contain transition-transform duration-200"
                            x-show="active === {{ $index }}"
                            x-bind:style="zoomed ? { transform: 'scale(2)', transformOrigin: origin } : { transform: '', transformOrigin: '' }"
                            data-gallery-image="{{ $index }}"
                        >
                    @endforeach
                </button>
            @else
                <div class="block aspect-square w-full overflow-hidden" data-gallery-stage>
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
                            class="h-full w-full object-contain"
                            x-show="active === {{ $index }}"
                            data-gallery-image="{{ $index }}"
                        >
                    @endforeach
                </div>
            @endif

            <x-artisanpack-button
                icon="o-arrows-pointing-out"
                class="btn-circle btn-sm absolute end-3 top-3"
                :aria-label="__( 'Open full-screen image' )"
                x-on:click="openLightbox()"
                data-gallery-open
            />
        </div>

        @if ( count( $items ) > 1 && 'none' !== $thumbnails )
            <ul role="list" @class( [ 'flex flex-wrap gap-2', 'sm:w-16 sm:shrink-0 sm:flex-col sm:flex-nowrap' => $ecommerceBeside ] ) aria-label="{{ __( 'Thumbnails' ) }}">
                @foreach ( $items as $index => $item )
                    <li>
                        <button
                            type="button"
                            class="block h-16 w-16 overflow-hidden rounded-box border-2 border-transparent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                            x-bind:class="active === {{ $index }} ? 'border-primary' : 'opacity-70 hover:opacity-100'"
                            x-bind:aria-current="( active === {{ $index }} ).toString()"
                            x-on:click="show( {{ $index }} )"
                            aria-label="{{ __( 'Show image :number of :total', [ 'number' => $index + 1, 'total' => count( $items ) ] ) }}"
                            data-gallery-thumbnail="{{ $index }}"
                        >
                            <img src="{{ $item['url'] }}" @if ( null !== $item['srcset'] ) srcset="{{ $item['srcset'] }}" sizes="64px" @endif width="64" height="64" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        <dialog
            x-ref="lightbox"
            class="modal"
            aria-label="{{ __( ':name images', [ 'name' => $name ] ) }}"
            x-on:keydown.arrow-right.prevent="show( active + 1 )"
            x-on:keydown.arrow-left.prevent="show( active - 1 )"
            data-gallery-lightbox
        >
            <div class="modal-box flex max-w-5xl flex-col gap-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm tabular-nums" aria-live="polite" x-text="@js( __( 'Image :number of :total' ) ).replace( ':number', active + 1 ).replace( ':total', images.length )"></p>

                    <form method="dialog">
                        <x-artisanpack-button type="submit" icon="o-x-mark" class="btn-ghost btn-sm btn-circle" :aria-label="__( 'Close' )" />
                    </form>
                </div>

                <template x-for="( image, index ) in images" :key="index">
                    <img x-show="active === index" :src="image.full" :alt="image.alt" loading="lazy" decoding="async" class="max-h-[80vh] w-full object-contain">
                </template>

                @if ( count( $items ) > 1 )
                    <div class="flex justify-between gap-2">
                        <x-artisanpack-button icon="o-chevron-left" :label="__( 'Previous' )" class="btn-sm" x-on:click="show( active - 1 )" />
                        <x-artisanpack-button icon-right="o-chevron-right" :label="__( 'Next' )" class="btn-sm" x-on:click="show( active + 1 )" />
                    </div>
                @endif
            </div>

            <form method="dialog" class="modal-backdrop">
                <button type="submit" tabindex="-1">{{ __( 'Close' ) }}</button>
            </form>
        </dialog>
    </div>
@endif
