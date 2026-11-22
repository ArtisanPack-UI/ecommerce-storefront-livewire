{{--
    Grouped product purchase form. See Product\Forms\GroupedForm.

    Under the product summary (#101): the design-system Table of the
    group's products (item, price, quantity). A product that can't be
    bought shows its stock badge (or "Unavailable") and a dash for its
    quantity. A running "2 items · $56.00" line sits above "Add selected to
    cart", which adds every product with a quantity; the quantities bind on
    change so the line follows them.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceFormId = 'ec-product-' . $product->id )
<div class="ec-purchase-form" data-purchase-form="grouped">
    @if ( [] === $rows )
        <p class="ec-purchase-form__notice">{{ __( 'None of the products in this group are available right now.' ) }}</p>
    @else
        <x-artisanpack-table
            :headers="[
                [ 'key' => 'name', 'label' => __( 'Item' ) ],
                [ 'key' => 'price', 'label' => __( 'Price' ), 'numeric' => true ],
                [ 'key' => 'quantity', 'label' => __( 'Quantity' ), 'numeric' => true ],
            ]"
            :rows="$rows"
            :label="__( 'Products in this group' )"
            :sticky-header="false"
            class="ec-grouped-table"
            data-grouped-table
        >
            @scope( 'header_quantity', $header )
                <span aria-hidden="true">{{ __( 'Qty' ) }}</span><span class="apui-sr-only">{{ $header['label'] }}</span>
            @endscope

            @scope( 'cell_name', $row )
                <span class="ec-grouped-table__item" data-grouped-child="{{ $row['id'] }}">
                    <span class="ec-grouped-table__name">{{ $row['name'] }}</span>
                    @if ( ! $row['buyable'] )
                        @if ( $row['stock']->purchasable() )
                            <x-artisanpack-badge variant="soft" color="neutral" size="sm" data-grouped-unavailable>{{ __( 'Unavailable' ) }}</x-artisanpack-badge>
                        @else
                            <x-artisanpack-ec-stock-status :status="$row['stock']" />
                        @endif
                    @endif
                </span>
            @endscope

            @scope( 'cell_price', $row )
                @if ( null !== $row['price'] )
                    <x-artisanpack-ec-price :price="$row['price']" />
                @else
                    <span aria-hidden="true">–</span><span class="apui-sr-only">{{ __( 'No price' ) }}</span>
                @endif
            @endscope

            @scope( 'cell_quantity', $row, $ecommerceFormId, $errors )
                @if ( $row['buyable'] )
                    <x-artisanpack-input
                        :id="$ecommerceFormId . '-child-' . $row['id']"
                        type="number"
                        inputmode="numeric"
                        min="0"
                        max="{{ \ArtisanPackUI\Ecommerce\Services\StorefrontCartService::MAX_LINE_QUANTITY }}"
                        step="1"
                        placeholder="0"
                        :aria-label="__( 'Quantity of :name', [ 'name' => $row['name'] ] )"
                        wire:model.live.change="quantities.{{ $row['id'] }}"
                        :error="$errors->first( 'quantities.' . $row['id'] ) ?: null"
                        class="ec-grouped-table__quantity"
                        data-grouped-quantity="{{ $row['id'] }}"
                    />
                @else
                    <span aria-hidden="true">–</span><span class="apui-sr-only">{{ __( ':name can\'t be added right now.', [ 'name' => $row['name'] ] ) }}</span>
                @endif
            @endscope
        </x-artisanpack-table>

        @error( 'quantities' )
            <p id="{{ $ecommerceFormId }}-quantities-error" class="ec-purchase-form__error" data-form-error><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
        @enderror

        <p class="ec-grouped-summary" aria-live="polite" aria-atomic="true" data-grouped-summary>
            @if ( $summary['count'] > 0 )
                <span>{{ trans_choice( ':count item|:count items', $summary['count'], [ 'count' => $summary['count'] ] ) }}</span>
                @if ( null !== $summary['total'] )
                    <span class="apui-sr-only">·</span>
                    <bdi class="ec-grouped-summary__total" data-grouped-total>{{ $summary['total'] }}</bdi>
                @endif
            @endif
        </p>

        @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [
            'formId'        => $ecommerceFormId,
            'showQuantity'  => false,
            'blockedReason' => __( 'None of these products are in stock.' ),
            'buttonLabel'   => $buttonText ?? __( 'Add selected to cart' ),
        ] )
    @endif
</div>
