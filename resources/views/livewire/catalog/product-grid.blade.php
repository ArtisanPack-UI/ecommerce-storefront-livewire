{{--
    A fixed grid of products. See Catalog\ProductGrid.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-product-grid="{{ $source }}">
    @if ( $products->isNotEmpty() )
        <section class="flex flex-col gap-4" @if ( null !== $heading ) aria-labelledby="{{ $headingId }}" @else aria-label="{{ __( 'Products' ) }}" @endif>
            @if ( null !== $heading )
                <h2 id="{{ $headingId }}" class="text-2xl font-bold">{{ $heading }}</h2>
            @endif

            <ul role="list" class="grid gap-4 {{ $gridClass }}" data-product-grid-list>
                @foreach ( $products as $product )
                    <li wire:key="product-grid-{{ $product->id }}">
                        <x-artisanpack-ec-sf-product-card
                            :product="$product"
                            :currency="$currency"
                            :quick-add="$showAddToCart"
                            :show-price="$showPrice"
                            :show-rating="$showRating"
                            :heading-level="null !== $heading ? 3 : 2"
                        />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
