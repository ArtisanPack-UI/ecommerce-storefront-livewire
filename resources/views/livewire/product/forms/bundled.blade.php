{{--
    Bundled product purchase form. See Product\Forms\BundledForm.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-4" data-purchase-form="bundled">
    @if ( [] !== $items )
        <div class="flex flex-col gap-2">
            <h2 id="ec-product-{{ $product->id }}-includes" class="text-sm font-semibold">{{ __( 'This bundle includes' ) }}</h2>

            <ul role="list" class="flex flex-col gap-1" aria-labelledby="ec-product-{{ $product->id }}-includes">
                @foreach ( $items as $item )
                    <li class="flex items-center gap-2" wire:key="bundle-item-{{ $item['id'] }}" data-bundle-item="{{ $item['id'] }}">
                        <x-artisanpack-icon name="o-check" class="h-4 w-4 text-success" aria-hidden="true" />
                        <span>{{ __( ':quantity × :name', [ 'quantity' => $item['quantity'], 'name' => $item['name'] ] ) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [ 'formId' => 'ec-product-' . $product->id ] )
</div>
