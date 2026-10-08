{{--
    Grouped product purchase form. See Product\Forms\GroupedForm.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceFormId = 'ec-product-' . $product->id )
<div class="flex flex-col gap-4" data-purchase-form="grouped">
    @if ( [] === $rows )
        <p class="text-base-content/70">{{ __( 'None of the products in this group are available right now.' ) }}</p>
    @else
        <ul role="list" class="divide-y divide-base-content/10" aria-label="{{ __( 'Products in this group' ) }}">
            @foreach ( $rows as $row )
                <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="grouped-child-{{ $row['id'] }}" data-grouped-child="{{ $row['id'] }}">
                    <div class="flex flex-col gap-1">
                        <span class="font-medium">{{ $row['name'] }}</span>
                        <x-artisanpack-ec-price :price="$row['price']" />
                        <x-artisanpack-ec-stock-status :status="$row['stock']" />
                    </div>

                    <x-artisanpack-ec-quantity
                        :id="$ecommerceFormId . '-child-' . $row['id']"
                        :min="0"
                        :item-name="$row['name']"
                        :label="__( 'Quantity of :name', [ 'name' => $row['name'] ] )"
                        wire:model="quantities.{{ $row['id'] }}"
                        :error="$errors->first( 'quantities.' . $row['id'] )"
                        :disabled="! $row['buyable']"
                        placeholder="0"
                    />
                </li>
            @endforeach
        </ul>

        @error( 'quantities' )
            <p class="text-sm text-error" data-form-error>{{ $message }}</p>
        @enderror

        @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [
            'formId'        => $ecommerceFormId,
            'showQuantity'  => false,
            'blockedReason' => __( 'None of these products are in stock.' ),
        ] )
    @endif
</div>
