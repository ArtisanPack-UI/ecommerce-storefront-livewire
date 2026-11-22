{{--
    Variable product purchase form (the variation picker). See
    Product\Forms\VariableForm.

    Under the product summary (#100): one required group per variation
    attribute, as swatches, or as a Select when it has more values than
    `product.variation_select_after`; then the shared add-to-cart row. The
    product page shows the price range until every choice is made, then the
    variant's price. Pressing "Add to cart" with a choice missing shows a
    field error on that group ("Choose a size to add this to your cart.").

    A choice can clear another group's (a colour without that size). The
    morph updates each radio's `checked` attribute (and each option's
    `selected`), not the state the shopper's click set, so every radio and
    select is re-synced from its attributes once `choose()` returns.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php
    $ecommerceFormId = 'ec-product-' . $product->id;
    $ecommerceResync = ".then( () => { const form = \$el.closest( '[data-purchase-form]' ); form?.querySelectorAll( 'input[data-swatch-value]' ).forEach( ( input ) => input.checked = input.hasAttribute( 'checked' ) ); form?.querySelectorAll( 'select' ).forEach( ( select ) => select.value = select.querySelector( 'option[selected]' )?.value ?? '' ); } )";
@endphp
<div class="ec-purchase-form" data-purchase-form="variable">
    @foreach ( $groups as $group )
        @php
            $ecommerceGroupId    = $ecommerceFormId . '-attribute-' . $group['id'];
            $ecommerceGroupError = $errors->first( 'selected.' . $group['id'] );
            $ecommerceDescribed  = $errors->has( 'variant' ) ? $ecommerceFormId . '-variant-error' : null;
        @endphp

        @if ( 'select' === $group['control'] )
            @php( $ecommerceChosen = collect( $group['options'] )->firstWhere( 'selected', true ) )
            <div class="ec-purchase-form__select" wire:key="variation-group-{{ $group['id'] }}" data-variation-group="{{ $group['id'] }}">
                <x-artisanpack-select
                    :id="$ecommerceGroupId"
                    :label="$group['label']"
                    :options="array_map( static fn ( array $option ): array => [
                        'value'    => $option['value'],
                        'label'    => null === $option['reason'] ? $option['label'] : __( ':value (:reason)', [ 'value' => $option['label'], 'reason' => $option['reason'] ] ),
                        'disabled' => $option['disabled'],
                    ], $group['options'] )"
                    :value="$ecommerceChosen['value'] ?? null"
                    :placeholder="$group['placeholder']"
                    :error="'' === $ecommerceGroupError ? null : $ecommerceGroupError"
                    required
                    x-on:change="$wire.choose( {{ (int) $group['id'] }}, $event.target.value ){{ $ecommerceResync }}"
                    :aria-describedby="$ecommerceDescribed"
                />
            </div>
        @else
            <x-artisanpack-ec-swatches
                :name="$ecommerceGroupId"
                :legend="$group['label']"
                :options="$group['options']"
                :id-prefix="$ecommerceGroupId"
                :error="'' === $ecommerceGroupError ? null : $ecommerceGroupError"
                required
                x-on:change="$wire.choose( {{ (int) $group['id'] }}, $event.target.value ){{ $ecommerceResync }}"
                wire:key="variation-group-{{ $group['id'] }}"
                :aria-describedby="$ecommerceDescribed"
                data-variation-group="{{ $group['id'] }}"
            />
        @endif
    @endforeach

    <p @class( [ 'ec-purchase-form__notice', 'apui-sr-only' => '' === $selectionNotice ] ) role="status" aria-live="polite" aria-atomic="true" data-selection-notice>{{ $selectionNotice }}</p>

    @error( 'variant' )
        <p id="{{ $ecommerceFormId }}-variant-error" class="ec-purchase-form__error" data-variant-error><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
    @enderror

    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [ 'formId' => $ecommerceFormId ] )
</div>
