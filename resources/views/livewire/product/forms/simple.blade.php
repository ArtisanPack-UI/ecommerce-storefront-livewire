{{--
    Simple product purchase form. See Product\Forms\SimpleForm.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div data-purchase-form="simple">
    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [ 'formId' => 'ec-product-' . $product->id ] )
</div>
