{{--
    A tag page (#132). See Catalog\TagShow.

    The category page's layout without its image half and chips: the
    breadcrumbs, then a design-system Card with the "Tag" eyebrow and the
    tag's name at display-lg (a <div>, so the page keeps one banner
    landmark), then the catalog listing for the tag.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="ec-page" data-tag-page="{{ $tag->id }}">
    @if ( [] !== $breadcrumbs )
        <x-artisanpack-breadcrumbs :items="$breadcrumbs" :label="__( 'Breadcrumb' )" :navigate="false" />
    @endif

    <div class="apui-card ec-category-hero" data-surface="default" data-tag-header>
        <div class="ec-category-hero__body">
            <p class="ec-category-hero__eyebrow">{{ __( 'Tag' ) }}</p>

            @php( $heading = 'h2' === ( $heading ?? null ) ? 'h2' : 'h1' )
            <{{ $heading }} class="ec-category-hero__title">{{ $tag->name }}</{{ $heading }}>
        </div>
    </div>

    <livewire:artisanpack-ecommerce-storefront-catalog :tag="(int) $tag->id" :key="'tag-catalog-' . $tag->id" />
</div>
