{{--
    Simple product purchase form. See Product\Forms\SimpleForm.

    Under the product summary (#99): the shared add-to-cart row, quantity
    and "Add to cart" in one line (wrapping on a narrow screen), with the
    line price on the button.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-purchase-form" data-purchase-form="simple">
    @include( 'ecommerce-storefront::livewire.product.forms.partials.add-to-cart', [ 'formId' => 'ec-product-' . $product->id ] )
</div>
