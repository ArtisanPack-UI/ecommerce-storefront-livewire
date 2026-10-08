{{--
    Storefront product card. See ProductCard.

    The product name's link stretches over the card (a pseudo-element), so
    the whole card is clickable while there is one link per product for
    keyboard and screen-reader users. The quick-add button sits above it.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceHeading = 'h' . $card['heading_level'] )
<x-artisanpack-card {{ $attributes->class( [ 'relative h-full border border-base-content/10 transition-shadow hover:shadow-md focus-within:shadow-md' ] ) }} data-product-card="{{ $card['id'] }}">
    <x-slot:figure class="aspect-square bg-base-200">
        @if ( null !== $card['image'] )
            <img
                src="{{ $card['image']['url'] }}"
                @if ( null !== $card['image']['srcset'] ) srcset="{{ $card['image']['srcset'] }}" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw" @endif
                alt="{{ $card['image']['alt'] }}"
                loading="lazy"
                decoding="async"
                class="h-full w-full object-cover"
            >
        @else
            <div class="flex h-full w-full items-center justify-center" aria-hidden="true">
                <x-artisanpack-icon name="o-photo" class="h-12 w-12 opacity-30" />
            </div>
        @endif
    </x-slot:figure>

    <div class="flex grow flex-col gap-2">
        @if ( [] !== $card['badges'] )
            <div class="flex flex-wrap gap-1">
                @foreach ( $card['badges'] as $badge )
                    <x-artisanpack-badge :value="$badge" class="badge-neutral badge-sm" />
                @endforeach
            </div>
        @endif

        <{{ $ecommerceHeading }} class="text-base font-semibold leading-snug">
            @if ( null !== $card['url'] )
                <a href="{{ $card['url'] }}" class="link-hover after:absolute after:inset-0 after:content-[''] focus-visible:outline-none" data-product-link>{{ $card['name'] }}</a>
            @else
                {{ $card['name'] }}
            @endif
        </{{ $ecommerceHeading }}>

        @if ( $showPrice )
            <x-artisanpack-ec-price :price="$card['price']" />
        @endif

        @if ( $showRating )
            <x-artisanpack-ec-rating-summary :rating="$card['rating']" :count="$card['reviews']" hide-empty class="relative z-10" />
        @endif

        <x-artisanpack-ec-stock-status :status="$card['stock']" />

        @if ( $card['quick_add'] )
            <div class="relative z-10 mt-auto pt-2">
                <x-artisanpack-button
                    :label="__( 'Add to cart' )"
                    icon="o-shopping-cart"
                    class="btn-sm w-full"
                    color="primary"
                    :aria-label="__( 'Add :name to cart', [ 'name' => $card['name'] ] )"
                    wire:click="quickAdd( {{ $card['id'] }} )"
                    wire:loading.attr="disabled"
                    wire:target="quickAdd( {{ $card['id'] }} )"
                    spinner="quickAdd( {{ $card['id'] }} )"
                    data-quick-add="{{ $card['id'] }}"
                />
            </div>
        @endif
    </div>
</x-artisanpack-card>
