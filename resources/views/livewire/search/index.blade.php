{{--
    The search page (#133). See Search\Index.

    With `showTitle` (the search page): the breadcrumbs ("Shop › Search")
    and the title, "Results for “…”" when there are results, else "Search".
    Then the query in a large search field (its label visually hidden) with
    a large neutral "Search" button. The form also works without
    JavaScript: it submits `q` to the search route. "Did you mean" links
    follow, then the catalog listing with the search's own results count
    and empty states (a card with tips and "Browse all products").

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceSearchRoute = \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.storefront.search' ) ? route( 'artisanpack.ecommerce.storefront.search' ) : null;
    $ecommerceCatalogUrl  = \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.storefront.catalog' ) ? route( 'artisanpack.ecommerce.storefront.catalog' ) : null;
    $ecommerceHasResults  = $searched && $products->total() > 0;
@endphp
<div class="ec-page" data-search-page>
    @if ( $showTitle )
        @if ( null !== $ecommerceCatalogUrl )
            <x-artisanpack-breadcrumbs :items="[ [ 'label' => __( 'Shop' ), 'link' => $ecommerceCatalogUrl ], [ 'label' => __( 'Search' ) ] ]" :label="__( 'Breadcrumb' )" :navigate="false" />
        @endif

        <h1 class="ec-search-page__title" data-search-title>{{ $ecommerceHasResults ? __( 'Results for “:term”', [ 'term' => $term ] ) : __( 'Search' ) }}</h1>
    @endif

    <form
        role="search"
        method="GET"
        @if ( null !== $ecommerceSearchRoute ) action="{{ $ecommerceSearchRoute }}" @endif
        wire:submit="search"
        class="ec-search-page__form"
        data-search-form
    >
        <x-artisanpack-input
            id="ecommerce-search-term"
            type="search"
            name="q"
            size="lg"
            :label="__( 'Search products' )"
            leading-icon="search"
            wire:model.live.debounce.500ms="q"
            autocomplete="off"
            enterkeyhint="search"
            error-field="q"
            class="ec-search-page__field"
        />

        <x-artisanpack-button type="submit" color="neutral" size="lg" wire:loading.attr="disabled" wire:target="search" data-search-submit>{{ __( 'Search' ) }}</x-artisanpack-button>
    </form>

    @if ( [] !== $suggestions )
        {{-- One translatable sentence around the suggestion links, so each language places its own punctuation (¿…?). --}}
        @php
            [ $ecommerceSuggestBefore, $ecommerceSuggestAfter ] = array_pad( explode( ':suggestions', __( 'Did you mean: :suggestions?' ), 2 ), 2, '' );
        @endphp
        <p class="ec-search-page__suggestions" data-search-suggestions>
            {{-- The design-system Link's button form, written out so no whitespace lands before the punctuation. --}}
            {{ $ecommerceSuggestBefore }}@foreach ( $suggestions as $suggestion )<button
                    type="button"
                    class="apui-link"
                    data-tone="primary"
                    wire:click="useSuggestion( {{ \Illuminate\Support\Js::from( $suggestion ) }} )"
                    wire:key="search-suggestion-{{ $loop->index }}"
                    data-search-suggestion
                >{{ $suggestion }}</button>{{ $loop->last ? '' : __( ', ' ) }}@endforeach{{ $ecommerceSuggestAfter }}
        </p>
    @endif

    @include( 'ecommerce-storefront::livewire.catalog.index', [ 'browseUrl' => $ecommerceCatalogUrl ] )
</div>
