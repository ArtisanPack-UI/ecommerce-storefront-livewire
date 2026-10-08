{{--
    The search page. See Search\Index.

    The form also works without JavaScript: it submits `q` to the search
    route.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceSearchRoute = \Illuminate\Support\Facades\Route::has( 'artisanpack.ecommerce.storefront.search' ) ? route( 'artisanpack.ecommerce.storefront.search' ) : null )
<div class="flex flex-col gap-6" data-search-page>
    <form
        role="search"
        method="GET"
        @if ( null !== $ecommerceSearchRoute ) action="{{ $ecommerceSearchRoute }}" @endif
        wire:submit="search"
        class="flex flex-wrap items-end gap-2"
        data-search-form
    >
        <div class="min-w-0 grow sm:max-w-xl">
            <x-artisanpack-input
                id="ecommerce-search-term"
                type="search"
                name="q"
                :label="__( 'Search products' )"
                icon="o-magnifying-glass"
                wire:model.live.debounce.500ms="q"
                autocomplete="off"
                enterkeyhint="search"
                error-field="q"
            />
        </div>

        <x-artisanpack-button type="submit" :label="__( 'Search' )" color="primary" wire:loading.attr="disabled" wire:target="search" data-search-submit />
    </form>

    @if ( [] !== $suggestions )
        <p class="text-sm" data-search-suggestions>
            {{ __( 'Did you mean:' ) }}
            @foreach ( $suggestions as $suggestion )
                <button
                    type="button"
                    class="link link-primary font-semibold"
                    wire:click="useSuggestion( {{ \Illuminate\Support\Js::from( $suggestion ) }} )"
                    wire:key="search-suggestion-{{ $loop->index }}"
                    data-search-suggestion
                >{{ $suggestion }}</button>{{ $loop->last ? '?' : ',' }}
            @endforeach
        </p>
    @endif

    @include( 'ecommerce-storefront::livewire.catalog.index' )
</div>
