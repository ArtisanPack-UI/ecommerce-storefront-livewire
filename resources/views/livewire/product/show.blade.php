{{--
    The product page (#134). See Product\Show.

    The breadcrumbs (the shop, the product's category trail, the product),
    then two columns from 1024px (the gallery at 1.1fr, the summary at 1fr;
    stacked below that). The summary: the category link, the name at
    display-lg, the rating linking to the reviews, the price, the short
    description, the stock badge and SKU, a divider, the purchase form,
    the sold-out notice when it is out of stock (a satellite can make it a
    "Notify me" form), and the delivery and returns list. Then "Product
    details" beside its accordion (description, a specifications table,
    shipping and returns), and the filterable page sections (reviews,
    upsells, related products).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<article class="ec-product" aria-labelledby="ec-product-{{ $product->id }}-name" data-product-page="{{ $product->id }}">
    @if ( count( $breadcrumbs ) > 1 )
        <x-artisanpack-breadcrumbs :items="$breadcrumbs" :label="__( 'Breadcrumb' )" :navigate="false" />
    @endif

    <div class="ec-product__top">
        <x-artisanpack-ec-gallery :images="$images" :name="$product->name" :scope="'product-' . $product->id" />

        <div class="ec-product__summary" data-product-summary>
            @if ( null !== $category )
                @if ( null !== $category['url'] )
                    <a href="{{ $category['url'] }}" class="ec-product__category" data-product-category>{{ $category['name'] }}</a>
                @else
                    <p class="ec-product__category" data-product-category>{{ $category['name'] }}</p>
                @endif
            @endif

            <h1 id="ec-product-{{ $product->id }}-name" class="ec-product__name">{{ $product->name }}</h1>

            <x-artisanpack-ec-rating-summary :rating="(float) $product->avg_rating" :count="(int) $product->reviews_count" :href="$reviewsUrl" />

            <x-artisanpack-ec-price :price="$price" size="lg" data-product-price />

            @if ( null !== $shortDescription )
                <div class="apui-prose ec-product__short-description" data-product-short-description>{!! $shortDescription !!}</div>
            @endif

            <div class="ec-product__meta">
                <x-artisanpack-ec-stock-status :status="$stock" data-product-stock />

                @if ( null !== $sku && '' !== $sku )
                    <p class="ec-product__sku" data-product-sku>{{ __( 'SKU' ) }} <code>{{ $sku }}</code></p>
                @endif
            </div>

            <div class="ec-product__purchase">
                @if ( null !== $form )
                    @livewire( $form, [ 'product' => $product ], key( 'purchase-form-' . $product->id ) )
                @else
                    <x-artisanpack-alert icon="info" :title="__( 'This product can\'t be purchased online' )" data-product-unavailable />
                @endif

                @if ( $soldOut instanceof \Illuminate\Contracts\Support\Htmlable )
                    {{ $soldOut }}
                @elseif ( is_string( $soldOut ) )
                    @include( $soldOut, [ 'product' => $product ] )
                @endif
            </div>

            @if ( [] !== $highlights )
                <ul role="list" class="apui-card ec-product__highlights" data-surface="default" data-product-highlights>
                    @foreach ( $highlights as $highlight )
                        <li class="ec-product__highlight">
                            <x-artisanpack-icon :name="$highlight['icon']" class="ec-product__highlight-icon" aria-hidden="true" />
                            <span>
                                <span class="ec-product__highlight-title">{{ $highlight['title'] }}</span>
                                @if ( null !== $highlight['description'] )
                                    <span class="ec-product__highlight-description">{{ $highlight['description'] }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @if ( null !== $description || [] !== $specifications || null !== $shippingReturns )
        <section class="ec-product__details" aria-labelledby="ec-product-{{ $product->id }}-details" data-product-details>
            <h2 id="ec-product-{{ $product->id }}-details" class="ec-product__section-title">{{ __( 'Product details' ) }}</h2>

            <x-artisanpack-accordion wire:model="openSection" class="ec-product__accordion">
                @if ( null !== $description )
                    <x-artisanpack-accordion-item value="description" :title="__( 'Description' )" data-details-section="description">
                        <div class="apui-prose">{!! $description !!}</div>
                    </x-artisanpack-accordion-item>
                @endif

                @if ( [] !== $specifications )
                    <x-artisanpack-accordion-item value="specifications" :title="__( 'Specifications' )" data-details-section="specifications">
                        <div class="apui-table ec-product__specs" data-density="compact">
                            <div data-scope="table" data-part="scroll">
                                <table data-scope="table" data-part="table">
                                    <caption class="apui-sr-only">{{ __( 'Specifications' ) }}</caption>
                                    <tbody>
                                        @foreach ( $specifications as $row )
                                            <tr data-scope="table" data-part="row">
                                                <th scope="row">{{ $row['label'] }}</th>
                                                <td>{{ $row['value'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </x-artisanpack-accordion-item>
                @endif

                @if ( null !== $shippingReturns )
                    <x-artisanpack-accordion-item value="shipping" :title="__( 'Shipping & returns' )" data-details-section="shipping">
                        <div class="apui-prose">{!! $shippingReturns !!}</div>
                    </x-artisanpack-accordion-item>
                @endif
            </x-artisanpack-accordion>
        </section>
    @endif

    @foreach ( $sections as $key => $section )
        <div class="ec-product__section" data-product-section="{{ $key }}">
            @livewire( $section['component'], $section['params'], key( 'product-section-' . $product->id . '-' . $key ) )
        </div>
    @endforeach
</article>
