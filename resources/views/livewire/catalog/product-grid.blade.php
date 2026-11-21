{{--
    A fixed grid of products (#119). See Catalog\ProductGrid.

    The optional heading, then product cards laid out by `data-columns`
    (ec-product-grid in resources/css/storefront.css): two to a row on small
    screens, three from 640px for 4–6 columns, `columns` from 1024px. One
    column stays one column. The cards stretch to the row's height. The
    grid adds no outer margin; block spacing comes from the editor.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-product-grid="{{ $source }}">
    @if ( $products->isNotEmpty() )
        <section class="ec-product-grid" @if ( null !== $heading ) aria-labelledby="{{ $headingId }}" @else aria-label="{{ __( 'Products' ) }}" @endif>
            @if ( null !== $heading )
                <h2 id="{{ $headingId }}" class="ec-product-grid__title">{{ $heading }}</h2>
            @endif

            <ul role="list" class="ec-product-grid__list" data-columns="{{ $columns }}" data-product-grid-list>
                @foreach ( $products as $product )
                    <li class="ec-product-grid__item" wire:key="product-grid-{{ $product->id }}">
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
