{{--
    A tag page. See Catalog\TagShow.

    @package    ArtisanPack_UI
    @subpackage EcommerceStorefrontLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6" data-tag-page="{{ $tag->id }}">
    @if ( [] !== $breadcrumbs )
        <nav aria-label="{{ __( 'Breadcrumb' ) }}">
            <x-artisanpack-breadcrumbs :items="$breadcrumbs" no-wire-navigate />
        </nav>
    @endif

    <h1 class="text-3xl font-bold">{{ $tag->name }}</h1>

    <livewire:artisanpack-ecommerce-storefront-catalog :tag="(int) $tag->id" :key="'tag-catalog-' . $tag->id" />
</div>
