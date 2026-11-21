{{--
    The product page's default sold-out notice (#134), under the purchase
    form when the product or the chosen variant is out of stock. A
    satellite can replace it, for example with a "Notify me" form, through
    `ap.ecommerceStorefrontLivewire.product.soldOut`. See Product\Show.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      2.0.0
--}}
<x-artisanpack-alert color="neutral" icon="mail" :title="__( 'This item is sold out' )" class="ec-product__sold-out" data-product-sold-out>
    {{ __( 'Check back soon. It may be restocked, and similar pieces are below.' ) }}
</x-artisanpack-alert>
