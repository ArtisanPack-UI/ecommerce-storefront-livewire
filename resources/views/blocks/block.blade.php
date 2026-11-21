{{--
    A commerce block's wrapper (#91). See Blocks\StorefrontBlock.

    `$html` is the block's rendered (escaped) Blade output. In an editor
    preview that fell back to the newest product, a dashed sample notice
    (a soft info "Sample product" badge and one line of help) sits above
    it, framed so it never reads as storefront content. On the storefront
    the block renders alone.

    An editor preview (`$preview`) has no storefront layout around it, so it
    inlines the storefront's styles itself (once per request).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@if ( $preview ?? false )
    @include( 'ecommerce-storefront::partials.styles' )
@endif
<div class="ec-commerce-block" data-commerce-block="{{ $slug }}">
    @if ( $sample )
        <div class="ec-block-notice ec-block-notice--sample" data-commerce-sample>
            <x-artisanpack-badge color="info" leading-icon="info">{{ __( 'Sample product' ) }}</x-artisanpack-badge>
            <p class="ec-block-notice__message">{{ __( 'Showing the newest product as a sample. On a product page this block shows that page\'s product; set a product ID in the block settings to always show one product.' ) }}</p>
        </div>
    @endif

    {!! $html !!}
</div>
