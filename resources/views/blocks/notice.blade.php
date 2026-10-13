{{--
    A commerce block's notice in the editor preview, when there is nothing
    to show. See Blocks\StorefrontBlock.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="rounded-box border border-dashed border-base-content/30 p-4 text-sm" data-commerce-block="{{ $slug }}" data-commerce-block-notice>
    <p class="font-semibold">{{ $title }}</p>
    <p class="text-base-content/70">{{ $message }}</p>
</div>
