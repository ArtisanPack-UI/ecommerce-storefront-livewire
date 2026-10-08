{{--
    The product page. See Product\Show.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<article class="flex flex-col gap-10" aria-labelledby="ec-product-{{ $product->id }}-name" data-product-page="{{ $product->id }}">
    <div class="grid gap-8 lg:grid-cols-2">
        <x-artisanpack-ec-gallery :images="$images" :name="$product->name" :scope="'product-' . $product->id" />

        <div class="flex flex-col gap-4" data-product-summary>
            <h1 id="ec-product-{{ $product->id }}-name" class="text-3xl font-bold">{{ $product->name }}</h1>

            <x-artisanpack-ec-rating-summary :rating="(float) $product->avg_rating" :count="(int) $product->reviews_count" :href="$reviewsUrl" />

            <x-artisanpack-ec-price :price="$price" class="text-xl" data-product-price />

            @if ( null !== $shortDescription )
                <div class="prose max-w-none" data-product-short-description>{!! $shortDescription !!}</div>
            @endif

            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <x-artisanpack-ec-stock-status :status="$stock" data-product-stock />

                @if ( null !== $sku && '' !== $sku )
                    <span data-product-sku>{{ __( 'SKU: :sku', [ 'sku' => $sku ] ) }}</span>
                @endif
            </div>

            <div class="border-t border-base-content/10 pt-4">
                @if ( null !== $form )
                    @livewire( $form, [ 'product' => $product ], key( 'purchase-form-' . $product->id ) )
                @else
                    <x-artisanpack-alert icon="o-information-circle" :title="__( 'This product can\'t be purchased online' )" data-product-unavailable />
                @endif
            </div>
        </div>
    </div>

    @if ( null !== $description || [] !== $specifications || null !== $shippingReturns )
        <section aria-labelledby="ec-product-{{ $product->id }}-details" data-product-details>
            <h2 id="ec-product-{{ $product->id }}-details" class="mb-4 text-2xl font-bold">{{ __( 'Product details' ) }}</h2>

            <x-artisanpack-accordion wire:model="openSection">
                @if ( null !== $description )
                    <x-artisanpack-collapse name="description" data-details-section="description">
                        <x-slot:heading>{{ __( 'Description' ) }}</x-slot:heading>
                        <x-slot:content>
                            <div class="prose max-w-none">{!! $description !!}</div>
                        </x-slot:content>
                    </x-artisanpack-collapse>
                @endif

                @if ( [] !== $specifications )
                    <x-artisanpack-collapse name="specifications" data-details-section="specifications">
                        <x-slot:heading>{{ __( 'Specifications' ) }}</x-slot:heading>
                        <x-slot:content>
                            <table class="table table-sm">
                                <caption class="sr-only">{{ __( 'Specifications' ) }}</caption>
                                <tbody>
                                    @foreach ( $specifications as $row )
                                        <tr>
                                            <th scope="row" class="w-1/3">{{ $row['label'] }}</th>
                                            <td>{{ $row['value'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-slot:content>
                    </x-artisanpack-collapse>
                @endif

                @if ( null !== $shippingReturns )
                    <x-artisanpack-collapse name="shipping" data-details-section="shipping">
                        <x-slot:heading>{{ __( 'Shipping & returns' ) }}</x-slot:heading>
                        <x-slot:content>
                            <div class="prose max-w-none">{!! $shippingReturns !!}</div>
                        </x-slot:content>
                    </x-artisanpack-collapse>
                @endif
            </x-artisanpack-accordion>
        </section>
    @endif

    @foreach ( $sections as $key => $section )
        <div data-product-section="{{ $key }}">
            @livewire( $section['component'], $section['params'], key( 'product-section-' . $product->id . '-' . $key ) )
        </div>
    @endforeach
</article>
