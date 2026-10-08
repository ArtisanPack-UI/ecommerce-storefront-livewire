{{--
    The header search box. See Search\HeaderSearch.

    An ARIA 1.2 combobox: the input owns a listbox of products, categories,
    and "See all results". Arrow keys move the highlight
    (`aria-activedescendant`), Enter opens the highlighted option or submits
    the form to the search page, Escape closes the popover, and the result
    count is announced politely.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceOptionCount = count( $products ) + count( $categories ) + ( null === $allUrl ? 0 : 1 ) )
@php( $ecommerceOptionIndex = 0 )
<div
    class="relative"
    x-data="{
        open: false,
        active: -1,
        options() {
            return this.$refs.listbox ? Array.from( this.$refs.listbox.querySelectorAll( '[role=option]' ) ) : [];
        },
        move( step ) {
            const options = this.options();

            this.open = true;

            if ( 0 === options.length ) {
                this.active = -1;

                return;
            }

            this.active = this.active < 0 && step < 0 ? options.length - 1 : ( this.active + step + options.length ) % options.length;
            options[ this.active ].scrollIntoView( { block: 'nearest' } );
        },
        activeId() {
            const option = this.open ? this.options()[ this.active ] : null;

            return option ? option.id : null;
        },
        choose( event ) {
            const option = this.open ? this.options()[ this.active ] : null;

            if ( option ) {
                event.preventDefault();
                window.location.assign( option.getAttribute( 'href' ) );
            }
        },
        close() {
            this.open   = false;
            this.active = -1;
        },
    }"
    x-on:click.outside="close()"
    x-on:focusout="$el.contains( $event.relatedTarget ) || close()"
    data-header-search
