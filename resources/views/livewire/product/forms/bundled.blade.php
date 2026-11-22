{{--
    Bundled product purchase form. See Product\Forms\BundledForm.

    Under the product summary (#102): "In this set", each member a checked,
    disabled design-system Checkbox described "Included" ("2 × Brush" when
    the bundle holds more than one); then the total for the quantity with a
    success "You save $26.00" badge when the members cost more on their
    own; then the shared add-to-cart row with "Add set to cart" (the total
    carries the price, so the button doesn't).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceFormId = 'ec-product-' . $product->id )
<div class="ec-purchase-form" data-purchase-form="bundled">
    @if ( [] !== $items )
        <fieldset class="ec-bundle-items" data-bundle-items>
            <legend class="ec-bundle-items__legend">{{ __( 'In this set' ) }}</legend>

            <div class="ec-bundle-items__list">
                @foreach ( $items as $item )
                    <x-artisanpack-checkbox
                        :id="$ecommerceFormId . '-bundle-item-' . $item['id']"
                        :label="1 === $item['quantity'] ? $item['name'] : __( ':quantity × :name', [ 'quantity' => $item['quantity'], 'name' => $item['name'] ] )"
                        :description="__( 'Included' )"
                        checked
                        disabled
                        data-bundle-item="{{ $item['id'] }}"
                    />
                @endforeach
            </div>
        </fieldset>
    @endif

    @if ( null !== $total )
        <p class="ec-bundle-total" aria-live="polite" aria-atomic="true" data-bundle-total>
            <span class="apui-sr-only">{{ __( 'Total' ) }}</span>
            <bdi class="ec-bundle-total__price">{{ $total }}</bdi>
            @if ( null !== $saving )
                <x-artisanpack-badge variant="soft" color="success" data-bundle-saving>{{ __( 'You save :amount', [ 'amount' => $saving ] ) }}</x-artisanpack-badge>
            @endif
        </p>
    @endif

    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [
        'formId'        => $ecommerceFormId,
        'buttonLabel'   => $buttonText ?? __( 'Add set to cart' ),
        'priceOnButton' => false,
    ] )
</div>
