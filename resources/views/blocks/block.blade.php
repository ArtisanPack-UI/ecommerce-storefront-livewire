{{--
    A commerce block's wrapper. See Blocks\StorefrontBlock.

    `$html` is the block's rendered (escaped) Blade output. In an editor
    preview that fell back to the newest product, a label says so.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-commerce-block" data-commerce-block="{{ $slug }}">
    @if ( $sample )
        <div class="mb-3 flex flex-col items-start gap-1 rounded-box border border-dashed border-base-content/30 p-3 text-sm" data-commerce-sample>
            <span class="badge badge-neutral badge-sm">{{ __( 'Sample product' ) }}</span>
            <span>{{ __( 'Showing the newest product as a sample. On a product page this block shows that page\'s product; set a product ID in the block settings to always show one product.' ) }}</span>
        </div>
    @endif

    {!! $html !!}
</div>
