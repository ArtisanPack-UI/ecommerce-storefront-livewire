{{--
    The header search box (#92). See Search\HeaderSearch.

    An ARIA 1.2 combobox: the input owns a listbox of products, categories,
    and "See all N results". Arrow keys move the highlight
    (`aria-activedescendant`, `aria-selected` on the active option), Enter
    opens the highlighted option or submits the form to the search page,
    Escape closes the popover, and the result count is announced politely.
    Pressing "/" anywhere outside a text field focuses the box, unless a
    modal (the cart drawer, a dialog) is open.

    The design system's Input (sunken fill, a "/" key hint at the end) and
    the DropdownMenu panel and item parts for the popover. Product rows show
    a 36px thumbnail, the name with the match marked, the category and the
    price.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceOptionCount = count( $products ) + count( $categories ) + ( null === $allUrl ? 0 : 1 ) )
@php( $ecommerceOptionIndex = 0 )
{{-- Each part is escaped; only the <mark> around a match is markup. --}}
@php( $ecommerceMarked = static fn ( array $parts ): string => implode( '', array_map( static fn ( array $part ): string => $part['match'] ? '<mark class="ec-search__match">' . e( $part['text'] ) . '</mark>' : e( $part['text'] ), $parts ) ) )
<div
    class="ec-search"
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
        shortcut( event ) {
            if ( '/' !== event.key || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey || event.isComposing ) {
                return;
            }

            if ( document.querySelector( '[aria-modal=true]:not([hidden])' ) ) {
                return;
            }

            const target = event.target;

            if ( target instanceof Element && target.closest( 'input, textarea, select, [contenteditable]:not([contenteditable=false])' ) ) {
                return;
            }

            const input = this.$el.querySelector( 'input[role=combobox]' );

            if ( input && null !== input.offsetParent ) {
                event.preventDefault();
                input.focus();
            }
        },
    }"
    x-on:click.outside="close()"
    x-on:focusout="$el.contains( $event.relatedTarget ) || close()"
    x-on:keydown.window="shortcut( $event )"
    data-header-search
>
    <form method="GET" @if ( null !== $searchUrl ) action="{{ $searchUrl }}" @endif role="search" x-on:submit="choose( $event )" data-header-search-form>
        <x-artisanpack-input
            :id="$inputId"
            type="search"
            name="q"
            :placeholder="__( 'Search products' )"
            :aria-label="__( 'Search products' )"
            leading-icon="search"
            wire:model.live.debounce.300ms="q"
            autocomplete="off"
            enterkeyhint="search"
            aria-keyshortcuts="/"
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
            class="ec-search__field"
            error-field="q"
        >
            <x-slot:iconEnd><x-artisanpack-kbd size="sm" class="ec-search__hint">/</x-artisanpack-kbd></x-slot:iconEnd>
        </x-artisanpack-input>
    </form>

    <p class="apui-sr-only" role="status" aria-live="polite" aria-atomic="true" data-header-search-status>
        @if ( 'results' === $state )
            {{ trans_choice( ':count suggestion|:count suggestions', count( $products ) + count( $categories ), [ 'count' => count( $products ) + count( $categories ) ] ) }}
        @endif
    </p>

    <div
        x-show="open"
        x-cloak
        class="apui-dropdown-menu ec-search__popover"
        data-surface="overlay"
        @if ( 'idle' === $state ) hidden @endif
        wire:loading.attr="data-loading"
        wire:target="q"
        data-header-search-popover
    >
        @if ( 'throttled' === $state )
            <p class="ec-search__message" data-header-search-throttled>{{ __( 'Too many searches. Press Enter to see all results.' ) }}</p>
        @elseif ( 'failed' === $state )
            <p class="ec-search__message" data-header-search-failed>{{ __( 'Suggestions aren\'t available right now. Press Enter to search.' ) }}</p>
        @elseif ( 'results' === $state && 0 === $ecommerceOptionCount )
            <p class="ec-search__message" data-header-search-empty>{{ __( 'No matches for ":term".', [ 'term' => $q ] ) }}</p>
        @endif

        <div id="{{ $listboxId }}" x-ref="listbox" role="listbox" aria-label="{{ __( 'Search suggestions' ) }}" class="ec-search__results" data-header-search-results>
            @if ( [] !== $products )
                <div role="group" aria-labelledby="{{ $listboxId }}-products">
                    <p id="{{ $listboxId }}-products" role="presentation" data-scope="dropdown-menu" data-part="item-group-label">{{ __( 'Products' ) }}</p>

                    @foreach ( $products as $product )
                        @if ( null !== $product['url'] )
                            @php( $ecommerceOption = $ecommerceOptionIndex++ )
                            <a
                                id="{{ $listboxId }}-option-{{ $ecommerceOption }}"
                                href="{{ $product['url'] }}"
                                role="option"
                                tabindex="-1"
                                class="ec-search__option ec-search__option--product"
                                data-scope="dropdown-menu"
                                data-part="item"
                                x-bind:data-highlighted="active === {{ $ecommerceOption }} ? '' : null"
                                x-bind:aria-selected="( active === {{ $ecommerceOption }} ).toString()"
                                x-on:mouseenter="active = {{ $ecommerceOption }}"
                                wire:key="header-search-product-{{ $product['id'] }}"
                                data-header-search-product="{{ $product['id'] }}"
                            >
                                <span class="ec-search__thumb" aria-hidden="true">
                                    @if ( null !== $product['image'] )
                                        <img src="{{ $product['image']['url'] }}" @if ( null !== ( $product['image']['srcset'] ?? null ) ) srcset="{{ $product['image']['srcset'] }}" sizes="36px" @endif width="36" height="36" alt="" loading="lazy" decoding="async">
                                    @endif
                                </span>

                                <span class="ec-search__text">
                                    <span class="ec-search__name">{!! $ecommerceMarked( $product['parts'] ) !!}</span>
                                    @if ( null !== $product['category'] )
                                        <span class="ec-search__meta" data-header-search-category-name>{{ $product['category'] }}</span>
                                    @endif
                                </span>

                                @if ( null !== $product['price'] )
                                    <x-artisanpack-ec-price :price="$product['price']" size="sm" class="ec-search__price" />
                                @endif
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            @if ( [] !== $categories )
                <div role="group" aria-labelledby="{{ $listboxId }}-categories">
                    <p id="{{ $listboxId }}-categories" role="presentation" data-scope="dropdown-menu" data-part="item-group-label">{{ __( 'Categories' ) }}</p>

                    @foreach ( $categories as $category )
                        @if ( null !== $category['url'] )
                            @php( $ecommerceOption = $ecommerceOptionIndex++ )
                            <a
                                id="{{ $listboxId }}-option-{{ $ecommerceOption }}"
                                href="{{ $category['url'] }}"
                                role="option"
                                tabindex="-1"
                                class="ec-search__option"
                                data-scope="dropdown-menu"
                                data-part="item"
                                x-bind:data-highlighted="active === {{ $ecommerceOption }} ? '' : null"
                                x-bind:aria-selected="( active === {{ $ecommerceOption }} ).toString()"
                                x-on:mouseenter="active = {{ $ecommerceOption }}"
                                wire:key="header-search-category-{{ $category['id'] }}"
                                data-header-search-category="{{ $category['id'] }}"
                            >
                                <x-artisanpack-icon name="folder" class="ec-search__icon" />
                                <span class="ec-search__name">{!! $ecommerceMarked( $category['parts'] ) !!}</span>
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
                    class="ec-search__option ec-search__option--all"
                    data-scope="dropdown-menu"
                    data-part="item"
                    x-bind:data-highlighted="active === {{ $ecommerceOption }} ? '' : null"
                    x-bind:aria-selected="( active === {{ $ecommerceOption }} ).toString()"
                    x-on:mouseenter="active = {{ $ecommerceOption }}"
                    data-header-search-all
                >
                    <x-artisanpack-icon name="search" class="ec-search__icon" />
                    <span class="ec-search__name">{{ trans_choice( 'See all :count result for ":term"|See all :count results for ":term"', $total, [ 'count' => $total, 'term' => $q ] ) }}</span>
                    <x-artisanpack-icon name="arrow-right" class="ec-search__icon" />
                </a>
            @endif
        </div>
    </div>
</div>
