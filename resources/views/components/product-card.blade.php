{{--
    Product card (#73). See ProductCard.

    Design-system Card markup (written out, so the name can take any heading
    level), with the card's own layout in resources/css/storefront.css
    (ec-product-card): the square image on the sunken surface with its badges
    at the top start and the satellites' actions at the top end, then the
    category, the name, the price, the rating and stock row, and the quick
    add pinned to the bottom.

    The name's link stretches over the card (a pseudo-element), so the whole
    card is clickable while there is one link per product for keyboard and
    screen-reader users. The actions and "Add to cart" sit above it; hover
    raises the card's edge and focus inside it draws the focus ring around
    the card.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceHeading = 'h' . $card['heading_level'];
    $ecommerceCta     = $cta();
    $ecommerceOnSale  = null !== $card['price'] && $card['price']->onSale();
@endphp
<article {{ $attributes->class( [ 'apui-card ec-product-card' ] ) }} data-surface="default" data-interactive data-product-card="{{ $card['id'] }}" @if ( $soldOut() ) data-sold-out @endif>
    <div class="ec-product-card__media" data-scope="card" data-part="media">
        @if ( null !== $card['image'] )
            @php( $ecommerceSize = \ArtisanPackUI\EcommerceStorefrontLivewire\Support\ProductImages::dimensions( $card['image'], 600, 600 ) )
            <img
                src="{{ $card['image']['url'] }}"
                @if ( null !== $card['image']['srcset'] ) srcset="{{ $card['image']['srcset'] }}" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw" @endif
                width="{{ $ecommerceSize['width'] }}"
                height="{{ $ecommerceSize['height'] }}"
                alt="{{ $card['image']['alt'] }}"
                loading="lazy"
                decoding="async"
            >
        @else
            <div class="ec-product-card__placeholder" aria-hidden="true">
                <x-artisanpack-icon name="image" />
            </div>
        @endif

        @if ( $ecommerceOnSale || [] !== $card['badges'] )
            <div class="ec-product-card__badges" data-product-card-badges>
                @if ( $ecommerceOnSale )
                    <x-artisanpack-badge variant="solid" color="error">{{ __( 'Sale' ) }}</x-artisanpack-badge>
                @endif

                @foreach ( $card['badges'] as $ecommerceBadge )
                    <x-artisanpack-badge variant="solid" color="neutral">{{ $ecommerceBadge }}</x-artisanpack-badge>
                @endforeach
            </div>
        @endif

        @if ( [] !== $card['actions'] )
            <div class="ec-product-card__actions" data-product-card-actions>
                @foreach ( $card['actions'] as $ecommerceActionKey => $ecommerceAction )
                    @if ( $ecommerceAction instanceof \Illuminate\Contracts\Support\Htmlable )
                        {{ $ecommerceAction }}
                    @elseif ( is_string( $ecommerceAction ) && view()->exists( $ecommerceAction ) )
                        @include( $ecommerceAction, [ 'product' => $product ] )
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    <div class="ec-product-card__body">
        @if ( null !== $card['category'] && '' !== $card['category'] )
            <p class="ec-product-card__category" data-product-card-category>{{ $card['category'] }}</p>
        @endif

        <{{ $ecommerceHeading }} class="ec-product-card__name">
            @if ( null !== $card['url'] )
                {{-- "Choose options" is drawn below but is not a control, so the link carries the words for screen readers and voice control. --}}
                <a href="{{ $card['url'] }}" data-product-link>{{ $card['name'] }}@if ( 'options' === $ecommerceCta )<span class="apui-sr-only">: {{ __( 'Choose options' ) }}</span>@endif</a>
            @else
                {{ $card['name'] }}
            @endif
        </{{ $ecommerceHeading }}>

        @if ( $showPrice )
            <x-artisanpack-ec-price :price="$card['price']" :sale-badge="false" />
        @endif

        @php( $ecommerceShowsStock = \ArtisanPackUI\Ecommerce\Inventory\StockStatus::IN_STOCK !== $card['stock']->status )
        @if ( ( $showRating && $card['reviews'] > 0 ) || $ecommerceShowsStock )
            <div class="ec-product-card__meta">
                @if ( $showRating )
                    <x-artisanpack-ec-rating-summary :rating="$card['rating']" :count="$card['reviews']" hide-empty compact />
                @endif

                <x-artisanpack-ec-stock-status :status="$card['stock']" compact />
            </div>
        @endif

        @if ( 'add' === $ecommerceCta )
            <div class="ec-product-card__cta" data-cta="add">
                {{-- The visible text starts the name (WCAG 2.5.3); the product is added for screen readers. --}}
                <x-artisanpack-button
                    variant="outline"
                    color="neutral"
                    size="sm"
                    leading-icon="plus"
                    wire:click="quickAdd( {{ $card['id'] }} )"
                    wire:loading.attr="disabled"
                    wire:target="quickAdd( {{ $card['id'] }} )"
                    data-quick-add="{{ $card['id'] }}"
                >{{ __( 'Add to cart' ) }}<span class="apui-sr-only">: {{ $card['name'] }}</span></x-artisanpack-button>
            </div>
        @elseif ( 'options' === $ecommerceCta )
            {{-- Not a second link: the card's link takes the click to the product page. --}}
            <div class="ec-product-card__cta" data-cta="options" aria-hidden="true">
                <span class="apui-button" data-variant="outline" data-color="neutral" data-size="sm" data-product-card-options>
                    <span data-scope="button" data-part="icon" aria-hidden="true"><x-artisanpack-icon name="plus" /></span>
                    <span data-scope="button" data-part="label">{{ __( 'Choose options' ) }}</span>
                </span>
            </div>
        @elseif ( 'sold_out' === $ecommerceCta )
            <div class="ec-product-card__cta" data-cta="sold_out">
                <x-artisanpack-button variant="outline" color="neutral" size="sm" disabled data-product-card-sold-out>{{ __( 'Sold out' ) }}<span class="apui-sr-only">: {{ $card['name'] }}</span></x-artisanpack-button>
            </div>
        @endif
    </div>
</article>