>
    <form method="GET" @if ( null !== $searchUrl ) action="{{ $searchUrl }}" @endif role="search" x-on:submit="choose( $event )" data-header-search-form>
        <x-artisanpack-input
            :id="$inputId"
            type="search"
            name="q"
            :placeholder="__( 'Search products' )"
            :aria-label="__( 'Search products' )"
            icon="o-magnifying-glass"
            wire:model.live.debounce.300ms="q"
            autocomplete="off"
            enterkeyhint="search"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $listboxId }}"
            aria-haspopup="listbox"
            x-bind:aria-expanded="( open && {{ $ecommerceOptionCount > 0 || 'idle' !== $state ? 'true' : 'false' }} ).toString()"
            x-bind:aria-activedescendant="activeId()"
            x-on:input="open = true; active = -1"
            x-on:focus="open = true"
            x-on:keydown.arrow-down.prevent="move( 1 )"
            x-on:keydown.arrow-up.prevent="move( -1 )"
            x-on:keydown.escape="if ( open ) { $event.preventDefault(); close(); }"
            class="input-sm"
            error-field="q"
        />
    </form>

    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-header-search-status>
        @if ( 'results' === $state )
            {{ trans_choice( ':count suggestion|:count suggestions', count( $products ) + count( $categories ), [ 'count' => count( $products ) + count( $categories ) ] ) }}
        @endif
    </p>

    <div
        x-show="open"
        x-cloak
        @class( [ 'absolute end-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-box border border-base-content/10 bg-base-100 p-2 shadow-lg', 'hidden' => 'idle' === $state ] )
        wire:loading.class="opacity-60"
        wire:target="q"
        data-header-search-popover
    >
        @if ( 'throttled' === $state )
            <p class="p-2 text-sm" data-header-search-throttled>{{ __( 'Too many searches. Press Enter to see all results.' ) }}</p>
        @elseif ( 'failed' === $state )
            <p class="p-2 text-sm" data-header-search-failed>{{ __( 'Suggestions aren\'t available right now. Press Enter to search.' ) }}</p>
        @elseif ( 'results' === $state && 0 === $ecommerceOptionCount )
            <p class="p-2 text-sm" data-header-search-empty>{{ __( 'No matches for ":term".', [ 'term' => $q ] ) }}</p>
        @endif

        <div id="{{ $listboxId }}" x-ref="listbox" role="listbox" aria-label="{{ __( 'Search suggestions' ) }}" class="flex flex-col gap-1" data-header-search-results>
            @if ( [] !== $products )
                <div role="group" aria-labelledby="{{ $listboxId }}-products">
                    <p id="{{ $listboxId }}-products" role="presentation" class="px-2 py-1 text-xs font-semibold uppercase text-base-content/70">{{ __( 'Products' ) }}</p>

                    @foreach ( $products as $product )
                        @if ( null !== $product['url'] )
                            @php( $ecommerceOption = $ecommerceOptionIndex++ )
                            <a
                                id="{{ $listboxId }}-option-{{ $ecommerceOption }}"
                                href="{{ $product['url'] }}"
                                role="option"
                                tabindex="-1"
                                class="flex min-h-6 items-center gap-3 rounded-field p-2"
                                x-bind:class="active === {{ $ecommerceOption }} && 'bg-base-200'"
                                x-bind:aria-selected="( active === {{ $ecommerceOption }} ).toString()"
                                x-on:mouseenter="active = {{ $ecommerceOption }}"
                                wire:key="header-search-product-{{ $product['id'] }}"
                                data-header-search-product="{{ $product['id'] }}"
                            >
                                <span class="block h-10 w-10 shrink-0 overflow-hidden rounded-field bg-base-200" aria-hidden="true">
                                    @if ( null !== $product['image'] )
                                        <img src="{{ $product['image']['url'] }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                    @endif
                                </span>

                                <span class="flex min-w-0 flex-col">
                                    <span class="truncate text-sm font-medium">{{ $product['name'] }}</span>
                                    <x-artisanpack-ec-price :price="$product['price']" class="text-xs" />
                                </span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            @if ( [] !== $categories )
                <div role="group" aria-labelledby="{{ $listboxId }}-categories">
                    <p id="{{ $listboxId }}-categories" role="presentation" class="px-2 py-1 text-xs font-semibold uppercase text-base-content/70">{{ __( 'Categories' ) }}</p>

                    @foreach ( $categories as $category )
                        @if ( null !== $category['url'] )
                            @php( $ecommerceOption = $ecommerceOptionIndex++ )
                            <a
                                id="{{ $listboxId }}-option-{{ $ecommerceOption }}"
                                href="{{ $category['url'] }}"
                                role="option"
                                tabindex="-1"
                                class="flex min-h-6 items-center gap-2 rounded-field p-2 text-sm"
                                x-bind:class="active === {{ $ecommerceOption }} && 'bg-base-200'"
                                x-bind:aria-selected="( active === {{ $ecommerceOption }} ).toString()"
                                x-on:mouseenter="active = {{ $ecommerceOption }}"
                                wire:key="header-search-category-{{ $category['id'] }}"
                                data-header-search-category="{{ $category['id'] }}"
                            >
                                <x-artisanpack-icon name="o-folder" class="h-4 w-4 opacity-60" aria-hidden="true" />
                                <span class="truncate">{{ $category['name'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            @if ( null !== $allUrl )
                @php( $ecommerceOption = $ecommerceOptionIndex++ )
                <a
                    id="{{ $listboxId }}-option-{{ $ecommerceOption }}"
                    href="{{ $allUrl }}"
                    role="option"
                    tabindex="-1"
                    class="flex min-h-6 items-center gap-2 rounded-field border-t border-base-content/10 p-2 text-sm font-semibold"
                    x-bind:class="active === {{ $ecommerceOption }} && 'bg-base-200'"
                    x-bind:aria-selected="( active === {{ $ecommerceOption }} ).toString()"
                    x-on:mouseenter="active = {{ $ecommerceOption }}"
                    data-header-search-all
                >
                    <x-artisanpack-icon name="o-magnifying-glass" class="h-4 w-4 opacity-60" aria-hidden="true" />
                    {{ __( 'See all results for ":term"', [ 'term' => $q ] ) }}
                </a>
            @endif
        </div>
    </div>
</div>
