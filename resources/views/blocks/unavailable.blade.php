{{--
    The Add-to-Cart block for a product with no purchase form (#113): a
    neutral design-system Alert with the info icon. See
    Blocks\AddToCartBlock.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<x-artisanpack-alert color="neutral" icon="info" :title="__( 'This product can\'t be purchased online' )" data-product-unavailable />
