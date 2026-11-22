{{--
    Digital product purchase form. See Product\Forms\DigitalForm.

    Under the product summary (#103): the licence types as design-system
    radio cards (when the product offers them), then what the shopper gets
    as muted notes beside the download icon, then the shared add-to-cart
    row with the line price on the button.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceFormId = 'ec-product-' . $product->id )
<div class="ec-purchase-form" data-purchase-form="digital">
    @if ( [] !== $licenseTypes )
        <x-artisanpack-radio-group
            :id="$ecommerceFormId . '-license'"
            :label="__( 'Licence' )"
            variant="cards"
            :options="collect( $licenseTypes )->map( fn ( string $label, string $value ): array => [ 'value' => $value, 'label' => $label ] )->values()->all()"
            :value="'' === $licenseType ? null : $licenseType"
            required
            wire:model="licenseType"
            error-field="licenseType"
            data-license-types
        />
    @endif

    <div class="ec-digital-notes" data-digital-notes>
        <x-artisanpack-icon name="download" class="ec-digital-notes__icon" aria-hidden="true" />
        <ul role="list" class="ec-digital-notes__list">
            @foreach ( $notes as $note )
                <li>{{ $note }}</li>
            @endforeach
        </ul>
    </div>

    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [ 'formId' => $ecommerceFormId ] )
</div>
