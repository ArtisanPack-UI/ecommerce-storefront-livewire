{{--
    A commerce block's notice in the editor preview (#91), when there is
    nothing to show or the block failed. See Blocks\StorefrontBlock.

    The block's icon and title over the message, in a dashed frame so it
    never reads as storefront content. `$error` swaps in the error border,
    an alert icon and an error-toned title. Notices only render in editor
    previews, which have no storefront layout, so this inlines the
    storefront's styles itself (once per request).

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
@include( 'ecommerce-storefront::partials.styles' )
@php( $ecommerceNoticeError = (bool) ( $error ?? false ) )
<div
    @class( [ 'ec-block-notice', 'ec-block-notice--error' => $ecommerceNoticeError ] )
    data-commerce-block="{{ $slug }}"
    data-commerce-block-notice
    @if ( $ecommerceNoticeError ) data-error @endif
>
    <p class="ec-block-notice__title">
        <x-artisanpack-icon :name="$icon ?? 'shopping-bag'" />
        <span>{{ $title }}</span>
    </p>
    <p class="ec-block-notice__message">{{ $message }}</p>
</div>
