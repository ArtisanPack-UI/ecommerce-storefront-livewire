{{--
    Digital product purchase form. See Product\Forms\DigitalForm.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@php( $ecommerceFormId = 'ec-product-' . $product->id )
<div class="flex flex-col gap-4" data-purchase-form="digital">
    <ul role="list" class="flex flex-col gap-1 text-sm" data-digital-notes>
        @foreach ( $notes as $note )
            <li class="flex items-start gap-2">
                <x-artisanpack-icon name="o-arrow-down-tray" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span>{{ $note }}</span>
            </li>
        @endforeach
    </ul>

    @if ( [] !== $licenseTypes )
        <x-artisanpack-select
            :id="$ecommerceFormId . '-license'"
            :label="__( 'Licence' )"
            :options="collect( $licenseTypes )->map( fn ( string $label, string $value ): array => [ 'id' => $value, 'name' => $label ] )->values()->all()"
            :placeholder="__( 'Choose a licence' )"
            wire:model="licenseType"
            error-field="licenseType"
        />
    @endif

    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [ 'formId' => $ecommerceFormId ] )
</div>
